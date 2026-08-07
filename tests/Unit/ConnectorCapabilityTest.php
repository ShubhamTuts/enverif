<?php

namespace Tests\Unit;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Connectors\DTO\ConnectorAction;
use App\Core\Connectors\Drivers\{GmailConnector, OutlookConnector, SmtpConnector};
use App\Core\Email\OAuthTokenService;
use App\Models\ConnectorConnection;
use Tests\TestCase;

final class ConnectorCapabilityTest extends TestCase
{
    public function test_connector_action_exports_normalized_capabilities_without_changing_tool_name(): void
    {
        $action = new ConnectorAction(
            'search',
            'Search mail',
            RiskLevel::Read,
            ['type' => 'object'],
            ['mail.search', 'mail.message.read'],
        );

        $tool = $action->toTool('connector.12');

        self::assertSame('connector.12.search', $tool['name']);
        self::assertSame(['mail.search', 'mail.message.read'], $tool['capabilities']);
    }

    public function test_mail_drivers_publish_the_same_generic_capability_vocabulary(): void
    {
        $gmail = new GmailConnector(app(OAuthTokenService::class));
        $outlook = new OutlookConnector(app(OAuthTokenService::class));
        $smtp = new SmtpConnector;

        self::assertContains('mail.search', $this->capabilities($gmail->actions(), 'search'));
        self::assertContains('mail.thread.read', $this->capabilities($gmail->actions(), 'thread'));
        self::assertContains('mail.send', $this->capabilities($gmail->actions(), 'send'));
        self::assertContains('mail.reply', $this->capabilities($gmail->actions(), 'reply'));

        self::assertContains('mail.search', $this->capabilities($outlook->actions(), 'search'));
        self::assertContains('mail.thread.read', $this->capabilities($outlook->actions(), 'thread'));
        self::assertContains('mail.send', $this->capabilities($outlook->actions(), 'send'));
        self::assertContains('mail.reply', $this->capabilities($outlook->actions(), 'reply'));

        self::assertContains('mail.send', $this->capabilities($smtp->actions(), 'send'));
        self::assertContains('mail.reply', $this->capabilities($smtp->actions(), 'reply'));
    }

    public function test_smtp_connection_exposes_imap_read_capabilities_only_when_imap_is_configured(): void
    {
        $driver = new SmtpConnector;
        $smtpOnly = new ConnectorConnection(['configuration' => ['host' => 'smtp.example.test']]);
        $withImap = new ConnectorConnection(['configuration' => [
            'host' => 'smtp.example.test',
            'imap_host' => 'imap.example.test',
            'imap_mailbox' => 'INBOX',
        ]]);

        self::assertNotContains('search', array_map(fn ($action) => $action->name, $driver->actionsForConnection($smtpOnly)));

        $imapActions = $driver->actionsForConnection($withImap);
        self::assertContains('search', array_map(fn ($action) => $action->name, $imapActions));
        self::assertContains('mail.receive', $this->capabilities($imapActions, 'receive'));
        self::assertContains('mail.message.read', $this->capabilities($imapActions, 'read'));
        self::assertContains('mail.thread.read', $this->capabilities($imapActions, 'thread'));
    }

    /** @param array<int,ConnectorAction> $actions @return list<string> */
    private function capabilities(array $actions, string $name): array
    {
        foreach ($actions as $action) if ($action->name === $name) return $action->capabilities;
        return [];
    }
}
