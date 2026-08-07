<?php

namespace Tests\Feature;

use App\Models\{User, Workspace};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_use_chat_but_cannot_administer_sensitive_integrations(): void
    {
        [$user, $workspace] = $this->member('member');

        $this->actingAs($user)->withSession(['workspace_id' => $workspace->id]);

        $this->get('/')->assertOk();
        $this->get('/models/create')->assertForbidden();
        $this->get('/connectors/create')->assertForbidden();
        $this->get('/mcp/create')->assertForbidden();
        $this->get('/skills/create')->assertForbidden();
    }

    public function test_admin_can_open_sensitive_integration_management(): void
    {
        [$user, $workspace] = $this->member('admin');

        $this->actingAs($user)->withSession(['workspace_id' => $workspace->id]);

        $this->get('/models/create')->assertOk();
        $this->get('/connectors/create')->assertOk();
        $this->get('/mcp/create')->assertOk();
        $this->get('/skills/create')->assertOk();
    }

    /** @return array{User, Workspace} */
    private function member(string $role): array
    {
        $workspace = Workspace::create([
            'name' => ucfirst($role).' Workspace',
            'slug' => $role.'-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $owner = User::create([
            'name' => 'Workspace Owner',
            'email' => 'owner-'.$role.'@example.test',
            'password' => Hash::make('Correct-Horse-123'),
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $owner->workspaces()->attach($workspace->id, ['role' => 'owner']);

        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'@example.test',
            'password' => Hash::make('Correct-Horse-123'),
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $user->workspaces()->attach($workspace->id, ['role' => $role]);

        return [$user, $workspace];
    }
}
