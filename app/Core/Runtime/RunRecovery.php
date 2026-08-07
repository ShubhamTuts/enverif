<?php

namespace App\Core\Runtime;

use App\Jobs\{ContinueAgentRunJob, ContinueWorkflowRunJob};
use App\Models\{AgentRun, WorkflowRun};
use Carbon\CarbonImmutable;

final class RunRecovery
{
    /** @return array{agents:int,workflows:int} */
    public function recover(?CarbonImmutable $before = null): array
    {
        $before ??= CarbonImmutable::now()->subMinutes(2);

        $agentIds = AgentRun::withoutGlobalScopes()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<=', $before)
            ->orderBy('updated_at')
            ->limit(100)
            ->pluck('id')
            ->map('strval')
            ->all();

        foreach ($agentIds as $runId) {
            ContinueAgentRunJob::dispatch($runId);
        }

        $workflowIds = WorkflowRun::withoutGlobalScopes()
            ->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '<=', $before)
            ->orderBy('updated_at')
            ->limit(100)
            ->pluck('id')
            ->map('strval')
            ->all();

        foreach ($workflowIds as $runId) {
            ContinueWorkflowRunJob::dispatch($runId);
        }

        return ['agents' => count($agentIds), 'workflows' => count($workflowIds)];
    }
}
