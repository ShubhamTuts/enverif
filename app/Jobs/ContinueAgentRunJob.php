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

    public int $tries = 3;
    public int $timeout = 900;
    public int $uniqueFor = 1800;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue('agents');
    }

    public function handle(AgentOrchestrator $orchestrator, RunExecutionLock $locks, WorkspaceContext $workspace): void
    {
        $run = AgentRun::withoutGlobalScopes()->find($this->runId);
        if (!$run) {
            return;
        }

        $workspace->run((int) $run->workspace_id, function () use ($orchestrator, $locks): void {
            $locks->agent($this->runId, fn () => $orchestrator->advance($this->runId));
        });
    }

    public function uniqueId(): string
    {
        return $this->runId;
    }
}
