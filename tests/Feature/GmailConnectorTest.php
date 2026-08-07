<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Connectors\Drivers\GmailConnector;
use App\Models\ConnectorConnection;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GmailConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_returns_readable_mail_metadata_and_body_for_agent_reasoning(): void
    {
        $workspace = Workspace::create([
            'name' => 'Mail Workspace',
            'slug' => 'mail-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        app(WorkspaceContext::class)->set($workspace->id);

        $connection = ConnectorConnection::create([
            'driver' => 'gmail',
            'name' => 'Gmail',
            'credentials' => [
                'access_token' => 'gmail-test-token',
                'expires_at' => time() + 3600,
            ],
            'configuration' => [],
            'enabled' => true,
        ]);

        $body = rtrim(strtr(base64_encode("Hi Shubham,\n\nYes, I am ready to move forward.\n\nSara"), '+/', '-_'), '=');
        Http::fake(function (Request $request) use ($body) {
            if (str_contains($request->url(), '/gmail/v1/users/me/threads/thread-1')) {
                return Http::response([
                    'id' => 'thread-1',
                    'historyId' => '99',
                    'messages' => [[
                        'id' => 'message-1',
                        'threadId' => 'thread-1',
                        'internalDate' => '1786046400000',
                        'snippet' => 'Yes, I am ready to move forward.',
                        'payload' => [
                            'mimeType' => 'text/plain',
                            'headers' => [
                                ['name' => 'From', 'value' => 'Sara <sara@example.test>'],
                                ['name' => 'To', 'value' => 'Shubham <owner@example.test>'],
                                ['name' => 'Subject', 'value' => 'Re: Website proposal'],
                                ['name' => 'Date', 'value' => 'Thu, 6 Aug 2026 18:40:00 +0000'],
                                ['name' => 'Message-ID', 'value' => '<message-1@example.test>'],
                            ],
                            'body' => ['data' => $body],
                        ],
                    ]],
                ], 200);
            }

            return Http::response([], 404);
        });

        $result = app(GmailConnector::class)->execute($connection, 'thread', ['thread_id' => 'thread-1']);

        self::assertTrue($result->ok);
        self::assertSame('thread-1', $result->data['thread_id'] ?? null);
        self::assertSame('Sara <sara@example.test>', $result->data['messages'][0]['from'] ?? null);
        self::assertSame('Re: Website proposal', $result->data['messages'][0]['subject'] ?? null);
        self::assertStringContainsString('ready to move forward', $result->data['messages'][0]['body'] ?? '');
        self::assertSame('<message-1@example.test>', $result->data['messages'][0]['message_id'] ?? null);
    }
}
