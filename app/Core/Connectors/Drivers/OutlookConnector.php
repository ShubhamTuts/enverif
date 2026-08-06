<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{MailActionPolicy, OAuthTokenService};
use App\Models\ConnectorConnection;

final class OutlookConnector extends AbstractConnector
{
    public function __construct(private readonly OAuthTokenService $tokens) {}
    public function id(): string { return 'outlook'; }
    public function label(): string { return 'Microsoft Outlook'; }

    public function actions(): array
    {
        return [
            new ConnectorAction('account','Get the connected Microsoft account.',MailActionPolicy::risk('account')),
            new ConnectorAction('search','Search Outlook messages.',MailActionPolicy::risk('search'),['type'=>'object','properties'=>['query'=>['type'=>'string'],'limit'=>['type'=>'integer']]]),
            new ConnectorAction('thread','List messages from one Outlook conversation ID.',MailActionPolicy::risk('thread'),['type'=>'object','properties'=>['conversation_id'=>['type'=>'string']],'required'=>['conversation_id']]),
            new ConnectorAction('draft','Create an Outlook draft.',MailActionPolicy::risk('draft'),self::messageParameters()),
            new ConnectorAction('send','Send an email through Outlook. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('send'),self::messageParameters()),
            new ConnectorAction('reply','Reply to an Outlook message. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('reply'),self::messageParameters(true)),
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
        $this->action($action); $request=$this->request($connection);
        return match($action) {
            'account'=>ConnectorResult::success($request->get('https://graph.microsoft.com/v1.0/me')->throw()->json()),
            'search'=>ConnectorResult::success($request->withHeaders(['ConsistencyLevel'=>'eventual'])->get('https://graph.microsoft.com/v1.0/me/messages', ['$search'=>'"'.str_replace('"','',(string)($arguments['query']??'')).'"','$top'=>max(1,min(100,(int)($arguments['limit']??20))),'$select'=>'id,subject,from,toRecipients,receivedDateTime,isRead,conversationId'])->throw()->json()),
            'thread'=>ConnectorResult::success($request->get('https://graph.microsoft.com/v1.0/me/messages', ['$filter'=>"conversationId eq '" . str_replace("'","''",(string)($arguments['conversation_id']??'')) . "'",'$top'=>100,'$select'=>'id,subject,from,toRecipients,receivedDateTime,bodyPreview,conversationId'])->throw()->json()),
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
        $id=rawurlencode((string)($arguments['message_id']??'')); if($id==='')throw new \InvalidArgumentException('message_id is required for Outlook replies.');
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

    private static function messageParameters(bool $reply=false):array
    {
        if($reply)return ['type'=>'object','properties'=>['message_id'=>['type'=>'string'],'body'=>['type'=>'string']],'required'=>['message_id','body']];
        return ['type'=>'object','properties'=>['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean']],'required'=>['to','subject','body']];
    }
}
