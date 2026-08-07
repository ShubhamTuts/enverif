<?php

namespace Tests\Feature;

use App\Models\{Agent, AgentRun, Approval, ChatMessage, ChatThread, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ApprovalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_stopping_chat_run_closes_pending_approvals_for_the_run_tree(): void
    {
        $workspace = Workspace::create(['name' => 'Approval', 'slug' => 'approval', 'timezone' => 'UTC', 'locale' => 'en']);
        $user = User::create(['name' => 'Owner', 'email' => 'approval@example.test', 'password' => Hash::make('password')]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($workspace->id);

        $agent = Agent::create([
            'workspace_id' => $workspace->id,
            'name' => 'Agent',
            'slug' => 'approval-agent',
            'instructions' => 'Help.',
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
            'title' => 'Approval chat',
            'last_message_at' => now(),
        ]);
        $message = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'running',
            'content' => 'Send it.',
        ]);
        $run = AgentRun::create([
            'workspace_id' => $workspace->id,
            'agent_id' => $agent->id,
            'status' => 'awaiting_approval',
            'input' => 'Send it.',
            'started_at' => now(),
            'context' => ['conversation_id' => $thread->id, 'chat_message_id' => $message->id],
        ]);
        $message->update(['run_id' => $run->id]);
        $approval = Approval::create([
            'workspace_id' => $workspace->id,
            'run_id' => $run->id,
            'action' => 'connector.1.send',
            'risk_level' => 'external_write',
            'summary' => 'Send message',
            'payload' => ['to' => 'person@example.test'],
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post('/chats/'.$thread->id.'/stop')
            ->assertRedirect();

        self::assertSame('cancelled', $run->fresh()->status);
        self::assertSame('denied', $approval->fresh()->status);
        self::assertNotNull($approval->fresh()->decided_at);
        self::assertSame(0, Approval::where('status', 'pending')->count());
    }
}
