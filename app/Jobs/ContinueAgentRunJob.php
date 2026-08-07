<?php

namespace App\Jobs;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Agents\Execution\RunExecutionLock;
use App\Models\AgentRun;
use App\Support\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue, ShouldBeUniqueUntilProcessing};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ContinueAgentRunJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Lock contention is not a failed execution attempt. Allow the queue to retry
     * until the run reaches a terminal state, while still bounding real exceptions.
     */
    public int $tries = 0;
    public int $maxExceptions = 3;
    public int $timeout = 900;
    public int $uniqueFor = 1800;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue('agents');
    }

    public function handle(AgentOrchestrator $orchestrator, RunExecutionLock $locks, WorkspaceContext $workspace): void
    {
        $run = AgentRun::withoutGlobalScopes()->find($this->runId);
        if (!$run || in_array((string) $run->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $acquired = $workspace->run((int) $run->workspace_id, function () use ($orchestrator, $locks): bool {
            return $locks->agent($this->runId, fn () => $orchestrator->advance($this->runId));
        });

        if (! $acquired) {
            // Another worker is advancing this same durable run. Requeue instead of
            // silently consuming the continuation and leaving the run stuck forever.
            $this->release(2);
        }
    }

    public function uniqueId(): string
    {
        return $this->runId;
    }
}
