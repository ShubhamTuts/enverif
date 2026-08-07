<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Agent, ModelConnection, User, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ScheduleControllerSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_schedule_never_silently_overwrites_an_existing_matching_schedule(): void
    {
        $workspace = Workspace::create([
            'name' => 'Schedule Workspace',
            'slug' => 'schedule-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'schedule-owner@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);
        app(WorkspaceContext::class)->set($workspace->id);

        $connection = ModelConnection::create([
            'workspace_id' => $workspace->id,
            'provider' => 'openai',
            'name' => 'Model',
            'credentials' => ['api_key' => 'test-key'],
            'default_model' => 'gpt-5',
            'enabled' => true,
        ]);
        $agent = Agent::create([
            'workspace_id' => $workspace->id,
            'name' => 'Revenue Agent',
            'slug' => 'revenue-agent',
            'instructions' => 'Run scheduled work.',
            'status' => 'active',
            'model_connection_id' => $connection->id,
            'model' => 'gpt-5',
            'default_effort' => 'standard',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 5,
        ]);

        $base = [
            'target_type' => 'agent',
            'agent_id' => $agent->id,
            'name' => 'Morning research',
            'cron_expression' => '0 9 * * *',
            'timezone' => 'UTC',
            'enabled' => '1',
        ];

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post('/schedules', $base + ['prompt' => 'Research five verified leads.'])
            ->assertRedirect();

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post('/schedules', $base + ['prompt' => 'Research ten verified leads.'])
            ->assertRedirect();

        $this->assertDatabaseCount('agent_schedules', 2);
        $this->assertDatabaseHas('agent_schedules', ['prompt' => 'Research five verified leads.']);
        $this->assertDatabaseHas('agent_schedules', ['prompt' => 'Research ten verified leads.']);
    }
}
