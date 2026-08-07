<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeContinuationRegressionTest extends TestCase
{
    public function test_agent_continuation_releases_back_to_queue_when_run_lock_is_busy(): void
    {
        $source = file_get_contents(app_path('Jobs/ContinueAgentRunJob.php'));

        self::assertIsString($source);
        self::assertStringContainsString('$acquired = $locks->agent(', $source);
        self::assertStringContainsString('if (! $acquired)', $source);
        self::assertStringContainsString('$this->release(', $source);
        self::assertStringContainsString('public int $maxExceptions', $source);
    }

    public function test_workflow_continuation_releases_back_to_queue_when_run_lock_is_busy(): void
    {
        $source = file_get_contents(app_path('Jobs/ContinueWorkflowRunJob.php'));

        self::assertIsString($source);
        self::assertStringContainsString('$acquired = $locks->workflow(', $source);
        self::assertStringContainsString('if (! $acquired)', $source);
        self::assertStringContainsString('$this->release(', $source);
        self::assertStringContainsString('public int $maxExceptions', $source);
    }
}
