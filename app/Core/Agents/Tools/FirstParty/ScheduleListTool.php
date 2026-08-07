<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Core\Models\ToolSchemaNormalizer;
use App\Models\{AgentRun, AgentSchedule};

final class ScheduleListTool implements AgentTool
{
    public function name(): string { return 'schedules.list'; }
    public function description(): string { return 'List real recurring schedules persisted in the current workspace. Use this to verify whether recurring work is actually configured.'; }
    public function risk(): RiskLevel { return RiskLevel::Read; }
    public function parameters(): array
    {
        return ToolSchemaNormalizer::parameters([
            'type' => 'object',
            'properties' => [
                'enabled_only' => ['type' => 'boolean', 'description' => 'When true, return only enabled schedules.'],
            ],
        ]);
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $query = AgentSchedule::query()
            ->where('workspace_id', $run->workspace_id)
            ->with(['agent:id,name', 'workflow:id,name'])
            ->orderBy('name');
        if ((bool) ($arguments['enabled_only'] ?? false)) $query->where('enabled', true);

        return ToolExecutionResult::success($query->get()->map(fn (AgentSchedule $schedule) => [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'target_type' => $schedule->agent_id ? 'agent' : 'workflow',
            'target_id' => $schedule->agent_id ?: $schedule->workflow_id,
            'target_name' => $schedule->agent?->name ?: $schedule->workflow?->name,
            'cron_expression' => $schedule->cron_expression,
            'timezone' => $schedule->timezone,
            'prompt' => $schedule->prompt,
            'enabled' => (bool) $schedule->enabled,
            'last_run_at' => $schedule->last_run_at?->toIso8601String(),
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
        ])->values()->all());
    }
}
