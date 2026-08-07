<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ChatTranscriptPollingRegressionTest extends TestCase
{
    public function test_busy_chat_status_returns_before_loading_full_transcript_or_projection(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChatStatusController.php'));

        self::assertIsString($controller);
        self::assertStringNotContainsString('use App\\Core\\Agents\\RunProjection;', $controller);
        self::assertStringNotContainsString("'activity' =>", $controller);
        self::assertStringContainsString('if ($busy) {', $controller);
        self::assertStringContainsString("'transcript_html' => null", $controller);

        $busyBranch = strpos($controller, 'if ($busy) {');
        $fullThreadLoad = strpos($controller, "->load(['messages.attachments', 'defaultAgent'])");

        self::assertNotFalse($busyBranch);
        self::assertNotFalse($fullThreadLoad);
        self::assertLessThan(
            $fullThreadLoad,
            $busyBranch,
            'The active-run response must return before loading every chat message and attachment.'
        );
    }
}
