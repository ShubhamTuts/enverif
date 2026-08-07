<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ChatTranscriptPollingRegressionTest extends TestCase
{
    public function test_busy_chat_status_does_not_rebuild_the_full_transcript_on_every_poll(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ChatStatusController.php'));

        self::assertIsString($controller);
        self::assertStringContainsString(
            "'transcript_html' => $busy ? null : view('chat._transcript'",
            $controller,
            'While a run is active, status polling must update lightweight run state without rebuilding the entire transcript DOM payload.'
        );
    }
}
