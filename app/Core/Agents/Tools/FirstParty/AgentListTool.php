<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Core\Models\ToolSchemaNormalizer;
use App\Models\Agent;
use App\Models\AgentRun;

final class AgentListTool implements AgentTool
{
    public function name(): string { return 'agents.list'; }
    public function description(): string { return 'List active specialist agents available in the current workspace for delegation.'; }
    public function risk(): RiskLevel { return RiskLevel::Read; }
    public function parameters(): array
    {
        return ToolSchemaNormalizer::parameters(['type' => 'object', 'properties' => []]);
    }
    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        return ToolExecutionResult::success(
            Agent::where('workspace_id', $run->workspace_id)
                ->where('status', 'active')
                ->where('id', '!=', $run->agent_id)
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->toArray()
        );
    }
}
