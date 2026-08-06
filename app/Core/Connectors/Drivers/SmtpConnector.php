<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{MailActionPolicy, MailMessageFactory};
use App\Models\ConnectorConnection;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

final class SmtpConnector extends AbstractConnector
{
    public function id(): string { return 'smtp'; }
    public function label(): string { return 'SMTP'; }
    public function actions(): array
    {
        return [
            new ConnectorAction('account','Return the configured SMTP sender identity.',MailActionPolicy::risk('account')),
            new ConnectorAction('send','Send an email through SMTP. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('send'),self::messageParameters()),
            new ConnectorAction('reply','Send a reply through SMTP. Requires approval unless autonomous external writes are enabled.',MailActionPolicy::risk('reply'),self::messageParameters(true)),
        ];
    }
    public function configurationSchema(): array
    {
        return ['credentials'=>[
            'username'=>['label'=>'SMTP username','secret'=>false,'required'=>true],
            'password'=>['label'=>'SMTP password','secret'=>true,'required'=>true],
        ],'fields'=>[
            'host'=>['label'=>'SMTP host','required'=>true],
            'port'=>['label'=>'SMTP port','type'=>'number','required'=>true,'default'=>'587'],
            'encryption'=>['label'=>'Encryption','type'=>'select','options'=>['tls','ssl','none'],'default'=>'tls'],
            'from_email'=>['label'=>'From email','required'=>true],
            'from_name'=>['label'=>'From name','required'=>false,'default'=>'Enverif'],
        ]];
    }
    public function test(ConnectorConnection $connection): bool
    {
        try {
            $transport = $this->transport($connection);
            if (method_exists($transport, 'start')) {
                $transport->start();
            }
            if (method_exists($transport, 'stop')) {
                $transport->stop();
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    public function execute(ConnectorConnection $connection,string $action,array $arguments):ConnectorResult
    {
        $this->action($action);
        if($action==='account')return ConnectorResult::success(['email'=>(string)data_get($connection->configuration,'from_email'),'name'=>(string)data_get($connection->configuration,'from_name')]);
        $email=MailMessageFactory::create($arguments,(string)data_get($connection->configuration,'from_email'),(string)data_get($connection->configuration,'from_name'));
        (new Mailer($this->transport($connection)))->send($email);
        return ConnectorResult::success(['sent'=>true,'message_id'=>$email->getHeaders()->get('Message-ID')?->getBodyAsString()]);
    }
    private function transport(ConnectorConnection $connection)
    {
        $host=(string)data_get($connection->configuration,'host'); $port=(int)data_get($connection->configuration,'port',587); $enc=(string)data_get($connection->configuration,'encryption','tls');
        $user=$connection->credential('username')??''; $pass=$connection->credential('password')??''; if($host===''||$user===''||$pass==='')throw new \RuntimeException('SMTP host and credentials are required.');
        $scheme=$enc==='ssl'?'smtps':'smtp'; $query=match($enc){'none'=>'?auto_tls=false','tls'=>'?require_tls=true',default=>''};
        return Transport::fromDsn(sprintf('%s://%s:%s@%s:%d%s',$scheme,rawurlencode($user),rawurlencode($pass),$host,$port,$query));
    }
    private static function messageParameters(bool $reply=false):array
    {
        $props=['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean'],'reply_to'=>['type'=>'string']]; if($reply)$props['message_id']=['type'=>'string'];
        return ['type'=>'object','properties'=>$props,'required'=>['to','subject','body']];
    }
}
