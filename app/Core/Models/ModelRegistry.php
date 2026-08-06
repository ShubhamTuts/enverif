<?php

namespace App\Core\Models;

/**
 * Capability-aware model registry. Suggested IDs stay current; custom IDs remain allowed.
 * Agents / chat can filter to models that support the required capabilities (tools, vision, …).
 */
final class ModelRegistry
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(): array
    {
        return [
            'openai' => [
                $this->model('gpt-5.4', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gpt-5.2', tools: true, vision: true, reasoning: true, structured: true, context: 400_000),
                $this->model('gpt-5', tools: true, vision: true, reasoning: true, structured: true, context: 400_000),
                $this->model('gpt-5-mini', tools: true, vision: true, reasoning: false, structured: true, context: 400_000),
                $this->model('gpt-4.1', tools: true, vision: true, reasoning: false, structured: true, context: 1_000_000),
                $this->model('gpt-4.1-mini', tools: true, vision: true, reasoning: false, structured: true, context: 1_000_000),
                $this->model('gpt-4o', tools: true, vision: true, reasoning: false, structured: true, context: 128_000),
                $this->model('gpt-4o-mini', tools: true, vision: true, reasoning: false, structured: true, context: 128_000),
                $this->model('o3', tools: true, vision: true, reasoning: true, structured: true, context: 200_000),
                $this->model('o4-mini', tools: true, vision: true, reasoning: true, structured: true, context: 200_000),
                $this->model('o3-mini', tools: true, vision: false, reasoning: true, structured: true, context: 200_000),
                $this->model('o1', tools: false, vision: false, reasoning: true, structured: false, context: 200_000),
                $this->model('o1-mini', tools: false, vision: false, reasoning: true, structured: false, context: 128_000),
            ],
            'anthropic' => [
                $this->model('claude-opus-5', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('claude-sonnet-5', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('claude-haiku-4-5', tools: true, vision: true, reasoning: true, structured: true, context: 200_000),
                $this->model('claude-opus-4-8', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('claude-sonnet-4-6', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('claude-opus-4-5', tools: true, vision: true, reasoning: true, structured: true, context: 200_000),
                $this->model('claude-sonnet-4-5', tools: true, vision: true, reasoning: true, structured: true, context: 200_000),
                $this->model('claude-fable-5', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
            ],
            'gemini' => [
                $this->model('gemini-3.6-flash', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gemini-3.5-flash', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gemini-3.1-pro-preview', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gemini-3.1-flash-lite', tools: true, vision: true, reasoning: false, structured: true, context: 1_000_000),
                $this->model('gemini-2.5-pro', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gemini-2.5-flash', tools: true, vision: true, reasoning: true, structured: true, context: 1_000_000),
                $this->model('gemini-2.5-flash-lite', tools: true, vision: true, reasoning: false, structured: true, context: 1_000_000),
            ],
            'deepseek' => [
                $this->model('deepseek-v4-flash', tools: true, vision: false, reasoning: true, structured: true, context: 1_000_000),
                $this->model('deepseek-v4-pro', tools: true, vision: false, reasoning: true, structured: true, context: 1_000_000),
            ],
        ];
    }

    /** @return list<string> */
    public function idsFor(string $provider): array
    {
        return array_values(array_map(
            fn (array $row) => (string) $row['id'],
            $this->all()[$provider] ?? [],
        ));
    }

    /**
     * @param  list<string>  $required  capability keys: tools, vision, reasoning, structured
     * @return list<string>
     */
    public function compatibleIds(string $provider, array $required = ['tools']): array
    {
        $out = [];
        foreach ($this->all()[$provider] ?? [] as $row) {
            $ok = true;
            foreach ($required as $cap) {
                if (! ($row[$cap] ?? false)) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = (string) $row['id'];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(string $provider, string $modelId): ?array
    {
        foreach ($this->all()[$provider] ?? [] as $row) {
            if ($row['id'] === $modelId) {
                return $row;
            }
        }

        return null;
    }

    private function model(
        string $id,
        bool $tools,
        bool $vision,
        bool $reasoning,
        bool $structured,
        int $context,
    ): array {
        return [
            'id' => $id,
            'tools' => $tools,
            'vision' => $vision,
            'files' => $vision || $tools,
            'reasoning' => $reasoning,
            'structured' => $structured,
            'streaming' => true,
            'temperature' => ! $reasoning,
            'context_length' => $context,
        ];
    }
}
