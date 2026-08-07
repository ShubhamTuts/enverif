<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeAssetCacheBustTest extends TestCase
{
    public function test_runtime_javascript_cache_key_changes_when_served_file_changes(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertStringContainsString("@filemtime(public_path('assets/runtime-ui.js'))", $layout);
        self::assertStringContainsString('$runtimeAssetVersion', $layout);
    }

    public function test_chat_application_javascript_cache_key_changes_when_served_file_changes(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertStringContainsString("@filemtime(public_path('assets/app.js'))", $layout);
        self::assertStringContainsString('$appAssetVersion', $layout);
        self::assertStringContainsString("assets/app.js?v={{ $appAssetVersion }}", $layout);
    }
}
