<?php

namespace App\Core\Connectors\DTO;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Models\ToolSchemaNormalizer;

final class ConnectorAction
{
    /**
     * @param array<string,mixed> $parameters
     * @param list<string> $capabilities Normalized semantic capabilities such as mail.search or repo.file.read.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly RiskLevel $risk,
        public readonly array $parameters = [],
        public readonly array $capabilities = [],
    ) {}

    public function toTool(string $prefix): array
    {
        return [
            'name' => $prefix.'.'.$this->name,
            'description' => $this->description,
            'risk' => $this->risk->value,
            'parameters' => ToolSchemaNormalizer::parameters($this->parameters),
            'capabilities' => array_values(array_unique(array_filter(array_map('strval', $this->capabilities)))),
        ];
    }
}
