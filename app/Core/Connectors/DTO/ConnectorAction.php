<?php

namespace App\Core\Connectors\DTO;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Models\ToolSchemaNormalizer;

final class ConnectorAction
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly RiskLevel $risk,
        public readonly array $parameters = [],
    ) {}

    public function toTool(string $prefix): array
    {
        return [
            'name' => $prefix.'.'.$this->name,
            'description' => $this->description,
            'risk' => $this->risk->value,
            'parameters' => ToolSchemaNormalizer::parameters($this->parameters),
        ];
    }
}
