<?php

namespace Tests\Feature;

use App\Core\Runtime\RunRecovery;
use App\Jobs\ContinueAgentRunJob;
use App\Models\{Agent, AgentRun, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RunRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_stale_active_agent_runs_are_requeued(): void
    {
        Queue::fake();

        $workspace = Workspace::create(['name' => 'Recovery', 'slug' => 'recovery', 'timezone' => 'UTC', 'locale' => 'en']);
        app(WorkspaceContext::class)->set($workspace->id);
        $agent = Agent::create([
            'workspace_id' => $workspace->id,
            'name' => 'Recovery Agent',
            'slug' => 'recovery-agent',
            'instructions' => 'Help.',
            'status' => 'active',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 1,
        ]);

        $stale = AgentRun::create([
            'workspace_id' => $workspace->id,
            'agent_id' => $agent->id,
            'status' => 'running',
            'input' => 'stale',
            'started_at' => now()->subMinutes(5),
            'context' => [],
        ]);
        $fresh = AgentRun::create([
            'workspace_id' => $workspace->id,
            'agent_id' => $agent->id,
            'status' => 'running',
            'input' => 'fresh',
            'started_at' => now(),
            'context' => [],
        ]);
        AgentRun::withoutGlobalScopes()->whereKey($stale->id)->update(['updated_at' => now()->subMinutes(5)]);

        $result = app(RunRecovery::class)->recover();

        self::assertSame(1, $result['agents']);
        Queue::assertPushed(ContinueAgentRunJob::class, fn (ContinueAgentRunJob $job) => $job->runId === $stale->id);
        Queue::assertNotPushed(ContinueAgentRunJob::class, fn (ContinueAgentRunJob $job) => $job->runId === $fresh->id);
    }
}
