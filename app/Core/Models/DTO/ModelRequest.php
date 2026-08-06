<?php

namespace App\Core\Models\DTO;

final class ModelRequest
{
    /**
     * @param list<array<string,mixed>> $messages
     * @param list<array<string,mixed>> $tools
     * @param list<array<string,mixed>> $attachments
     */
    public function __construct(
        public readonly string $model,
        public readonly string $system,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly int $maxTokens = 4096,
        public readonly string $effort = 'standard',
        public readonly array $attachments = [],
    ) {}
}
