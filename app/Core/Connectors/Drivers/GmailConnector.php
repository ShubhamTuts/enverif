<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Core\Email\{MailActionPolicy, MailMessageFactory, OAuthTokenService};
use App\Models\ConnectorConnection;
use Illuminate\Support\Str;

final class GmailConnector extends AbstractConnector
{
    public function __construct(private readonly OAuthTokenService $tokens) {}
    public function id(): string { return 'gmail'; }
    public function label(): string { return 'Gmail'; }

    public function actions(): array
    {
        return [
            new ConnectorAction('account', 'Get the connected Gmail account profile.', MailActionPolicy::risk('account')),
            new ConnectorAction('search', 'Search Gmail messages using Gmail search syntax. Returns message and thread IDs; read a relevant thread before deciding whether a person replied.', MailActionPolicy::risk('search'), ['type'=>'object','properties'=>['query'=>['type'=>'string'],'limit'=>['type'=>'integer']]]),
            new ConnectorAction('thread', 'Read a Gmail thread as normalized message metadata and readable bodies.', MailActionPolicy::risk('thread'), ['type'=>'object','properties'=>['thread_id'=>['type'=>'string']],'required'=>['thread_id']]),
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
            'thread' => ConnectorResult::success($this->normalizeThread((array) $request->get('https://gmail.googleapis.com/gmail/v1/users/me/threads/' . rawurlencode((string)($arguments['thread_id']??'')), ['format'=>'full'])->throw()->json())),
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

    /** @param array<string,mixed> $thread @return array<string,mixed> */
    private function normalizeThread(array $thread): array
    {
        $messages = [];
        foreach (array_slice((array) ($thread['messages'] ?? []), -100) as $message) {
            if (! is_array($message)) continue;
            $payload = is_array($message['payload'] ?? null) ? $message['payload'] : [];
            $headers = [];
            foreach ((array) ($payload['headers'] ?? []) as $header) {
                if (! is_array($header)) continue;
                $key = strtolower(trim((string) ($header['name'] ?? '')));
                if ($key !== '') $headers[$key] = trim((string) ($header['value'] ?? ''));
            }
            $messages[] = [
                'id' => (string) ($message['id'] ?? ''),
                'thread_id' => (string) ($message['threadId'] ?? $thread['id'] ?? ''),
                'from' => $headers['from'] ?? '',
                'to' => $headers['to'] ?? '',
                'cc' => $headers['cc'] ?? '',
                'subject' => $headers['subject'] ?? '',
                'date' => $headers['date'] ?? '',
                'message_id' => $headers['message-id'] ?? '',
                'in_reply_to' => $headers['in-reply-to'] ?? '',
                'snippet' => Str::limit(trim((string) ($message['snippet'] ?? '')), 1000, '…'),
                'body' => Str::limit($this->bodyText($payload), 20000, '…'),
                'internal_date' => isset($message['internalDate']) ? (string) $message['internalDate'] : null,
            ];
        }

        return [
            'thread_id' => (string) ($thread['id'] ?? ''),
            'history_id' => isset($thread['historyId']) ? (string) $thread['historyId'] : null,
            'message_count' => count($messages),
            'messages' => $messages,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function bodyText(array $payload): string
    {
        $plain = [];
        $html = [];
        $this->collectBodies($payload, $plain, $html);
        $parts = $plain !== [] ? $plain : array_map(
            static fn (string $value): string => trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $html,
        );
        $parts = array_values(array_filter(array_map('trim', $parts), fn (string $value): bool => $value !== ''));
        return trim(implode("\n\n", $parts));
    }

    /** @param array<string,mixed> $payload @param list<string> $plain @param list<string> $html */
    private function collectBodies(array $payload, array &$plain, array &$html): void
    {
        $mime = strtolower((string) ($payload['mimeType'] ?? ''));
        $data = data_get($payload, 'body.data');
        if (is_string($data) && $data !== '') {
            $decoded = $this->decodeBody($data);
            if ($decoded !== '') {
                if ($mime === 'text/plain' || ($mime === '' && ($payload['parts'] ?? []) === [])) $plain[] = $decoded;
                elseif ($mime === 'text/html') $html[] = $decoded;
            }
        }
        foreach ((array) ($payload['parts'] ?? []) as $part) {
            if (is_array($part)) $this->collectBodies($part, $plain, $html);
        }
    }

    private function decodeBody(string $data): string
    {
        $value = strtr($data, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding !== 0) $value .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : trim($decoded);
    }

    private static function messageParameters(bool $reply = false): array
    {
        $properties=['to'=>['type'=>'string'],'subject'=>['type'=>'string'],'body'=>['type'=>'string'],'html'=>['type'=>'boolean'],'reply_to'=>['type'=>'string']];
        if ($reply) {$properties['thread_id']=['type'=>'string'];$properties['message_id']=['type'=>'string'];}
        return ['type'=>'object','properties'=>$properties,'required'=>$reply?['to','subject','body','thread_id']:['to','subject','body']];
    }
}
