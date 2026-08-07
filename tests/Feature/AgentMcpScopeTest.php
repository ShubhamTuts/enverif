<?php

namespace Tests\Feature;

use App\Core\Agents\Tools\ToolRegistry;
use App\Models\{Agent, AgentRun, McpServer, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Http};
use Tests\TestCase;

final class AgentMcpScopeTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $user;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Workspace::create([
            'name' => 'MCP Scope',
            'slug' => 'mcp-scope',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $this->user = User::create([
            'name' => 'MCP Owner',
            'email' => 'mcp-owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($this->workspace->id);
        $this->agent = Agent::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Scoped Agent',
            'slug' => 'scoped-agent',
            'instructions' => 'Use only assigned capabilities.',
            'status' => 'active',
            'default_effort' => 'standard',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 2,
            'settings' => [],
        ]);
    }

    public function test_legacy_agent_without_mcp_scope_keeps_enabled_workspace_mcp_access(): void
    {
        $server = $this->server('Legacy MCP');
        Http::fake(fn () => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => [[
                'name' => 'repo.read',
                'description' => 'Read repository data',
                'inputSchema' => ['type' => 'object', 'properties' => []],
                'annotations' => ['readOnlyHint' => true],
            ]]],
        ], 200));

        $names = collect(app(ToolRegistry::class)->definitions($this->agent))->pluck('name')->all();

        self::assertContains('mcp.'.$server->id.'.repo.read', $names);
    }

    public function test_explicit_empty_mcp_scope_exposes_no_workspace_mcp_tools(): void
    {
        $this->server('Unassigned MCP');
        $this->agent->update(['settings' => ['mcp_server_ids' => []]]);
        Http::fake();

        $names = collect(app(ToolRegistry::class)->definitions($this->agent->fresh()))->pluck('name')->all();

        self::assertSame([], array_values(array_filter($names, fn (string $name) => str_starts_with($name, 'mcp.'))));
        Http::assertNothingSent();
    }

    public function test_agent_scope_exposes_only_selected_mcp_server(): void
    {
        $allowed = $this->server('Allowed MCP', 'https://1.1.1.1/allowed');
        $blocked = $this->server('Blocked MCP', 'https://1.0.0.1/blocked');
        $this->agent->update(['settings' => ['mcp_server_ids' => [$allowed->id]]]);
        Http::fake(function ($request) use ($allowed) {
            if ($request->url() !== $allowed->endpoint) {
                return Http::response(['error' => 'unexpected endpoint'], 500);
            }
            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['tools' => [[
                    'name' => 'repo.read',
                    'description' => 'Read repository data',
                    'inputSchema' => ['type' => 'object', 'properties' => []],
                    'annotations' => ['readOnlyHint' => true],
                ]]],
            ], 200);
        });

        $names = collect(app(ToolRegistry::class)->definitions($this->agent->fresh()))->pluck('name')->all();

        self::assertContains('mcp.'.$allowed->id.'.repo.read', $names);
        self::assertNotContains('mcp.'.$blocked->id.'.repo.read', $names);
        Http::assertSentCount(1);
    }

    public function test_agent_editor_persists_an_explicit_mcp_allow_list(): void
    {
        $allowed = $this->server('Editor Allowed MCP');
        $this->server('Editor Blocked MCP', 'https://1.0.0.1/editor-blocked');

        $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->put('/agents/'.$this->agent->id, [
                'name' => $this->agent->name,
                'description' => '',
                'instructions' => $this->agent->instructions,
                'status' => 'active',
                'model_connection_id' => '',
                'model' => '',
                'default_effort' => 'standard',
                'max_steps' => 20,
                'max_runtime_seconds' => 300,
                'max_cost_usd' => 2,
                'mcp_scope_present' => '1',
                'mcp_servers' => [$allowed->id],
            ])
            ->assertRedirect();

        $settings = (array) Agent::withoutGlobalScopes()->findOrFail($this->agent->id)->settings;
        self::assertSame([$allowed->id], array_map('intval', (array) ($settings['mcp_server_ids'] ?? [])));
    }

    public function test_run_snapshot_rejects_direct_mcp_execution_outside_allowed_scope(): void
    {
        $server = $this->server('Blocked Runtime MCP');
        $this->agent->update(['settings' => ['mcp_server_ids' => []]]);
        $run = AgentRun::create([
            'workspace_id' => $this->workspace->id,
            'agent_id' => $this->agent->id,
            'status' => 'running',
            'input' => 'Try MCP',
            'started_at' => now(),
            'context' => [
                'agent_snapshot' => [
                    'name' => $this->agent->name,
                    'mcp_server_ids' => [],
                ],
            ],
        ]);
        $toolName = 'mcp.'.$server->id.'.repo.read';
        $run->steps()->create([
            'sequence' => 1,
            'type' => 'tool',
            'status' => 'running',
            'tool' => $toolName,
            'risk_level' => 'read',
            'input' => ['arguments' => []],
            'started_at' => now(),
        ]);
        Http::fake(fn () => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['content' => [['type' => 'text', 'text' => 'should not run']]],
        ], 200));

        $result = app(ToolRegistry::class)->execute($run, $toolName, []);

        self::assertFalse($result->ok);
        self::assertStringContainsString('not assigned', strtolower((string) $result->message));
        Http::assertNothingSent();
    }

    private function server(string $name, string $endpoint = 'https://1.1.1.1/mcp'): McpServer
    {
        return McpServer::create([
            'workspace_id' => $this->workspace->id,
            'name' => $name,
            'transport' => 'http',
            'endpoint' => $endpoint,
            'credentials' => [],
            'configuration' => ['protocol_version' => '2026-07-28'],
            'enabled' => true,
        ]);
    }
}
