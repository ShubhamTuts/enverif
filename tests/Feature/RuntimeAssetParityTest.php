<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeAssetParityTest extends TestCase
{
    public function test_served_runtime_assets_are_byte_identical_to_their_source_files(): void
    {
        self::assertSame(
            file_get_contents(resource_path('js/runtime-ui.js')),
            file_get_contents(public_path('assets/runtime-ui.js')),
            'The served runtime JavaScript must exactly match its source file.'
        );

        self::assertSame(
            file_get_contents(resource_path('css/runtime-ui.css')),
            file_get_contents(public_path('assets/runtime-ui.css')),
            'The served runtime CSS must exactly match its source file.'
        );
    }
}
