<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{MailActionPolicy, MailMessageFactory, OAuthTokenService};
use App\Models\ConnectorConnection;

final class GmailConnector extends AbstractConnector
{
    public function __construct(private readonly OAuthTokenService $tokens) {}
    public function id(): string { return 'gmail'; }
    public function label(): string { return 'Gmail'; }

    public function actions(): array
    {
        return [
            new ConnectorAction('account', 'Get the connected Gmail account profile.', MailActionPolicy::risk('account')),
            new ConnectorAction('search', 'Search Gmail messages using Gmail search syntax.', MailActionPolicy::risk('search'), ['type'=>'object','properties'=>['query'=>['type'=>'string'],'limit'=>['type'=>'integer']]]),
            new ConnectorAction('thread', 'Read a Gmail thread by thread ID.', MailActionPolicy::risk('thread'), ['type'=>'object','properties'=>['thread_id'=>['type'=>'string']],'required'=>['thread_id']]),
            new ConnectorAction('draft', 'Create an email draft in Gmail.', MailActionPolicy::risk('draft'), self::messageParameters()),
            new ConnectorAction('send', 'Send an email through Gmail. Requires approval unless autonomous external writes are enabled.', MailActionPolicy::risk('send'), self::messageParameters()),
            new ConnectorAction('reply', 'Reply inside a Gmail thread. Requires approval unless autonomous external writes are enabled.', MailActionPolicy::risk('reply'), self::messageParameters(true)),
        ];
    }

    public function configurationSchema(): array
    {
        return [
            'credentials' => ['client_secret' => ['label'=>'Google OAuth client secret','secret'=>true,'required'=>true,'help'=>'Stored encrypted. Create a Web application OAuth client in Google Cloud.']],
            'fields' => ['client_id' => ['label'=>'Google OAuth client ID','required'=>true], 'from_name'=>['label'=>'Default sender name','required'=>false]],
        ];
    }

    public function test(ConnectorConnection $connection): bool
    {
        try { return $this->request($connection)->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')->successful(); } catch (\Throwable) { return false; }
    }

    public function execute(ConnectorConnection $connection, string $action, array $arguments): ConnectorResult
    {
        $this->action($action);
        $request = $this->request($connection);
        return match ($action) {
            'account' => ConnectorResult::success($request->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')->throw()->json()),
            'search' => ConnectorResult::success($request->get('https://gmail.googleapis.com/gmail/v1/users/me/messages', ['q'=>(string)($arguments['query']??''),'maxResults'=>max(1,min(100,(int)($arguments['limit']??20)))])->throw()->json()),
            'thread' => ConnectorResult::success($request->get('https://gmail.googleapis.com/gmail/v1/users/me/threads/' . rawurlencode((string)($arguments['thread_id']??'')), ['format'=>'full'])->throw()->json()),
            'draft' => ConnectorResult::success($request->post('https://gmail.googleapis.com/gmail/v1/users/me/drafts', ['message'=>['raw'=>$this->raw($connection,$arguments)]])->throw()->json()),
            'send' => ConnectorResult::success($request->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw'=>$this->raw($connection,$arguments)])->throw()->json()),
            'reply' => ConnectorResult::success($request->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', array_filter(['raw'=>$this->raw($connection,$arguments),'threadId'=>(string)($arguments['thread_id']??'')]))->throw()->json()),
            default => ConnectorResult::failure('Unsupported action'),
        };
    }

    private function request(ConnectorConnection $connection)
    {
        return $this->client(['Authorization'=>'Bearer ' . $this->tokens->accessToken($connection)]);
    }

    private function raw(ConnectorConnection $connection, array $arguments): string
    {
        $profile = $this->request($connection)->get('https://gmail.googleapis.com/gmail/v1/users/me/profile')->throw()->json();
        $email = MailMessageFactory::create($arguments, (string)($profile['emailAddress']??''), (string)data_get($connection->configuration,'from_name',''));
        return MailMessageFactory::base64Url($email->toString());
    }

    private static function messageParameters(bool $reply = false): array
    {
        $properties=['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean'],'reply_to'=>['type'=>'string']];
        if ($reply) {$properties['thread_id']=['type'=>'string'];$properties['message_id']=['type'=>'string'];}
        return ['type'=>'object','properties'=>$properties,'required'=>$reply?['to','subject','body','thread_id']:['to','subject','body']];
    }
}
