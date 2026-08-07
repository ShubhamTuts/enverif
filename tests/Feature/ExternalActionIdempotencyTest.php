<?php

namespace Tests\Feature;

use App\Core\Agents\Execution\ExternalActionExecutor;
use App\Core\Agents\Execution\ExternalActionOutcomeUnknown;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExternalActionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_external_action_is_replayed_without_executing_operation_twice(): void
    {
        $workspace = Workspace::create(['name' => 'One', 'slug' => 'one', 'timezone' => 'UTC', 'locale' => 'en']);
        app(WorkspaceContext::class)->set($workspace->id);
        $executor = app(ExternalActionExecutor::class);
        $calls = 0;

        $first = $executor->execute(
            $workspace->id,
            'agent',
            'run-1',
            'step-1',
            'connector.9.send',
            ['to' => 'person@example.test'],
            function () use (&$calls): array {
                $calls++;
                return ['ok' => true, 'data' => ['message_id' => 'provider-123'], 'message' => null];
            },
        );
        $second = $executor->execute(
            $workspace->id,
            'agent',
            'run-1',
            'step-1',
            'connector.9.send',
            ['to' => 'person@example.test'],
            function () use (&$calls): array {
                $calls++;
                return ['ok' => true, 'data' => ['message_id' => 'provider-456'], 'message' => null];
            },
        );

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
        self::assertSame('provider-123', data_get($second, 'data.message_id'));
        $this->assertDatabaseHas('external_action_executions', [
            'workspace_id' => $workspace->id,
            'run_type' => 'agent',
            'run_id' => 'run-1',
            'step_key' => 'step-1',
            'action' => 'connector.9.send',
            'status' => 'completed',
        ]);
    }

    public function test_unknown_external_outcome_is_never_blindly_replayed(): void
    {
        $workspace = Workspace::create(['name' => 'One', 'slug' => 'one', 'timezone' => 'UTC', 'locale' => 'en']);
        app(WorkspaceContext::class)->set($workspace->id);
        $executor = app(ExternalActionExecutor::class);
        $calls = 0;

        try {
            $executor->execute(
                $workspace->id,
                'agent',
                'run-2',
                'step-2',
                'connector.3.send',
                ['to' => 'person@example.test'],
                function () use (&$calls): array {
                    $calls++;
                    throw new \RuntimeException('connection dropped after request');
                },
            );
            self::fail('Expected first action to throw.');
        } catch (\RuntimeException $e) {
            self::assertSame('connection dropped after request', $e->getMessage());
        }

        try {
            $executor->execute(
                $workspace->id,
                'agent',
                'run-2',
                'step-2',
                'connector.3.send',
                ['to' => 'person@example.test'],
                function () use (&$calls): array {
                    $calls++;
                    return ['ok' => true, 'data' => [], 'message' => null];
                },
            );
            self::fail('Expected unknown outcome protection to block replay.');
        } catch (ExternalActionOutcomeUnknown) {
            self::assertSame(1, $calls);
        }

        $this->assertDatabaseHas('external_action_executions', [
            'run_id' => 'run-2',
            'step_key' => 'step-2',
            'status' => 'unknown_outcome',
        ]);
    }
}
