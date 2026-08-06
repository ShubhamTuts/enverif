<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest, ModelResponse, ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Core\Models\StrictFunctionNameMapper;
use App\Core\Models\ToolSchemaNormalizer;
use App\Models\ModelConnection;

/**
 * Anthropic Messages API.
 *
 * Suggested models track current Claude API IDs; custom IDs remain supported.
 *
 * @see https://platform.claude.com/docs/en/about-claude/models/overview
 */
final class AnthropicProvider extends AbstractHttpProvider
{
    public function id(): string
    {
        return 'anthropic';
    }

    public function models(): array
    {
        return [
            'claude-opus-5',
            'claude-sonnet-5',
            'claude-haiku-4-5',
            'claude-opus-4-8',
            'claude-sonnet-4-6',
            'claude-opus-4-5',
            'claude-sonnet-4-5',
            'claude-fable-5',
        ];
    }

    public function complete(ModelConnection $connection, ModelRequest $request): ModelResponse
    {
        $base = rtrim($connection->base_url ?: 'https://api.anthropic.com', '/');
        $mapper = new StrictFunctionNameMapper;
        $tools = $mapper->sanitizeTools(ToolSchemaNormalizer::tools($request->tools));
        $messages = $mapper->sanitizeAnthropicMessages(MessageNormalizer::anthropic($request->messages));
        $blocks = [];

        foreach ($request->attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $text = AttachmentPayload::text($attachment);
            if ($text !== null) {
                $blocks[] = [
                    'type' => 'text',
                    'text' => 'Attachment '.AttachmentPayload::label($attachment).":\n".$text,
                ];
                continue;
            }
            if (AttachmentPayload::isImage($attachment)) {
                $data = AttachmentPayload::base64($attachment);
                $mime = (string) ($attachment['mime_type'] ?? 'image/png');
                if ($data !== null) {
                    $blocks[] = [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mime,
                            'data' => $data,
                        ],
                    ];
                }
            }
        }
        if ($blocks) {
            $messages[] = ['role' => 'user', 'content' => $blocks];
        }

        $payload = [
            'model' => $request->model,
            'system' => $request->system,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens,
        ];

        // Effort is supported on current Opus/Sonnet generations.
        if ($this->supportsEffort($request->model)) {
            $payload['output_config'] = [
                'effort' => match ($request->effort) {
                    'fast' => 'low',
                    'deep' => 'high',
                    default => 'high',
                },
            ];
        }

        if ($tools) {
            $payload['tools'] = array_map(
                fn ($t) => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'input_schema' => ToolSchemaNormalizer::parameters($t['parameters'] ?? null),
                ],
                $tools,
            );
        }

        $res = $this->client($connection, [
            'x-api-key' => $this->apiKey($connection),
            'anthropic-version' => '2023-06-01',
        ])->post($base.'/v1/messages', $payload)->throw()->json();

        $text = '';
        $calls = [];
        foreach (($res['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
            if (($block['type'] ?? '') === 'tool_use') {
                $calls[] = new ToolCall(
                    (string) ($block['id'] ?? uniqid('tool_', true)),
                    $mapper->original((string) ($block['name'] ?? '')),
                    is_array($block['input'] ?? null) ? $block['input'] : [],
                );
            }
        }

        return new ModelResponse(
            $text,
            $calls,
            (int) ($res['usage']['input_tokens'] ?? 0),
            (int) ($res['usage']['output_tokens'] ?? 0),
            (string) ($res['stop_reason'] ?? ''),
        );
    }

    private function supportsEffort(string $model): bool
    {
        // Effort is documented for current Opus/Sonnet/Fable generations.
        // Skip older dated snapshots that reject unknown output_config fields.
        return (bool) preg_match(
            '/^claude-(fable-5|mythos-5|opus-5|sonnet-5|opus-4-[6-8]|sonnet-4-6)(-|$)/',
            $model,
        );
    }
}
