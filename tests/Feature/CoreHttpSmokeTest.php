<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CoreHttpSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => Hash::make('password'),
        ]);
        $this->workspace = Workspace::create([
            'name' => 'Smoke Workspace',
            'slug' => 'smoke-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);
    }

    public function test_primary_authenticated_screens_render_without_blade_or_model_boot_errors(): void
    {
        $paths = [
            '/',
            '/agents',
            '/agents/create',
            '/schedules',
            '/leads',
            '/campaigns',
            '/skills',
            '/connectors',
            '/workflows',
            '/workflows/create',
            '/models',
            '/mcp',
            '/approvals',
            '/audit',
            '/settings',
        ];

        foreach ($paths as $path) {
            $response = $this->actingAs($this->user)
                ->withSession(['workspace_id' => $this->workspace->id])
                ->get($path);

            $response->assertOk();
        }
    }

    public function test_skill_and_plugin_management_routes_are_authorized_by_laravel_capabilities(): void
    {
        $ownerSession = ['workspace_id' => $this->workspace->id];

        $this->actingAs($this->user)
            ->withSession($ownerSession)
            ->get('/skills/create')
            ->assertOk();

        $this->actingAs($this->user)
            ->withSession($ownerSession)
            ->post('/skills/install', [])
            ->assertSessionHasErrors('source_url');

        $this->actingAs($this->user)
            ->withSession($ownerSession)
            ->get('/plugins/apify/dependencies')
            ->assertOk();

        $member = User::create([
            'name' => 'Member',
            'email' => 'member@example.test',
            'password' => Hash::make('password'),
        ]);
        $member->workspaces()->attach($this->workspace->id, ['role' => 'member']);

        $this->actingAs($member)
            ->withSession($ownerSession)
            ->get('/skills/create')
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession($ownerSession)
            ->get('/plugins/apify/dependencies')
            ->assertForbidden();
    }

    public function test_product_brand_lockup_is_enverif_without_a_codefreex_suffix(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['workspace_id' => $this->workspace->id])
            ->get('/');

        $response->assertOk();
        $response->assertSee('Enverif');
        $response->assertDontSee('BY CODEFREEX');
        $response->assertDontSee('Enverif by Codefreex');
    }
}
