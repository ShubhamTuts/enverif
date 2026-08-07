<?php

namespace Tests\Feature;

use App\Models\{Agent, AgentRun, Approval, ChatMessage, ChatThread, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RuntimeFeedPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_feed_exposes_safe_agent_activity_metadata_and_scoped_counts(): void
    {
        $workspace = Workspace::create(['name' => 'Feed', 'slug' => 'feed', 'timezone' => 'UTC', 'locale' => 'en']);
        $user = User::create(['name' => 'Owner', 'email' => 'feed-owner@example.test', 'password' => Hash::make('password')]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($workspace->id);

        $agent = Agent::create([
            'workspace_id' => $workspace->id,
            'name' => 'Research Agent',
            'slug' => 'research-agent',
            'instructions' => 'Research.',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 1,
        ]);
        $thread = ChatThread::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'default_agent_id' => $agent->id,
            'title' => 'Find prospects',
            'last_message_at' => now(),
        ]);
        $run = AgentRun::create([
            'workspace_id' => $workspace->id,
            'agent_id' => $agent->id,
            'status' => 'awaiting_approval',
            'input' => 'Find prospects',
            'started_at' => now(),
            'context' => ['agent_snapshot' => ['name' => $agent->name]],
        ]);
        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'running',
            'content' => 'Find prospects',
            'run_id' => $run->id,
        ]);
        Approval::create([
            'run_id' => $run->id,
            'action' => 'connector.1.send',
            'risk_level' => 'external_write',
            'summary' => 'Send outreach',
            'payload' => ['to' => 'lead@example.test'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->getJson('/runtime/feed');

        $response->assertOk()
            ->assertJsonPath('threads.0.thread_id', $thread->id)
            ->assertJsonPath('threads.0.run_id', $run->id)
            ->assertJsonPath('threads.0.agent_id', $agent->id)
            ->assertJsonPath('threads.0.agent_name', 'Research Agent')
            ->assertJsonPath('threads.0.agent_avatar_url', route('agents.avatar', $agent))
            ->assertJsonPath('threads.0.activity_url', route('chat.activity', $thread))
            ->assertJsonPath('summary.active_count', 1)
            ->assertJsonPath('summary.approval_count', 1);

        self::assertStringNotContainsString('avatar_path', $response->getContent());
        self::assertStringNotContainsString('lead@example.test', $response->getContent());
    }
}
