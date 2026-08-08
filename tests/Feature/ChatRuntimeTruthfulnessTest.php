<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Agents\Tools\FirstParty\{CampaignCreateTool, LeadUpsertTool};
use App\Core\Agents\Tools\ToolRegistry;
use App\Models\{Agent, AgentRun, ChatMessage, ChatThread, Lead, ModelConnection, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ChatRuntimeTruthfulnessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ModelConnection $connection;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Runtime Workspace',
            'slug' => 'runtime-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $this->user = User::create([
            'name' => 'Owner',
            'email' => 'runtime-owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($this->workspace->id);

        $this->connection = ModelConnection::create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'openai',
            'name' => 'Model',
            'credentials' => ['api_key' => 'test-key'],
            'default_model' => 'gpt-5',
            'enabled' => true,
        ]);
        $this->agent = Agent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Revenue Agent',
            'slug' => 'revenue-agent',
            'instructions' => 'Do verified revenue work.',
            'status' => 'active',
            'model_connection_id' => $this->connection->id,
            'model' => 'gpt-5',
            'default_effort' => 'standard',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 5,
        ]);
    }

    public function test_terminal_chat_run_materializes_the_final_assistant_message_without_a_status_or_refresh_request(): void
    {
        $thread = $this->thread();
        $userMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'running',
            'content' => 'Finish this task.',
        ]);
        $run = $this->makeRun([
            'chat_message_id' => $userMessage->id,
            'conversation_id' => $thread->id,
        ]);
        $userMessage->update(['run_id' => $run->id]);

        $run->update([
            'status' => 'completed',
            'output' => 'Verified result.',
            'stop_reason' => 'final',
            'finished_at' => now(),
        ]);

        $assistant = ChatMessage::where('thread_id', $thread->id)
            ->where('run_id', $run->id)
            ->where('role', 'assistant')
            ->first();

        self::assertNotNull($assistant);
        self::assertSame('final', $assistant->kind);
        self::assertSame('completed', $assistant->status);
        self::assertSame('Verified result.', $assistant->content);
        self::assertSame('completed', $userMessage->fresh()->status);
    }

    public function test_chat_status_stays_lightweight_while_activity_endpoint_exposes_the_durable_run_tree(): void
    {
        $thread = $this->thread();
        $userMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'running',
            'content' => 'Delegate this.',
        ]);
        $parent = $this->makeRun(['chat_message_id' => $userMessage->id, 'conversation_id' => $thread->id], 'waiting_child');
        $userMessage->update(['run_id' => $parent->id]);
        $childAgent = Agent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Research Specialist',
            'slug' => 'research-specialist',
            'instructions' => 'Research.',
            'status' => 'active',
            'model_connection_id' => $this->connection->id,
            'model' => 'gpt-5',
            'default_effort' => 'standard',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 5,
        ]);
        $child = AgentRun::create([
            'workspace_id' => $this->workspace->id,
            'agent_id' => $childAgent->id,
            'parent_run_id' => $parent->id,
            'status' => 'running',
            'input' => 'Research',
            'started_at' => now(),
            'context' => ['agent_snapshot' => ['name' => $childAgent->name]],
        ]);
        $child->steps()->create([
            'sequence' => 1,
            'type' => 'tool',
            'status' => 'running',
            'tool' => 'leads.search',
            'risk_level' => 'read',
            'input' => ['arguments' => ['query' => 'prospects']],
            'started_at' => now(),
        ]);

        $status = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->getJson('/chats/'.$thread->id.'/status');

        $status->assertOk()
            ->assertJsonPath('busy', true)
            ->assertJsonPath('terminal_message_present', false)
            ->assertJsonPath('transcript_html', null)
            ->assertJsonMissingPath('activity');

        $activity = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->getJson('/chats/'.$thread->id.'/activity');

        $activity->assertOk()
            ->assertJsonPath('root_run_id', $parent->id)
            ->assertJsonPath('pending_approval_count', 0);
        self::assertSame(2, count((array) $activity->json('runs')));
    }

    public function test_chat_page_keeps_inline_approvals_and_uses_global_agent_activity_control(): void
    {
        $thread = $this->thread();
        ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'submitted',
            'content' => 'Show live work.',
        ]);

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get('/chats/'.$thread->id)
            ->assertOk()
            ->assertSee('data-runtime-approval-stack', false)
            ->assertSee('data-agent-activity-trigger', false)
            ->assertSee('Agent activity')
            ->assertDontSee('data-chat-inline-activity', false);
    }

    public function test_global_runtime_feed_reports_running_chat_work_without_opening_the_chat(): void
    {
        $thread = $this->thread();
        $message = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'running',
            'content' => 'Keep working.',
        ]);
        $run = $this->makeRun(['chat_message_id' => $message->id, 'conversation_id' => $thread->id]);
        $message->update(['run_id' => $run->id]);

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->getJson('/runtime/feed')
            ->assertOk()
            ->assertJsonPath('threads.0.thread_id', $thread->id)
            ->assertJsonPath('threads.0.run_id', $run->id)
            ->assertJsonPath('threads.0.status', 'running');
    }

    public function test_schedule_tool_is_discoverable_and_persists_a_real_recurring_schedule(): void
    {
        Queue::fake();
        $run = $this->makeRun();
        $registry = app(ToolRegistry::class);
        $names = collect($registry->definitions($this->agent))->pluck('name')->all();
        self::assertContains('schedules.upsert', $names);
        self::assertContains('schedules.list', $names);

        $result = $registry->execute($run, 'schedules.upsert', [
            'name' => 'Daily qualified lead research',
            'target_type' => 'agent',
            'agent_id' => $this->agent->id,
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'prompt' => 'Research and persist five verified leads.',
            'enabled' => true,
        ]);

        self::assertTrue($result->ok, $result->message ?? 'schedule tool failed');
        $this->assertDatabaseHas('agent_schedules', [
            'workspace_id' => $this->workspace->id,
            'agent_id' => $this->agent->id,
            'name' => 'Daily qualified lead research',
            'cron_expression' => '0 9 * * *',
            'enabled' => 1,
        ]);
    }

    public function test_lead_upsert_normalizes_verified_contact_data_and_campaign_does_not_queue_an_unreachable_email_recipient(): void
    {
        $run = $this->makeRun();
        $leadResult = (new LeadUpsertTool)->execute($run, [
            'company' => 'Example Electrical',
            'status' => 'qualified',
            'score' => 88,
            'research_summary' => 'Verified business. Phone: (512) 631-5677. No email address was found.',
            'source' => 'research',
        ]);
        self::assertTrue($leadResult->ok);

        $lead = Lead::where('company', 'Example Electrical')->firstOrFail();
        self::assertSame('(512) 631-5677', $lead->phone);
        self::assertSame('ready', $lead->outreach_readiness);

        $emailOnly = Lead::create([
            'workspace_id' => $this->workspace->id,
            'company' => 'No Email Co',
            'status' => 'qualified',
            'score' => 80,
            'outreach_readiness' => 'needs_enrichment',
        ]);
        $campaignResult = (new CampaignCreateTool)->execute($run, [
            'name' => 'Email outreach',
            'lead_ids' => [$emailOnly->id],
            'steps' => [[
                'channel' => 'email',
                'action' => 'send',
                'content' => 'Hello {{company}}',
                'requires_approval' => true,
            ]],
        ]);
        self::assertTrue($campaignResult->ok);
        $campaignId = (int) data_get($campaignResult->data, 'campaign_id');
        $this->assertDatabaseHas('campaign_members', [
            'campaign_id' => $campaignId,
            'lead_id' => $emailOnly->id,
            'status' => 'needs_enrichment',
        ]);
    }

    private function thread(): ChatThread
    {
        return ChatThread::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'agent_id' => $this->agent->id,
            'default_agent_id' => $this->agent->id,
            'default_model_connection_id' => $this->connection->id,
            'default_model' => 'gpt-5',
            'default_effort' => 'standard',
            'title' => 'Runtime chat',
            'last_message_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $context */
    private function makeRun(array $context = [], string $status = 'running'): AgentRun
    {
        return AgentRun::create([
            'workspace_id' => $this->workspace->id,
            'agent_id' => $this->agent->id,
            'status' => $status,
            'input' => 'Do work',
            'started_at' => now(),
            'context' => array_merge(['agent_snapshot' => ['name' => $this->agent->name]], $context),
        ]);
    }
}
