<?php

namespace App\Core\Models;

/**
 * Maps internal tool names (often dotted: memory.search, connector.1.send, mcp.2.foo)
 * onto provider-safe function names matching ^[a-zA-Z0-9_-]+$ (DeepSeek / OpenAI / Anthropic).
 *
 * Mapping is reversible so tool_call results still route through ToolRegistry.
 */
final class StrictFunctionNameMapper
{
    /** @var array<string, string> sanitized => original */
    private array $toOriginal = [];

    /** @var array<string, string> original => sanitized */
    private array $toSanitized = [];

    public function register(string $original): string
    {
        $original = trim($original);
        if ($original === '') {
            $original = 'tool';
        }
        if (isset($this->toSanitized[$original])) {
            return $this->toSanitized[$original];
        }

        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $original) ?: 'tool';
        $base = trim($base, '_');
        if ($base === '' || ! preg_match('/^[a-zA-Z0-9_-]+$/', $base)) {
            $base = 'tool';
        }

        $candidate = $base;
        $i = 2;
        while (isset($this->toOriginal[$candidate]) && $this->toOriginal[$candidate] !== $original) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        $this->toSanitized[$original] = $candidate;
        $this->toOriginal[$candidate] = $original;

        return $candidate;
    }

    public function original(string $sanitized): string
    {
        return $this->toOriginal[$sanitized] ?? $sanitized;
    }

    /** @param list<array<string, mixed>> $tools @return list<array<string, mixed>> */
    public function sanitizeTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (! is_array($tool)) {
                continue;
            }
            $name = (string) ($tool['name'] ?? 'tool');
            $tool['name'] = $this->register($name);
            $out[] = $tool;
        }

        return $out;
    }

    /**
     * Remap tool_calls[].name in OpenAI-style chat messages before sending.
     *
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    public function sanitizeOpenAiMessages(array $messages): array
    {
        foreach ($messages as &$message) {
            if (! is_array($message) || empty($message['tool_calls']) || ! is_array($message['tool_calls'])) {
                continue;
            }
            foreach ($message['tool_calls'] as &$call) {
                if (! is_array($call)) {
                    continue;
                }
                if (isset($call['function']) && is_array($call['function'])) {
                    $name = (string) ($call['function']['name'] ?? '');
                    if ($name !== '') {
                        $call['function']['name'] = $this->register($name);
                    }
                } elseif (isset($call['name'])) {
                    $call['name'] = $this->register((string) $call['name']);
                }
            }
            unset($call);
        }
        unset($message);

        return $messages;
    }

    /**
     * Remap Anthropic tool_use / tool_result names in message content blocks.
     *
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    public function sanitizeAnthropicMessages(array $messages): array
    {
        foreach ($messages as &$message) {
            if (! is_array($message) || ! isset($message['content']) || ! is_array($message['content'])) {
                continue;
            }
            foreach ($message['content'] as &$block) {
                if (! is_array($block)) {
                    continue;
                }
                $type = (string) ($block['type'] ?? '');
                if ($type === 'tool_use' && isset($block['name'])) {
                    $block['name'] = $this->register((string) $block['name']);
                }
            }
            unset($block);
        }
        unset($message);

        return $messages;
    }
}
