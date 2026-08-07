<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ChatRuntimeFreezeRegressionTest extends TestCase
{
    public function test_chat_runtime_projection_does_not_observe_and_mutate_the_same_subtree(): void
    {
        $runtime = file_get_contents(resource_path('js/runtime-ui.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        self::assertIsString($runtime);
        self::assertIsString($app);

        self::assertStringNotContainsString(
            "new MutationObserver(() => renderProjection()).observe(chatScroll, {childList:true, subtree:true})",
            $runtime,
            'Runtime projection must not use a self-triggering subtree MutationObserver.'
        );

        self::assertStringContainsString('enverif:chat-transcript-rendered', $app);
        self::assertStringContainsString('enverif:chat-transcript-rendered', $runtime);
    }
}
