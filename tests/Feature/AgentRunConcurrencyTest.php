<?php

namespace Tests\Feature;

use App\Core\Agents\Execution\RunExecutionLock;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AgentRunConcurrencyTest extends TestCase
{
    public function test_agent_run_does_not_enter_when_another_worker_holds_the_run_lock(): void
    {
        $held = Cache::lock('enverif:run:agent:run-1', 30);
        self::assertTrue($held->get());
        $called = false;

        try {
            $acquired = app(RunExecutionLock::class)->agent('run-1', function () use (&$called): void {
                $called = true;
            });
        } finally {
            $held->release();
        }

        self::assertFalse($acquired);
        self::assertFalse($called);
    }

    public function test_workflow_run_uses_an_independent_lock_namespace(): void
    {
        $called = false;

        $acquired = app(RunExecutionLock::class)->workflow('run-1', function () use (&$called): void {
            $called = true;
        });

        self::assertTrue($acquired);
        self::assertTrue($called);
    }
}
