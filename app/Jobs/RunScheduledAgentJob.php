<?php

namespace App\Jobs;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Workflows\WorkflowEngine;
use App\Models\AgentSchedule;
use App\Support\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScheduledAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $scheduleId)
    {
        $this->onQueue('agents');
    }

    public function handle(AgentOrchestrator $orchestrator, WorkflowEngine $workflows, WorkspaceContext $workspace): void
    {
        $schedule = AgentSchedule::withoutGlobalScopes()->find($this->scheduleId);
        if (!$schedule || !$schedule->enabled) {
            return;
        }

        $workspace->run((int) $schedule->workspace_id, function () use ($schedule, $orchestrator, $workflows): void {
            // Scoped relationships must be loaded only after the job has established
            // the owning workspace. Persistent queue workers may have handled another
            // tenant immediately before this job.
            $schedule->load(['agent', 'workflow']);

            if ($schedule->workflow_id && $schedule->workflow) {
                $workflows->start($schedule->workflow, 'schedule', [
                    'prompt' => $schedule->prompt,
                    'schedule_id' => $schedule->id,
                ]);
            } elseif ($schedule->agent) {
                $orchestrator->start($schedule->agent, $schedule->prompt, null, [
                    'schedule_id' => $schedule->id,
                ]);
            } else {
                throw new \RuntimeException('Scheduled target no longer exists.');
            }

            $schedule->update(['last_run_at' => now()]);
        });
    }
}
