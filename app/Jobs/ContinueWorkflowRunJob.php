<?php

namespace App\Jobs;

use App\Core\Agents\Execution\RunExecutionLock;
use App\Core\Workflows\WorkflowEngine;
use App\Models\WorkflowRun;
use App\Support\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue, ShouldBeUniqueUntilProcessing};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ContinueWorkflowRunJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 300;
    public int $uniqueFor = 900;

    public function __construct(public readonly string $runId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->runId;
    }

    public function handle(WorkflowEngine $engine, RunExecutionLock $locks, WorkspaceContext $workspace): void
    {
        $run = WorkflowRun::withoutGlobalScopes()->find($this->runId);
        if (!$run) {
            return;
        }

        $workspace->run((int) $run->workspace_id, function () use ($engine, $locks): void {
            $locks->workflow($this->runId, fn () => $engine->advance($this->runId));
        });
    }
}
