<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ChatRuntimeFreezeRegressionTest extends TestCase
{
    public function test_chat_runtime_projection_filters_its_own_mutations_before_repainting(): void
    {
        $runtime = file_get_contents(resource_path('js/runtime-ui.js'));

        self::assertIsString($runtime);

        self::assertStringNotContainsString(
            "new MutationObserver(() => renderProjection()).observe(chatScroll, {childList:true, subtree:true})",
            $runtime,
            'Runtime projection must not blindly repaint for every mutation in the subtree.'
        );

        self::assertStringContainsString(
            "target.closest('[data-runtime-approval-stack], [data-chat-inline-activity]')",
            $runtime,
            'Runtime projection must ignore mutations caused by its own approval/activity mounts.'
        );
    }
}
