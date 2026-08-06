<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Models\ConnectorConnection;

final class SlackConnector extends AbstractConnector
{
    public function id(): string
    {
        return 'slack';
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function actions(): array
    {
        return [
            new ConnectorAction(
                'auth_test',
                'Validate the Slack bot token and return workspace identity.',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => new \stdClass],
            ),
            new ConnectorAction(
                'conversations_list',
                'List Slack channels the bot can see.',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'types' => ['type' => 'string', 'description' => 'Comma-separated: public_channel,private_channel,mpim,im'],
                    'limit' => ['type' => 'integer'],
                    'cursor' => ['type' => 'string'],
                ]],
            ),
            new ConnectorAction(
                'conversations_history',
                'Read recent messages from a Slack channel (bot must be a member).',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'channel' => ['type' => 'string'],
                    'limit' => ['type' => 'integer'],
                    'cursor' => ['type' => 'string'],
                ], 'required' => ['channel']],
            ),
            new ConnectorAction(
                'chat_post_message',
                'Post a message to a Slack channel as the bot. Requires approval by default (external_write).',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'channel' => ['type' => 'string'],
                    'text' => ['type' => 'string'],
                    'thread_ts' => ['type' => 'string'],
                ], 'required' => ['channel', 'text']],
            ),
            new ConnectorAction(
                'chat_update',
                'Update an existing Slack message posted by the bot.',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'channel' => ['type' => 'string'],
                    'ts' => ['type' => 'string'],
                    'text' => ['type' => 'string'],
                ], 'required' => ['channel', 'ts', 'text']],
            ),
            new ConnectorAction(
                'users_list',
                'List users in the Slack workspace.',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => [
                    'limit' => ['type' => 'integer'],
                    'cursor' => ['type' => 'string'],
                ]],
            ),
        ];
    }

    public function configurationSchema(): array
    {
        return [
            'credentials' => [
                'bot_token' => [
                    'type' => 'password',
                    'required' => true,
                    'label' => 'Bot token (xoxb-…)',
                    'help' => 'From Slack App → OAuth & Permissions. Needs chat:write, channels:read, and usually channels:history / users:read.',
                ],
            ],
            'fields' => [
                'default_channel' => [
                    'type' => 'text',
                    'required' => false,
                    'label' => 'Default channel ID',
                    'help' => 'Optional C… / G… channel used when agents omit channel.',
                ],
            ],
        ];
    }

    public function test(ConnectorConnection $c): bool
    {
        try {
            $res = $this->api($c, 'auth.test', []);
            return (bool) ($res['ok'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function execute(ConnectorConnection $c, string $action, array $a): ConnectorResult
    {
        $this->action($action);
        $defaultChannel = (string) data_get($c->configuration, 'default_channel', '');

        return match ($action) {
            'auth_test' => ConnectorResult::success($this->api($c, 'auth.test', [])),
            'conversations_list' => ConnectorResult::success($this->api($c, 'conversations.list', array_filter([
                'types' => $a['types'] ?? 'public_channel,private_channel',
                'limit' => min(200, max(1, (int) ($a['limit'] ?? 100))),
                'cursor' => $a['cursor'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''))),
            'conversations_history' => ConnectorResult::success($this->api($c, 'conversations.history', array_filter([
                'channel' => (string) ($a['channel'] ?? $defaultChannel),
                'limit' => min(200, max(1, (int) ($a['limit'] ?? 50))),
                'cursor' => $a['cursor'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''))),
            'chat_post_message' => ConnectorResult::success($this->api($c, 'chat.postMessage', array_filter([
                'channel' => (string) ($a['channel'] ?? $defaultChannel),
                'text' => (string) ($a['text'] ?? ''),
                'thread_ts' => $a['thread_ts'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''))),
            'chat_update' => ConnectorResult::success($this->api($c, 'chat.update', [
                'channel' => (string) ($a['channel'] ?? $defaultChannel),
                'ts' => (string) ($a['ts'] ?? ''),
                'text' => (string) ($a['text'] ?? ''),
            ])),
            'users_list' => ConnectorResult::success($this->api($c, 'users.list', array_filter([
                'limit' => min(200, max(1, (int) ($a['limit'] ?? 100))),
                'cursor' => $a['cursor'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''))),
            default => ConnectorResult::failure('Unsupported Slack action'),
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function api(ConnectorConnection $c, string $method, array $payload): array
    {
        $token = $c->credential('bot_token') ?: $c->credential('api_key');
        if (! $token) {
            throw new \RuntimeException('Missing Slack bot_token credential.');
        }

        $res = $this->client(['Authorization' => 'Bearer '.$token])
            ->post('https://slack.com/api/'.$method, $payload)
            ->throw()
            ->json();

        if (! ($res['ok'] ?? false)) {
            throw new \RuntimeException('Slack API error: '.((string) ($res['error'] ?? 'unknown_error')));
        }

        return is_array($res) ? $res : [];
    }
}
