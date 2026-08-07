<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ContinueAgentRunJob;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ChatThread;
use App\Models\ModelConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ChatHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ModelConnection $connection;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator',
            'email' => 'chat@example.test',
            'password' => Hash::make('password'),
        ]);
        $this->workspace = Workspace::create([
            'name' => 'Chat Workspace',
            'slug' => 'chat-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->connection = ModelConnection::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'openai',
            'name' => 'OpenAI',
            'credentials' => ['api_key' => 'test-key'],
            'default_model' => 'gpt-5',
            'enabled' => true,
        ]);
        $this->agent = Agent::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Agent',
            'slug' => 'sales-agent',
            'instructions' => 'Help with revenue operations.',
            'status' => 'active',
            'model_connection_id' => $this->connection->id,
            'model' => 'gpt-5',
            'default_effort' => 'standard',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 5,
        ]);
    }

    public function test_new_chat_json_transport_creates_durable_thread_and_returns_canonical_urls_without_legacy_send_path(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->postJson('/chats', [
                'prompt' => 'Find qualified prospects.',
                'agent_id' => $this->agent->id,
                'model_connection_id' => $this->connection->id,
                'model' => 'gpt-5',
                'effort' => 'deep',
                'persist_defaults' => true,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['thread_id', 'run_id', 'thread_url', 'send_url', 'status_url', 'title', 'transcript_html']);

        $payload = $response->json();
        self::assertStringNotContainsString('/chats/send', (string) $payload['send_url']);
        self::assertStringContainsString('/chats/', (string) $payload['thread_url']);

        $thread = ChatThread::withoutGlobalScopes()->findOrFail((int) $payload['thread_id']);
        self::assertSame($this->workspace->id, (int) $thread->workspace_id);
        self::assertSame($this->agent->id, (int) $thread->default_agent_id);
        self::assertSame($this->connection->id, (int) $thread->default_model_connection_id);
        self::assertSame('deep', $thread->default_effort);

        $run = AgentRun::withoutGlobalScopes()->findOrFail((string) $payload['run_id']);
        self::assertSame('queued', $run->status);
        self::assertSame('gpt-5', data_get($run->context, 'model'));
        self::assertSame('deep', data_get($run->context, 'effort'));
        Queue::assertPushed(ContinueAgentRunJob::class);
    }

    public function test_one_message_override_does_not_rewrite_thread_defaults_when_keep_is_disabled(): void
    {
        Queue::fake();

        $thread = ChatThread::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'agent_id' => $this->agent->id,
            'default_agent_id' => $this->agent->id,
            'default_model_connection_id' => $this->connection->id,
            'default_model' => 'gpt-5',
            'default_effort' => 'standard',
            'title' => 'Existing chat',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->postJson('/chats/'.$thread->id, [
                'prompt' => 'Use a faster pass this time.',
                'agent_id' => $this->agent->id,
                'model_connection_id' => $this->connection->id,
                'model' => 'gpt-5',
                'effort' => 'fast',
                'persist_defaults' => false,
            ]);

        $response->assertStatus(202);
        $thread->refresh();
        self::assertSame('standard', $thread->default_effort);

        $run = AgentRun::withoutGlobalScopes()->findOrFail((string) $response->json('run_id'));
        self::assertSame('fast', data_get($run->context, 'effort'));
    }

    public function test_switching_agent_without_explicit_model_uses_the_new_agents_model_defaults(): void
    {
        Queue::fake();

        $claude = ModelConnection::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'provider' => 'anthropic',
            'name' => 'Claude',
            'credentials' => ['api_key' => 'claude-test-key'],
            'default_model' => 'claude-sonnet-5',
            'enabled' => true,
        ]);
        $research = Agent::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Research Agent',
            'slug' => 'research-agent',
            'instructions' => 'Research qualified prospects.',
            'status' => 'active',
            'model_connection_id' => $claude->id,
            'model' => 'claude-sonnet-5',
            'default_effort' => 'deep',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 5,
        ]);
        $thread = ChatThread::withoutGlobalScopes()->create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'agent_id' => $this->agent->id,
            'default_agent_id' => $this->agent->id,
            'default_model_connection_id' => $this->connection->id,
            'default_model' => 'gpt-5',
            'default_effort' => 'standard',
            'title' => 'Existing chat',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->postJson('/chats/'.$thread->id, [
                'prompt' => 'Use the research agent for this turn.',
                'agent_id' => $research->id,
                'persist_defaults' => true,
            ]);

        $response->assertStatus(202);
        $run = AgentRun::withoutGlobalScopes()->findOrFail((string) $response->json('run_id'));
        self::assertSame($research->id, (int) $run->agent_id);
        self::assertSame($claude->id, (int) data_get($run->context, 'model_connection_id'));
        self::assertSame('claude-sonnet-5', data_get($run->context, 'model'));

        $thread->refresh();
        self::assertSame($research->id, (int) $thread->default_agent_id);
        self::assertSame($claude->id, (int) $thread->default_model_connection_id);
        self::assertSame('claude-sonnet-5', $thread->default_model);
    }

    public function test_chat_attachment_is_private_and_download_requires_the_owning_user(): void
    {
        Queue::fake();
        Storage::fake('local');

        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->post('/chats', [
                'prompt' => 'Review this note.',
                'agent_id' => $this->agent->id,
                'model_connection_id' => $this->connection->id,
                'model' => 'gpt-5',
                'effort' => 'standard',
                'persist_defaults' => '1',
                'attachments' => [UploadedFile::fake()->createWithContent('brief.txt', 'Private account context')],
            ], ['Accept' => 'application/json']);

        $response->assertStatus(202);
        $thread = ChatThread::withoutGlobalScopes()->findOrFail((int) $response->json('thread_id'));
        app(WorkspaceContext::class)->set($this->workspace->id);
        $attachment = $thread->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get('/chat-attachments/'.$attachment->id)
            ->assertOk();

        $other = User::create([
            'name' => 'Other operator',
            'email' => 'other-chat@example.test',
            'password' => Hash::make('password'),
        ]);
        $other->workspaces()->attach($this->workspace->id, ['role' => 'member']);

        $this->actingAs($other)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get('/chat-attachments/'.$attachment->id)
            ->assertForbidden();
    }

}
