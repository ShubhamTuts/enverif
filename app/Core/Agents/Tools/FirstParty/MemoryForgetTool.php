<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentMemory;
use App\Models\AgentRun;

final class MemoryForgetTool implements AgentTool
{
    public function name(): string { return 'memory.forget'; }
    public function description(): string { return 'Delete one durable memory by its exact key. This is a destructive action and requires policy approval.'; }
    public function risk(): RiskLevel { return RiskLevel::Destructive; }
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => ['key' => ['type' => 'string']], 'required' => ['key']];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $deleted = AgentMemory::where('workspace_id', $run->workspace_id)
            ->where('agent_id', $run->agent_id)
            ->where('key', (string) ($arguments['key'] ?? ''))
            ->delete();
        return $deleted
            ? ToolExecutionResult::success(['deleted' => true])
            : ToolExecutionResult::failure('Memory was not found.');
    }
}
