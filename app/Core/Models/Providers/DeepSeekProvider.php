<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest, ModelResponse, ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Core\Models\StrictFunctionNameMapper;
use App\Core\Models\ToolSchemaNormalizer;
use App\Models\ModelConnection;

/**
 * DeepSeek Chat Completions API (OpenAI-compatible).
 *
 * Current documented model IDs (2026-07+): deepseek-v4-flash, deepseek-v4-pro.
 * Legacy aliases are remapped so existing workspaces keep working.
 *
 * @see https://api-docs.deepseek.com/
 */
final class DeepSeekProvider extends AbstractHttpProvider
{
    public function id(): string
    {
        return 'deepseek';
    }

    public function models(): array
    {
        return [
            'deepseek-v4-flash',
            'deepseek-v4-pro',
        ];
    }

    public function complete(ModelConnection $connection, ModelRequest $request): ModelResponse
    {
        $base = rtrim($connection->base_url ?: 'https://api.deepseek.com', '/');
        $model = $this->resolveModelId($request->model);
        $mapper = new StrictFunctionNameMapper;
        $tools = $mapper->sanitizeTools(ToolSchemaNormalizer::tools($request->tools));
        $messages = array_merge(
            [['role' => 'system', 'content' => $request->system]],
            $mapper->sanitizeOpenAiMessages(MessageNormalizer::openAi($request->messages)),
        );

        $attachmentText = [];
        foreach ($request->attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $text = AttachmentPayload::text($attachment);
            $label = AttachmentPayload::label($attachment);
            $attachmentText[] = $text !== null
                ? "Attachment {$label}:\n{$text}"
                : "Attachment {$label} is not text-readable by this provider; use its filename/type as context only.";
        }
        if ($attachmentText) {
            $messages[] = ['role' => 'user', 'content' => implode("\n\n", $attachmentText)];
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens,
        ];

        // V4 models support reasoning effort (flash: low/medium/high; pro: high/max).
        if (str_starts_with($model, 'deepseek-v4-')) {
            $payload['reasoning_effort'] = match ($request->effort) {
                'fast' => 'low',
                'deep' => $model === 'deepseek-v4-pro' ? 'max' : 'high',
                default => 'high',
            };
        }

        if ($tools) {
            $payload['tools'] = array_map(
                fn ($t) => [
                    'type' => 'function',
                    'function' => [
                        'name' => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters' => ToolSchemaNormalizer::parameters($t['parameters'] ?? null),
                    ],
                ],
                $tools,
            );
        }

        $res = $this->client($connection, ['Authorization' => 'Bearer '.$this->apiKey($connection)])
            ->post($base.'/chat/completions', $payload)
            ->throw()
            ->json();

        $msg = $res['choices'][0]['message'] ?? [];
        $calls = [];
        foreach (($msg['tool_calls'] ?? []) as $call) {
            $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
            $calls[] = new ToolCall(
                (string) ($call['id'] ?? uniqid('tool_', true)),
                $mapper->original((string) ($call['function']['name'] ?? '')),
                is_array($args) ? $args : [],
            );
        }

        return new ModelResponse(
            (string) ($msg['content'] ?? ''),
            $calls,
            (int) ($res['usage']['prompt_tokens'] ?? 0),
            (int) ($res['usage']['completion_tokens'] ?? 0),
            (string) ($res['choices'][0]['finish_reason'] ?? ''),
        );
    }

    /** Map retired / unofficial IDs onto current DeepSeek API models. */
    private function resolveModelId(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return $this->models()[0];
        }

        return match ($model) {
            'deepseek-chat',
            'deepseek-coder',
            'deepseek-v3',
            'deepseek-v2.5',
            'deepseek-v2',
            'deepseek-v3.2' => 'deepseek-v4-flash',
            'deepseek-reasoner',
            'deepseek-r1',
            'deepseek-reasoner-r1' => 'deepseek-v4-pro',
            default => $model,
        };
    }
}
