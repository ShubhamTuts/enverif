<?php

namespace Tests\Feature;

use App\Models\{Agent, ConnectorConnection, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class WorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_scope_hides_other_workspace_records(): void
    {
        $one = Workspace::create(['name' => 'One', 'slug' => 'one', 'timezone' => 'UTC', 'locale' => 'en']);
        $two = Workspace::create(['name' => 'Two', 'slug' => 'two', 'timezone' => 'UTC', 'locale' => 'en']);

        app(WorkspaceContext::class)->set($one->id);
        Agent::create([
            'name' => 'One agent',
            'slug' => 'one-agent',
            'instructions' => 'x',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 0,
        ]);
        Agent::withoutGlobalScopes()->create([
            'workspace_id' => $two->id,
            'name' => 'Two agent',
            'slug' => 'two-agent',
            'instructions' => 'x',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 0,
        ]);

        self::assertSame(1, Agent::count());
        self::assertSame('One agent', Agent::first()->name);
    }

    public function test_workspace_scoped_query_fails_closed_without_context(): void
    {
        $workspace = Workspace::create(['name' => 'One', 'slug' => 'one', 'timezone' => 'UTC', 'locale' => 'en']);
        Agent::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Hidden agent',
            'slug' => 'hidden-agent',
            'instructions' => 'x',
            'status' => 'active',
            'max_steps' => 10,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 0,
        ]);

        app(WorkspaceContext::class)->set(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Workspace context is required');
        Agent::query()->count();
    }

    public function test_workspace_context_run_restores_previous_context_even_when_callback_throws(): void
    {
        $context = app(WorkspaceContext::class);
        $context->set(11);

        try {
            $context->run(22, function () use ($context): void {
                self::assertSame(22, $context->id());
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame(11, $context->id());
    }

    public function test_connector_credentials_are_encrypted_at_rest(): void
    {
        $workspace = Workspace::create(['name' => 'One', 'slug' => 'one', 'timezone' => 'UTC', 'locale' => 'en']);
        app(WorkspaceContext::class)->set($workspace->id);
        $c = ConnectorConnection::create([
            'driver' => 'apollo',
            'name' => 'Apollo',
            'credentials' => ['api_key' => 'super-secret-value'],
            'enabled' => true,
        ]);

        $raw = DB::table('connector_connections')->where('id', $c->id)->value('credentials');
        self::assertIsString($raw);
        self::assertStringNotContainsString('super-secret-value', $raw);
        self::assertSame('super-secret-value', $c->fresh()->credential('api_key'));
    }
}
