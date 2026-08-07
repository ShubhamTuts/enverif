<?php

namespace Tests\Feature;

use App\Models\{Agent, ConnectorConnection, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ConnectorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_delete_is_blocked_until_live_agent_dependencies_are_removed(): void
    {
        [$user, $workspace] = $this->owner();
        app(WorkspaceContext::class)->set($workspace->id);
        $connection = ConnectorConnection::create([
            'driver' => 'smtp',
            'name' => 'Sales mail',
            'credentials' => ['username' => 'user', 'password' => 'secret'],
            'configuration' => ['host' => 'smtp.example.test', 'from_email' => 'sales@example.test'],
            'enabled' => true,
        ]);
        $agent = Agent::create([
            'name' => 'Sara', 'slug' => 'sara', 'instructions' => 'Sell', 'status' => 'active',
            'max_steps' => 10, 'max_runtime_seconds' => 300, 'max_cost_usd' => 1,
        ]);
        $agent->connectors()->attach($connection->id);
        app(WorkspaceContext::class)->clear();

        $blocked = $this->actingAs($user)->withSession(['workspace_id' => $workspace->id])->delete(route('connectors.destroy', $connection));
        $blocked->assertStatus(409);
        $this->assertDatabaseHas('connector_connections', ['id' => $connection->id]);

        app(WorkspaceContext::class)->set($workspace->id);
        $agent->connectors()->detach($connection->id);
        app(WorkspaceContext::class)->clear();

        $deleted = $this->actingAs($user)->withSession(['workspace_id' => $workspace->id])->delete(route('connectors.destroy', $connection));
        $deleted->assertRedirect();
        $this->assertDatabaseMissing('connector_connections', ['id' => $connection->id]);
    }

    /** @return array{User,Workspace} */
    private function owner(): array
    {
        $workspace = Workspace::create(['name' => 'Lifecycle', 'slug' => 'lifecycle', 'timezone' => 'UTC', 'locale' => 'en']);
        $user = User::create(['name' => 'Owner', 'email' => 'lifecycle-owner@example.test', 'password' => Hash::make('password')]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        return [$user, $workspace];
    }
}
