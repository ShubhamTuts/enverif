<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Memory\MemoryInput;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentMemory;
use App\Models\AgentRun;

final class MemoryRememberTool implements AgentTool
{
    public function name(): string { return 'memory.remember'; }
    public function description(): string { return 'Create or update durable non-secret memory for this agent. Never store passwords, API keys, tokens or credentials.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [
            'key' => ['type' => 'string'],
            'value' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'importance' => ['type' => 'integer'],
        ], 'required' => ['key', 'value']];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        try {
            $data = MemoryInput::normalize(
                (string) ($arguments['key'] ?? ''),
                (string) ($arguments['value'] ?? ''),
                (array) ($arguments['tags'] ?? []),
                (int) ($arguments['importance'] ?? 50),
            );
        } catch (\InvalidArgumentException $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }
        if (MemoryInput::containsLikelySecret($data['key'] . "\n" . $data['value'])) {
            return ToolExecutionResult::failure('Memory rejected because it appears to contain a credential or secret. Store operational facts, not secrets.');
        }
        $memory = AgentMemory::updateOrCreate(
            ['workspace_id' => $run->workspace_id, 'agent_id' => $run->agent_id, 'key' => $data['key']],
            ['value' => $data['value'], 'tags' => $data['tags'], 'importance' => $data['importance'], 'source_run_id' => $run->id, 'last_used_at' => now()],
        );
        return ToolExecutionResult::success(['id' => $memory->id, 'key' => $memory->key, 'importance' => $memory->importance]);
    }

}
