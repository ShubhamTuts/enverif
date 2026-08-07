<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{ImapMailbox, MailActionPolicy, MailMessageFactory};
use App\Models\ConnectorConnection;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

final class SmtpConnector extends AbstractConnector
{
    public function id(): string { return 'smtp'; }
    public function label(): string { return 'SMTP / IMAP Mail'; }

    /** @return array<int,ConnectorAction> */
    public function actions(): array
    {
        return [
            new ConnectorAction('account','Return the configured mail sender and mailbox identity.',MailActionPolicy::risk('account'),[],['mail.account.read']),
            new ConnectorAction('search','Search the configured IMAP mailbox using bounded normalized filters.',MailActionPolicy::risk('search'),self::searchParameters(),['mail.search']),
            new ConnectorAction('recent','List recent messages from the configured IMAP mailbox.',MailActionPolicy::risk('search'),['type'=>'object','properties'=>['limit'=>['type'=>'integer']]],['mail.search','mail.receive']),
            new ConnectorAction('receive','Check the configured IMAP mailbox for unread/new messages.',MailActionPolicy::risk('search'),['type'=>'object','properties'=>['limit'=>['type'=>'integer']]],['mail.receive','mail.search']),
            new ConnectorAction('read','Read one IMAP message by UID as normalized mail data.',MailActionPolicy::risk('thread'),['type'=>'object','properties'=>['message_uid'=>['type'=>'integer']],'required'=>['message_uid']],['mail.message.read']),
            new ConnectorAction('thread','Reconstruct a bounded IMAP conversation around one message UID.',MailActionPolicy::risk('thread'),['type'=>'object','properties'=>['message_uid'=>['type'=>'integer']],'required'=>['message_uid']],['mail.thread.read','mail.message.read']),
            new ConnectorAction('send','Send an email through SMTP. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('send'),self::messageParameters(),['mail.send']),
            new ConnectorAction('reply','Send a threaded reply through SMTP. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('reply'),self::messageParameters(true),['mail.reply','mail.send']),
        ];
    }

    /** @return array<int,ConnectorAction> */
    public function actionsForConnection(ConnectorConnection $connection): array
    {
        $imapConfigured = trim((string) data_get($connection->configuration, 'imap_host', '')) !== '';
        $imapActions = ['search','recent','receive','read','thread'];
        return array_values(array_filter($this->actions(), fn (ConnectorAction $action): bool => $imapConfigured || !in_array($action->name, $imapActions, true)));
    }

    public function configurationSchema(): array
    {
        return ['credentials'=>[
            'username'=>['label'=>'SMTP username','secret'=>false,'required'=>true],
            'password'=>['label'=>'SMTP password','secret'=>true,'required'=>true],
            'imap_username'=>['label'=>'IMAP username (optional)','secret'=>false,'required'=>false,'help'=>'Leave blank to reuse the SMTP username.'],
            'imap_password'=>['label'=>'IMAP password (optional)','secret'=>true,'required'=>false,'help'=>'Leave blank to reuse the SMTP password.'],
        ],'fields'=>[
            'host'=>['label'=>'SMTP host','required'=>true],
            'port'=>['label'=>'SMTP port','type'=>'number','required'=>true,'default'=>'587'],
            'encryption'=>['label'=>'SMTP encryption','type'=>'select','options'=>['tls','ssl','none'],'default'=>'tls'],
            'from_email'=>['label'=>'From email','required'=>true],
            'from_name'=>['label'=>'From name','required'=>false,'default'=>'Enverif'],
            'imap_host'=>['label'=>'IMAP host (optional)','required'=>false,'help'=>'Add IMAP to let this connection search, read and receive mail.'],
            'imap_port'=>['label'=>'IMAP port','type'=>'number','required'=>false,'default'=>'993'],
            'imap_encryption'=>['label'=>'IMAP encryption','type'=>'select','options'=>['ssl','tls','none'],'default'=>'ssl'],
            'imap_mailbox'=>['label'=>'Default IMAP mailbox','required'=>false,'default'=>'INBOX'],
            'imap_verify_peer'=>['label'=>'IMAP certificate verification','type'=>'select','options'=>['verify','noverify'],'default'=>'verify','help'=>'Keep certificate verification enabled in production.'],
        ]];
    }

    public function test(ConnectorConnection $connection): bool
    {
        $diagnostics = $this->diagnostics($connection);
        return (bool) data_get($diagnostics, 'smtp.ok', false)
            && (!data_get($diagnostics, 'imap.configured', false) || (bool) data_get($diagnostics, 'imap.ok', false));
    }

    /** @return array<string,mixed> */
    public function diagnostics(ConnectorConnection $connection): array
    {
        $smtp = ['ok'=>false,'message'=>null];
        try {
            $transport = $this->transport($connection);
            if (method_exists($transport, 'start')) $transport->start();
            if (method_exists($transport, 'stop')) $transport->stop();
            $smtp['ok'] = true;
        } catch (\Throwable $e) {
            $smtp['message'] = $this->safeDiagnostic($e->getMessage());
        }

        $mailbox = new ImapMailbox($connection);
        $imap = [
            'configured' => $mailbox->configured(),
            'runtime_available' => $mailbox->runtimeAvailable(),
            'ok' => null,
            'message' => null,
        ];
        if ($mailbox->configured()) {
            try {
                $imap['ok'] = $mailbox->test();
            } catch (\Throwable $e) {
                $imap['ok'] = false;
                $imap['message'] = $this->safeDiagnostic($e->getMessage());
            }
        }

        return ['smtp'=>$smtp,'imap'=>$imap];
    }

    public function execute(ConnectorConnection $connection,string $action,array $arguments):ConnectorResult
    {
        $this->action($action);
        if(!collect($this->actionsForConnection($connection))->contains(fn(ConnectorAction $item)=>$item->name===$action)){
            return ConnectorResult::failure('This mail capability is not configured on the connection.');
        }
        if($action==='account')return ConnectorResult::success([
            'email'=>(string)data_get($connection->configuration,'from_email'),
            'name'=>(string)data_get($connection->configuration,'from_name'),
            'imap_configured'=>trim((string)data_get($connection->configuration,'imap_host',''))!=='',
        ]);

        if(in_array($action,['search','recent','receive','read','thread'],true)){
            $imap=new ImapMailbox($connection);
            return match($action){
                'search'=>ConnectorResult::success($imap->search($arguments)),
                'recent'=>ConnectorResult::success($imap->search(['limit'=>max(1,min(50,(int)($arguments['limit']??20)))])),
                'receive'=>ConnectorResult::success($imap->search(['unread'=>true,'limit'=>max(1,min(50,(int)($arguments['limit']??20)))])),
                'read'=>ConnectorResult::success($imap->read((int)($arguments['message_uid']??0))),
                'thread'=>ConnectorResult::success($imap->thread((int)($arguments['message_uid']??0))),
            };
        }

        $email=MailMessageFactory::create($arguments,(string)data_get($connection->configuration,'from_email'),(string)data_get($connection->configuration,'from_name'));
        (new Mailer($this->transport($connection)))->send($email);
        return ConnectorResult::success(['sent'=>true,'message_id'=>$email->getHeaders()->get('Message-ID')?->getBodyAsString()]);
    }

    private function transport(ConnectorConnection $connection)
    {
        $host=(string)data_get($connection->configuration,'host');
        $port=(int)data_get($connection->configuration,'port',587);
        $enc=(string)data_get($connection->configuration,'encryption','tls');
        $user=$connection->credential('username')??'';
        $pass=$connection->credential('password')??'';
        if($host===''||$user===''||$pass==='')throw new \RuntimeException('SMTP host and credentials are required.');
        $scheme=$enc==='ssl'?'smtps':'smtp';
        $query=match($enc){'none'=>'?auto_tls=false','tls'=>'?require_tls=true',default=>''};
        return Transport::fromDsn(sprintf('%s://%s:%s@%s:%d%s',$scheme,rawurlencode($user),rawurlencode($pass),$host,$port,$query));
    }

    /** @return array<string,mixed> */
    private static function searchParameters(): array
    {
        return ['type'=>'object','properties'=>[
            'query'=>['type'=>'string'],
            'from'=>['type'=>'string'],
            'to'=>['type'=>'string'],
            'subject'=>['type'=>'string'],
            'unread'=>['type'=>'boolean'],
            'since'=>['type'=>'string'],
            'before'=>['type'=>'string'],
            'limit'=>['type'=>'integer'],
        ]];
    }

    private static function messageParameters(bool $reply=false):array
    {
        $props=['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean'],'reply_to'=>['type'=>'string']];
        if($reply){
            $props['message_id']=['type'=>'string'];
            $props['in_reply_to']=['type'=>'string'];
            $props['references']=['type'=>'string'];
        }
        return ['type'=>'object','properties'=>$props,'required'=>['to','subject','body']];
    }

    private function safeDiagnostic(string $message): string
    {
        $message=preg_replace('/(?:password|pass|token|secret|key)=?[^\s]*/i','$1=[redacted]',$message)??$message;
        return mb_substr($message,0,500);
    }
}
