<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeAssetCacheBustTest extends TestCase
{
    public function test_runtime_javascript_cache_key_changes_when_served_file_changes(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertStringContainsString(
            "@filemtime(public_path('assets/runtime-ui.js'))",
            $layout,
            'Runtime JavaScript cache busting must change when the served runtime file changes, even within the same product version.'
        );
        self::assertStringContainsString('$runtimeAssetVersion', $layout);
    }
}
