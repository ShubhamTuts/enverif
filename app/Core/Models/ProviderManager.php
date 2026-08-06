<?php

namespace App\Core\Models;

use App\Core\Models\Contracts\ModelProvider;
use App\Core\Models\Providers\{AnthropicProvider, DeepSeekProvider, GeminiProvider, OpenAIProvider};

final class ProviderManager
{
    /** @var array<string, ModelProvider> */
    private array $providers = [];

    public function __construct(private readonly ModelRegistry $registry = new ModelRegistry)
    {
        foreach ([new OpenAIProvider, new AnthropicProvider, new GeminiProvider, new DeepSeekProvider] as $p) {
            $this->providers[$p->id()] = $p;
        }
    }

    public function get(string $id): ModelProvider
    {
        if (! isset($this->providers[$id])) {
            throw new \InvalidArgumentException("Unknown model provider: {$id}");
        }

        return $this->providers[$id];
    }

    /** @return array<string, ModelProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    /** Suggested model IDs per provider (backward-compatible catalog). */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->providers as $id => $provider) {
            $fromRegistry = $this->registry->idsFor($id);
            $out[$id] = $fromRegistry !== [] ? $fromRegistry : $provider->models();
        }

        return $out;
    }

    /** Capability-rich registry for UI / agent compatibility checks. */
    public function registry(): ModelRegistry
    {
        return $this->registry;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function capabilityCatalog(): array
    {
        return $this->registry->all();
    }
}
