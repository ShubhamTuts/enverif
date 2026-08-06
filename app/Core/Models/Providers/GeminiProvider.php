<?php

namespace App\Core\Models\Providers;

use App\Core\Models\AttachmentPayload;
use App\Core\Models\DTO\{ModelRequest, ModelResponse, ToolCall};
use App\Core\Models\MessageNormalizer;
use App\Models\ModelConnection;

/**
 * Google Gemini generateContent API.
 *
 * Suggested models track current Gemini API IDs; custom IDs remain supported.
 * Shut-down Gemini 2.0 / 1.5 IDs are remapped onto current Flash/Pro models.
 *
 * @see https://ai.google.dev/gemini-api/docs/models
 */
final class GeminiProvider extends AbstractHttpProvider
{
    public function id(): string
    {
        return 'gemini';
    }

    public function models(): array
    {
        return [
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.1-pro-preview',
            'gemini-3.1-flash-lite',
            'gemini-2.5-pro',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ];
    }

    public function complete(ModelConnection $connection, ModelRequest $request): ModelResponse
    {
        $base = rtrim($connection->base_url ?: 'https://generativelanguage.googleapis.com', '/');
        $model = $this->resolveModelId($request->model);
        $contents = MessageNormalizer::gemini($request->messages);
        $parts = [];

        foreach ($request->attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $text = AttachmentPayload::text($attachment);
            if ($text !== null) {
                $parts[] = ['text' => 'Attachment '.AttachmentPayload::label($attachment).":\n".$text];
                continue;
            }
            if (AttachmentPayload::isImage($attachment)) {
                $data = AttachmentPayload::base64($attachment);
                $mime = (string) ($attachment['mime_type'] ?? 'image/png');
                if ($data !== null) {
                    $parts[] = ['inlineData' => ['mimeType' => $mime, 'data' => $data]];
                }
            }
        }
        if ($parts) {
            $contents[] = ['role' => 'user', 'parts' => $parts];
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $request->system]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => $request->maxTokens],
        ];

        if ($request->tools) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(
                    fn ($t) => [
                        'name' => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => []],
                    ],
                    $request->tools,
                ),
            ]];
        }

        $url = $base.'/v1beta/models/'.rawurlencode($model).':generateContent?key='.rawurlencode($this->apiKey($connection));
        $res = $this->client($connection)->post($url, $payload)->throw()->json();

        $text = '';
        $calls = [];
        foreach (($res['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $calls[] = new ToolCall(
                    uniqid('gemini_', true),
                    (string) ($fc['name'] ?? ''),
                    is_array($fc['args'] ?? null) ? $fc['args'] : [],
                );
            }
        }

        $usage = $res['usageMetadata'] ?? [];

        return new ModelResponse(
            $text,
            $calls,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            (string) ($res['candidates'][0]['finishReason'] ?? ''),
        );
    }

    private function resolveModelId(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return $this->models()[0];
        }

        return match ($model) {
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-2.0-flash-exp',
            'gemini-1.5-flash',
            'gemini-1.5-flash-latest' => 'gemini-3.6-flash',
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-lite-001',
            'gemini-1.5-flash-8b' => 'gemini-3.1-flash-lite',
            'gemini-1.5-pro',
            'gemini-1.5-pro-latest',
            'gemini-2.0-pro-exp' => 'gemini-2.5-pro',
            default => $model,
        };
    }
}
