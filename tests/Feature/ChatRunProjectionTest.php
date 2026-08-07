<?php

namespace Tests\Feature;

use App\Core\Agents\RunProjection;
use App\Models\{Agent, AgentRun, Approval, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ChatRunProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_includes_descendant_agents_steps_and_redacted_pending_approvals(): void
    {
        [$user, $workspace] = $this->workspaceOwner();
        app(WorkspaceContext::class)->set($workspace->id);

        $parentAgent = $this->agent('Primary', 'primary');
        $childAgent = $this->agent('Sara', 'sara');
        $parent = $this->run($parentAgent, null, 'waiting_child');
        $child = $this->run($childAgent, $parent->id, 'awaiting_approval');

        $step = $child->steps()->create([
            'sequence' => 1,
            'type' => 'tool',
            'status' => 'waiting_approval',
            'tool' => 'connector.5.reply',
            'risk_level' => 'external_write',
            'input' => ['to' => 'client@example.test'],
        ]);
        Approval::create([
            'run_id' => $child->id,
            'run_step_id' => $step->id,
            'action' => 'connector.5.reply',
            'risk_level' => 'external_write',
            'summary' => 'Reply to the client email',
            'payload' => ['to' => 'client@example.test', 'api_key' => 'should-never-leak'],
            'status' => 'pending',
        ]);

        $projection = app(RunProjection::class)->forRun($parent->id);

        self::assertSame($parent->id, $projection['root_run_id']);
        self::assertSame(2, count($projection['runs']));
        self::assertSame(1, $projection['pending_approval_count']);
        self::assertSame('Sara', collect($projection['runs'])->firstWhere('id', $child->id)['agent_name']);
        self::assertSame('Connector · Reply', collect($projection['events'])->firstWhere('id', 'step:'.$step->id)['label']);
        self::assertSame('[redacted]', $projection['pending_approvals'][0]['payload']['api_key']);
        self::assertStringNotContainsString('should-never-leak', json_encode($projection, JSON_THROW_ON_ERROR));
        self::assertSame($user->id, $user->id); // keeps owner fixture explicit for request-oriented follow-up tests.
    }

    private function agent(string $name, string $slug): Agent
    {
        return Agent::create([
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'instructions' => 'Do the assigned work.',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 1,
        ]);
    }

    private function run(Agent $agent, ?string $parentId, string $status): AgentRun
    {
        return AgentRun::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'agent_id' => $agent->id,
            'parent_run_id' => $parentId,
            'status' => $status,
            'input' => 'Do work',
            'started_at' => now(),
            'context' => ['agent_snapshot' => ['name' => $agent->name]],
        ]);
    }

    /** @return array{User,Workspace} */
    private function workspaceOwner(): array
    {
        $workspace = Workspace::create(['name' => 'Projection', 'slug' => 'projection', 'timezone' => 'UTC', 'locale' => 'en']);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        return [$user, $workspace];
    }
}
