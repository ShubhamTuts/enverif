<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Core\Models\ToolSchemaNormalizer;
use App\Core\Scheduling\ScheduleManager;
use App\Models\AgentRun;

final class ScheduleUpsertTool implements AgentTool
{
    public function name(): string { return 'schedules.upsert'; }
    public function description(): string { return 'Create or update a real recurring schedule in the current workspace. A successful result is the authoritative confirmation that recurring work was scheduled.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }
    public function parameters(): array
    {
        return ToolSchemaNormalizer::parameters([
            'type' => 'object',
            'required' => ['name', 'target_type', 'cron_expression', 'timezone', 'prompt'],
            'properties' => [
                'name' => ['type' => 'string', 'maxLength' => 120],
                'target_type' => ['type' => 'string', 'enum' => ['agent', 'workflow']],
                'agent_id' => ['type' => 'integer'],
                'workflow_id' => ['type' => 'integer'],
                'cron_expression' => ['type' => 'string', 'description' => 'Standard five-field cron expression.'],
                'timezone' => ['type' => 'string', 'description' => 'IANA timezone identifier.'],
                'prompt' => ['type' => 'string', 'maxLength' => 20000],
                'enabled' => ['type' => 'boolean'],
            ],
        ]);
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        try {
            $schedule = app(ScheduleManager::class)->upsert((int) $run->workspace_id, $arguments);
        } catch (\Throwable $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }

        return ToolExecutionResult::success([
            'schedule_id' => $schedule->id,
            'name' => $schedule->name,
            'target_type' => $schedule->agent_id ? 'agent' : 'workflow',
            'target_id' => $schedule->agent_id ?: $schedule->workflow_id,
            'cron_expression' => $schedule->cron_expression,
            'timezone' => $schedule->timezone,
            'enabled' => (bool) $schedule->enabled,
            'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            'persisted' => true,
        ]);
    }
}
