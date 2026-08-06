<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentRun;

final class DelegateAgentTool implements AgentTool
{
    public function name(): string { return 'agents.delegate'; }
    public function description(): string { return 'Delegate a focused task to another active Enverif agent. The current run pauses until the delegated agent finishes or fails.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [
            'agent_id' => ['type' => 'integer'],
            'prompt' => ['type' => 'string'],
        ], 'required' => ['agent_id', 'prompt']];
    }
    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        return ToolExecutionResult::failure('Delegation must be executed by the durable agent orchestrator.');
    }
}
