<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Workflows\WorkflowEngine;
use App\Jobs\ContinueWorkflowRunJob;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_minimal_manual_workflow_completes_through_real_engine(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Operator',
            'email' => 'workflow@example.test',
            'password' => Hash::make('password'),
        ]);
        $workspace = Workspace::create([
            'name' => 'Workflow Workspace',
            'slug' => 'workflow-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($workspace->id);

        $workflow = Workflow::create([
            'name' => 'Smoke workflow',
            'description' => 'Minimal deterministic workflow.',
            'status' => 'active',
            'definition' => [
                'nodes' => [
                    ['id' => 'trigger_1', 'type' => 'manual', 'label' => 'Manual trigger', 'config' => [], 'position' => ['x' => 80, 'y' => 180]],
                    ['id' => 'output_1', 'type' => 'output', 'label' => 'Output', 'config' => ['value' => '{{input.prompt}}'], 'position' => ['x' => 420, 'y' => 180]],
                ],
                'edges' => [
                    ['from' => 'trigger_1', 'to' => 'output_1', 'port' => 'default'],
                ],
            ],
            'settings' => ['allow_external_writes' => false, 'allow_destructive' => false],
            'webhook_secret' => 'test-workflow-secret',
            'version' => 1,
        ]);

        $engine = app(WorkflowEngine::class);
        $run = $engine->start($workflow, 'manual', ['prompt' => 'hello'], 'execute');

        Queue::assertPushed(ContinueWorkflowRunJob::class);
        self::assertSame('queued', $run->status);

        $engine->advance($run->id);

        $run = WorkflowRun::withoutGlobalScopes()->findOrFail($run->id);
        self::assertSame('completed', $run->status);
        self::assertSame(1, (int) data_get($run->context, 'workflow_version'));
        self::assertSame($workflow->definition, data_get($run->context, 'workflow_definition'));
        self::assertNotNull($run->finished_at);
    }
}
