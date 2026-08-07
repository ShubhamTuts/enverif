<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ChatTranscriptPollingRegressionTest extends TestCase
{
    public function test_chat_status_polling_skips_unchanged_transcript_html(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        self::assertIsString($app);
        self::assertStringContainsString(
            'let transcriptVersion =',
            $app,
            'Chat polling must remember which transcript version is already rendered.'
        );
        self::assertStringContainsString(
            'data?.transcript_version',
            $app,
            'Chat polling must use the durable transcript version returned by ChatStatusController.'
        );
        self::assertStringContainsString(
            'nextTranscriptVersion !== transcriptVersion',
            $app,
            'Unchanged status polls must not replace the entire transcript DOM.'
        );
    }
}
