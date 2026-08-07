<?php

namespace App\Core\Scheduling;

use App\Models\{Agent, AgentSchedule, Workflow};
use Cron\CronExpression;
use InvalidArgumentException;

final class ScheduleManager
{
    /** @param array<string,mixed> $input */
    public function upsert(int $workspaceId, array $input, ?AgentSchedule $schedule = null): AgentSchedule
    {
        $data = $this->normalize($workspaceId, $input);

        if ($schedule) {
            if ((int) $schedule->workspace_id !== $workspaceId) {
                throw new InvalidArgumentException('Schedule does not belong to the current workspace.');
            }
            $schedule->update($data);
            return $schedule->fresh();
        }

        $name = trim((string) $data['name']);
        $targetQuery = AgentSchedule::query()
            ->where('workspace_id', $workspaceId)
            ->where('name', $name);
        if ($data['agent_id']) {
            $targetQuery->where('agent_id', $data['agent_id'])->whereNull('workflow_id');
        } else {
            $targetQuery->where('workflow_id', $data['workflow_id'])->whereNull('agent_id');
        }

        $existing = $targetQuery->first();
        if ($existing) {
            $existing->update($data);
            return $existing->fresh();
        }

        return AgentSchedule::create($data);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function normalize(int $workspaceId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $cron = trim((string) ($input['cron_expression'] ?? ''));
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $prompt = trim((string) ($input['prompt'] ?? ''));
        $targetType = (string) ($input['target_type'] ?? '');
        $agentId = (int) ($input['agent_id'] ?? 0);
        $workflowId = (int) ($input['workflow_id'] ?? 0);

        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Schedule name is required and must be at most 120 characters.');
        if ($prompt === '' || mb_strlen($prompt) > 20000) throw new InvalidArgumentException('Schedule prompt is required and must be at most 20000 characters.');
        if (! in_array($targetType, ['agent', 'workflow'], true)) throw new InvalidArgumentException('Schedule target_type must be agent or workflow.');

        if ($targetType === 'agent') {
            $workflowId = 0;
            if ($agentId <= 0 || ! Agent::query()->where('workspace_id', $workspaceId)->whereKey($agentId)->where('status', 'active')->exists()) {
                throw new InvalidArgumentException('The selected schedule agent is unavailable.');
            }
        } else {
            $agentId = 0;
            if ($workflowId <= 0 || ! Workflow::query()->where('workspace_id', $workspaceId)->whereKey($workflowId)->where('status', 'active')->exists()) {
                throw new InvalidArgumentException('The selected schedule workflow must be active.');
            }
        }

        ScheduleTarget::type($agentId ?: null, $workflowId ?: null);
        CronExpressionLite::fromString($cron);
        if (! CronExpression::isValidExpression($cron)) throw new InvalidArgumentException('Invalid cron expression.');
        if (! in_array($timezone, timezone_identifiers_list(), true)) throw new InvalidArgumentException('Invalid schedule timezone.');

        return [
            'workspace_id' => $workspaceId,
            'agent_id' => $agentId ?: null,
            'workflow_id' => $workflowId ?: null,
            'name' => $name,
            'cron_expression' => $cron,
            'timezone' => $timezone,
            'prompt' => $prompt,
            'enabled' => filter_var($input['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'next_run_at' => (new CronExpression($cron))->getNextRunDate('now', 0, false, $timezone),
        ];
    }
}
