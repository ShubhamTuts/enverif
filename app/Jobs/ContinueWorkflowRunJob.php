<?php

namespace App\Jobs;

use App\Core\Workflows\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUniqueUntilProcessing};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ContinueWorkflowRunJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=4;
    public int $timeout=300;
    public int $uniqueFor=900;
    public function __construct(public readonly string $runId){$this->onQueue('default');}
    public function uniqueId():string{return $this->runId;}
    public function handle(WorkflowEngine $engine):void{$engine->advance($this->runId);}
}
