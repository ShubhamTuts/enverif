<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\{ConnectorAction, ConnectorResult};
use App\Models\ConnectorConnection;

/**
 * Buffer GraphQL API (https://api.buffer.com) for social scheduling.
 *
 * @see https://developers.buffer.com/
 */
final class BufferConnector extends AbstractConnector
{
    public function id(): string
    {
        return 'buffer';
    }

    public function label(): string
    {
        return 'Buffer';
    }

    public function actions(): array
    {
        return [
            new ConnectorAction(
                'list_channels',
                'List Buffer channels available to the authenticated account.',
                RiskLevel::Read,
                ['type' => 'object', 'properties' => new \stdClass],
            ),
            new ConnectorAction(
                'create_draft',
                'Create a draft post in Buffer (saveToDraft). Prefer for agent previews before scheduling.',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'text' => ['type' => 'string'],
                    'channel_id' => ['type' => 'string'],
                    'mode' => ['type' => 'string', 'description' => 'addToQueue or customScheduled'],
                ], 'required' => ['text']],
            ),
            new ConnectorAction(
                'queue_post',
                'Add a post to the Buffer queue (automatic scheduling).',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'text' => ['type' => 'string'],
                    'channel_id' => ['type' => 'string'],
                ], 'required' => ['text']],
            ),
            new ConnectorAction(
                'schedule_post',
                'Schedule a post for a specific UTC time (ISO-8601 dueAt).',
                RiskLevel::ExternalWrite,
                ['type' => 'object', 'properties' => [
                    'text' => ['type' => 'string'],
                    'channel_id' => ['type' => 'string'],
                    'due_at' => ['type' => 'string', 'description' => 'ISO-8601 UTC datetime'],
                ], 'required' => ['text', 'due_at']],
            ),
        ];
    }

    public function configurationSchema(): array
    {
        return [
            'credentials' => [
                'api_key' => [
                    'type' => 'password',
                    'required' => true,
                    'label' => 'Buffer API key',
                    'help' => 'Create at publish.buffer.com/settings/api. Sent as Authorization: Bearer.',
                ],
            ],
            'fields' => [
                'default_channel_id' => [
                    'type' => 'text',
                    'required' => false,
                    'label' => 'Default channel ID',
                    'help' => 'Used when agents omit channel_id.',
                ],
            ],
        ];
    }

    public function test(ConnectorConnection $c): bool
    {
        try {
            $res = $this->graphql($c, 'query { account { email } }');
            return isset($res['data']['account']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function execute(ConnectorConnection $c, string $action, array $a): ConnectorResult
    {
        $this->action($action);
        $channel = (string) ($a['channel_id'] ?? data_get($c->configuration, 'default_channel_id', ''));

        return match ($action) {
            'list_channels' => ConnectorResult::success($this->graphql($c, <<<'GQL'
query ListChannels {
  account {
    organizations {
      id
      name
      channels {
        id
        name
        service
      }
    }
  }
}
GQL)),
            'create_draft' => $this->createPost($c, (string) ($a['text'] ?? ''), $channel, 'addToQueue', true, null),
            'queue_post' => $this->createPost($c, (string) ($a['text'] ?? ''), $channel, 'addToQueue', false, null),
            'schedule_post' => $this->createPost($c, (string) ($a['text'] ?? ''), $channel, 'customScheduled', false, (string) ($a['due_at'] ?? '')),
            default => ConnectorResult::failure('Unsupported Buffer action'),
        };
    }

    private function createPost(
        ConnectorConnection $c,
        string $text,
        string $channelId,
        string $mode,
        bool $draft,
        ?string $dueAt,
    ): ConnectorResult {
        if ($text === '' || $channelId === '') {
            return ConnectorResult::failure('Buffer post requires text and channel_id (or a default channel on the connection).');
        }

        $dueLine = $dueAt ? ', dueAt: '.json_encode($dueAt) : '';
        $draftLine = $draft ? ', saveToDraft: true' : '';
        $query = <<<GQL
mutation CreateDraftPost {
  createPost(input: {
    text: {$this->gqlString($text)},
    channelId: {$this->gqlString($channelId)},
    schedulingType: automatic,
    mode: {$mode}{$draftLine}{$dueLine}
  }) {
    ... on PostActionSuccess {
      post { id text dueAt }
    }
    ... on MutationError {
      message
    }
  }
}
GQL;

        $res = $this->graphql($c, $query);
        $payload = $res['data']['createPost'] ?? null;
        if (is_array($payload) && isset($payload['message']) && ! isset($payload['post'])) {
            return ConnectorResult::failure('Buffer mutation error: '.(string) $payload['message']);
        }

        return ConnectorResult::success(is_array($payload) ? $payload : $res);
    }

    /** @return array<string, mixed> */
    private function graphql(ConnectorConnection $c, string $query): array
    {
        $res = $this->client(['Authorization' => 'Bearer '.$this->bearer($c)])
            ->post('https://api.buffer.com', ['query' => $query])
            ->throw()
            ->json();

        if (! empty($res['errors']) && is_array($res['errors'])) {
            $msg = (string) ($res['errors'][0]['message'] ?? 'GraphQL error');
            throw new \RuntimeException('Buffer API error: '.$msg);
        }

        return is_array($res) ? $res : [];
    }

    private function gqlString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
    }
}
