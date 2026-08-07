<?php

namespace Tests\Feature;

use App\Jobs\ContinueAgentRunJob;
use App\Models\{Agent, AgentRun, Approval, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

final class ApprovalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_first_pending_decision_resumes_the_run(): void
    {
        Queue::fake();
        [$user, $workspace] = $this->owner();
        app(WorkspaceContext::class)->set($workspace->id);

        $agent = Agent::create([
            'name' => 'Sara',
            'slug' => 'sara',
            'instructions' => 'Handle sales.',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 1,
        ]);
        $run = AgentRun::create([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'status' => 'awaiting_approval',
            'input' => 'Reply to prospect',
            'started_at' => now(),
        ]);
        $approval = Approval::create([
            'run_id' => $run->id,
            'action' => 'connector.3.reply',
            'risk_level' => 'external_write',
            'summary' => 'Reply to prospect',
            'payload' => ['to' => 'prospect@example.test'],
            'status' => 'pending',
        ]);
        app(WorkspaceContext::class)->clear();

        $first = $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->postJson(route('approvals.decide', $approval), ['decision' => 'approved']);
        $first->assertOk()->assertJson(['ok' => true, 'status' => 'approved']);

        $second = $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->postJson(route('approvals.decide', $approval), ['decision' => 'denied']);
        $second->assertStatus(409);

        Queue::assertPushed(ContinueAgentRunJob::class, 1);
        self::assertSame('approved', Approval::withoutGlobalScopes()->findOrFail($approval->id)->status);
    }

    /** @return array{User,Workspace} */
    private function owner(): array
    {
        $workspace = Workspace::create(['name' => 'Approvals', 'slug' => 'approvals', 'timezone' => 'UTC', 'locale' => 'en']);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'approval-owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        return [$user, $workspace];
    }
}
