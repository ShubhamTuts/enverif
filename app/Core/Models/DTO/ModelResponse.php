<?php
namespace App\Core\Models\DTO;
final class ModelResponse {/** @param list<ToolCall> $toolCalls */public function __construct(public readonly string $content,public readonly array $toolCalls=[],public readonly int $inputTokens=0,public readonly int $outputTokens=0,public readonly ?string $finishReason=null){} }
