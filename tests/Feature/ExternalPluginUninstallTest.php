<?php

namespace Tests\Feature;

use App\Core\Plugins\ExternalPluginUninstaller;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ExternalPluginUninstallTest extends TestCase
{
    public function test_builtin_plugin_cannot_be_physically_uninstalled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Built-in plugins cannot be uninstalled');
        app(ExternalPluginUninstaller::class)->uninstall('smtp');
    }

    public function test_dependency_free_external_plugin_directory_can_be_removed_safely(): void
    {
        $slug = 'test-removable-plugin';
        $dir = base_path('plugins/external/'.$slug);
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/enverif.json', json_encode([
            'schema' => 'enverif.plugin/v1',
            'name' => 'Test Removable Plugin',
            'slug' => $slug,
            'version' => '1.0.0',
            'type' => 'connector',
            'driver' => \App\Core\Connectors\Drivers\SmtpConnector::class,
            'capabilities' => ['network'],
            'license' => 'MIT',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        try {
            app(ExternalPluginUninstaller::class)->uninstall($slug);
            self::assertDirectoryDoesNotExist($dir);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_plugin_slug_cannot_escape_external_plugin_root(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ExternalPluginUninstaller::class)->uninstall('../builtin/smtp');
    }
}
