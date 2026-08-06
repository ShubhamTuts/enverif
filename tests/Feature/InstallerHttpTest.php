<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InstallerHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(storage_path('app/installed'));
        @unlink(storage_path('app/bootstrap.key'));
    }

    public function test_installer_page_renders_provider_model_catalog_without_undefined_view_variables(): void
    {
        $response = $this->get('/install');

        $response->assertOk();
        $response->assertSee('data-install-model-catalog', false);
        $response->assertSee('OpenAI');
        $response->assertSee('Anthropic Claude');
        $response->assertSee('Google Gemini');
        $response->assertSee('DeepSeek');
    }
}
