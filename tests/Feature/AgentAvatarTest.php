<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AgentAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_avatar_upload_is_private_and_rendered_through_authenticated_route(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Operator',
            'email' => 'avatar@example.test',
            'password' => Hash::make('password'),
        ]);
        $workspace = Workspace::create([
            'name' => 'Avatar Workspace',
            'slug' => 'avatar-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post('/agents', [
                'name' => 'Avatar Agent',
                'description' => 'Agent with an avatar.',
                'instructions' => 'Help the operator.',
                'status' => 'active',
                'default_effort' => 'standard',
                'max_steps' => 20,
                'max_runtime_seconds' => 300,
                'max_cost_usd' => 5,
                'avatar' => UploadedFile::fake()->image('agent.png', 128, 128),
            ]);

        $agent = Agent::withoutGlobalScopes()->where('workspace_id', $workspace->id)->where('slug', 'avatar-agent')->firstOrFail();
        $response->assertRedirect(route('agents.show', $agent));
        self::assertNotEmpty($agent->avatar_path);
        Storage::disk('local')->assertExists($agent->avatar_path);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->get('/agents/'.$agent->id.'/avatar')
            ->assertOk();
    }
}
