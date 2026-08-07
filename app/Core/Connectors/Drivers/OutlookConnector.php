<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{MailActionPolicy, OAuthTokenService};
use App\Models\ConnectorConnection;
use Illuminate\Support\Str;

final class OutlookConnector extends AbstractConnector
{
    public function __construct(private readonly OAuthTokenService $tokens) {}
    public function id(): string { return 'outlook'; }
    public function label(): string { return 'Microsoft Outlook'; }

    public function actions(): array
    {
        return [
            new ConnectorAction('account','Get the connected mail account.',MailActionPolicy::risk('account'),[],['mail.account.read']),
            new ConnectorAction('search','Search mailbox messages.',MailActionPolicy::risk('search'),['type'=>'object','properties'=>['query'=>['type'=>'string'],'limit'=>['type'=>'integer']]],['mail.search']),
            new ConnectorAction('thread','List normalized messages from one mail conversation.',MailActionPolicy::risk('thread'),['type'=>'object','properties'=>['conversation_id'=>['type'=>'string']],'required'=>['conversation_id']],['mail.thread.read','mail.message.read']),
            new ConnectorAction('draft','Create an email draft.',MailActionPolicy::risk('draft'),self::messageParameters(),['mail.draft']),
            new ConnectorAction('send','Send an email. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('send'),self::messageParameters(),['mail.send']),
            new ConnectorAction('reply','Reply to a mail message. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('reply'),self::messageParameters(true),['mail.reply','mail.send']),
        ];
    }

    public function configurationSchema(): array
    {
        return [
            'credentials'=>['client_secret'=>['label'=>'Microsoft OAuth client secret','secret'=>true,'required'=>true,'help'=>'Stored encrypted.']],
            'fields'=>['client_id'=>['label'=>'Microsoft application (client) ID','required'=>true],'tenant'=>['label'=>'Tenant','required'=>true,'default'=>'common','help'=>'Use common, organizations, consumers, or your tenant ID.']],
        ];
    }

    public function test(ConnectorConnection $connection): bool
    {
        try { return $this->request($connection)->get('https://graph.microsoft.com/v1.0/me')->successful(); } catch (\Throwable) { return false; }
    }

    public function execute(ConnectorConnection $connection, string $action, array $arguments): ConnectorResult
    {
        $this->action($action);
        $request=$this->request($connection);
        return match($action) {
            'account'=>ConnectorResult::success($request->get('https://graph.microsoft.com/v1.0/me')->throw()->json()),
            'search'=>ConnectorResult::success($this->normalizeMessages((array)$request->withHeaders(['ConsistencyLevel'=>'eventual'])->get('https://graph.microsoft.com/v1.0/me/messages', ['$search'=>'"'.str_replace('"','',(string)($arguments['query']??'')).'"','$top'=>max(1,min(100,(int)($arguments['limit']??20))),'$select'=>'id,internetMessageId,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,isRead,bodyPreview,conversationId'])->throw()->json())),
            'thread'=>ConnectorResult::success($this->normalizeMessages((array)$request->get('https://graph.microsoft.com/v1.0/me/messages', ['$filter'=>"conversationId eq '" . str_replace("'","''",(string)($arguments['conversation_id']??'')) . "'",'$top'=>100,'$select'=>'id,internetMessageId,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,isRead,bodyPreview,body,conversationId'])->throw()->json(), (string)($arguments['conversation_id']??''))),
            'draft'=>ConnectorResult::success($request->post('https://graph.microsoft.com/v1.0/me/messages',$this->graphMessage($arguments))->throw()->json()),
            'send'=>ConnectorResult::success($this->send($request,$arguments)),
            'reply'=>ConnectorResult::success($this->reply($request,$arguments)),
            default=>ConnectorResult::failure('Unsupported action'),
        };
    }

    private function request(ConnectorConnection $connection)
    {
        return $this->client(['Authorization'=>'Bearer '.$this->tokens->accessToken($connection)]);
    }

    private function send($request,array $arguments): array
    {
        $response=$request->post('https://graph.microsoft.com/v1.0/me/sendMail',['message'=>$this->graphMessage($arguments),'saveToSentItems'=>true]);
        $response->throw(); return ['sent'=>true,'status'=>$response->status()];
    }

    private function reply($request,array $arguments): array
    {
        $id=rawurlencode((string)($arguments['message_id']??'')); if($id==='')throw new \InvalidArgumentException('message_id is required for mail replies.');
        $response=$request->post("https://graph.microsoft.com/v1.0/me/messages/{$id}/reply",['comment'=>(string)($arguments['body']??'')]);
        $response->throw(); return ['sent'=>true,'status'=>$response->status()];
    }

    private function graphMessage(array $arguments): array
    {
        $to=trim((string)($arguments['to']??'')); if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('A valid recipient is required.');
        $subject=trim(str_replace(["\r","\n"],' ',(string)($arguments['subject']??''))); $body=(string)($arguments['body']??'');
        if($subject===''||$body==='')throw new \InvalidArgumentException('Email subject and body are required.');
        return ['subject'=>$subject,'body'=>['contentType'=>!empty($arguments['html'])?'HTML':'Text','content'=>$body],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalizeMessages(array $payload, ?string $threadId = null): array
    {
        $messages=[];
        foreach(array_slice((array)($payload['value']??[]),0,100) as $message){
            if(!is_array($message))continue;
            $body=is_array($message['body']??null)?$message['body']:[];
            $rawBody=(string)($body['content']??'');
            $isHtml=strtoupper((string)($body['contentType']??''))==='HTML';
            $text=$rawBody!==''?($isHtml?trim(html_entity_decode(strip_tags($rawBody),ENT_QUOTES|ENT_HTML5,'UTF-8')):$rawBody):(string)($message['bodyPreview']??'');
            $messages[]=[
                'id'=>(string)($message['id']??''),
                'thread_id'=>(string)($message['conversationId']??$threadId??''),
                'message_id'=>(string)($message['internetMessageId']??''),
                'in_reply_to'=>'',
                'references'=>'',
                'from'=>$this->address(data_get($message,'from.emailAddress')),
                'to'=>$this->addresses((array)($message['toRecipients']??[])),
                'cc'=>$this->addresses((array)($message['ccRecipients']??[])),
                'subject'=>(string)($message['subject']??''),
                'sent_at'=>$message['sentDateTime']??null,
                'received_at'=>$message['receivedDateTime']??null,
                'unread'=>array_key_exists('isRead',$message)?!(bool)$message['isRead']:null,
                'snippet'=>Str::limit((string)($message['bodyPreview']??''),1000,'…'),
                'text'=>Str::limit($text,20000,'…'),
                'html'=>$isHtml?Str::limit($rawBody,20000,'…'):null,
                'truncated'=>mb_strlen($text)>20000||($isHtml&&mb_strlen($rawBody)>20000),
            ];
        }
        return ['thread_id'=>$threadId,'message_count'=>count($messages),'messages'=>$messages];
    }

    /** @param array<string,mixed>|null $address */
    private function address(?array $address): string
    {
        if(!$address)return '';
        $email=trim((string)($address['address']??''));$name=trim((string)($address['name']??''));
        return $name!==''&&$email!==''?"{$name} <{$email}>":($email!==''?$email:$name);
    }

    /** @param array<int,mixed> $recipients */
    private function addresses(array $recipients): string
    {
        $out=[];foreach($recipients as $recipient){$value=$this->address(is_array($recipient)?(is_array($recipient['emailAddress']??null)?$recipient['emailAddress']:null):null);if($value!=='')$out[]=$value;}
        return implode(', ',$out);
    }

    private static function messageParameters(bool $reply=false):array
    {
        if($reply)return ['type'=>'object','properties'=>['message_id'=>['type'=>'string'],'body'=>['type'=>'string']],'required'=>['message_id','body']];
        return ['type'=>'object','properties'=>['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean']],'required'=>['to','subject','body']];
    }
}
