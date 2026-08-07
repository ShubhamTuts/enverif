<?php

namespace Tests\Unit;

use App\Core\Agents\Contracts\{CapabilityDecision, RiskLevel};
use App\Core\Agents\Security\CapabilityPolicy;
use PHPUnit\Framework\TestCase;

final class CapabilityPolicyTest extends TestCase
{
    public function test_deny_outweighs_allow(): void
    {
        $policy = new CapabilityPolicy(
            allow: ['connector.*'],
            deny: ['connector.mail.*'],
            allowExternalWrites: true,
        );

        self::assertSame(
            CapabilityDecision::Deny,
            $policy->decide(RiskLevel::ExternalWrite, ['tool' => 'connector.mail.send'])
        );
    }

    public function test_destructive_never_becomes_silent(): void
    {
        $policy = new CapabilityPolicy(allow: ['*'], allowDestructive: true);

        self::assertSame(
            CapabilityDecision::Ask,
            $policy->decide(RiskLevel::Destructive, ['tool' => 'connector.crm.delete'])
        );
    }

    public function test_secrets_always_ask(): void
    {
        $policy = new CapabilityPolicy(allow: ['*']);

        self::assertSame(
            CapabilityDecision::Ask,
            $policy->decide(RiskLevel::Secrets, ['tool' => 'secrets.read'])
        );
    }

    public function test_allow_pattern_does_not_bypass_external_write_approval(): void
    {
        $policy = new CapabilityPolicy(
            allow: ['connector.*'],
            allowExternalWrites: false,
        );

        self::assertSame(
            CapabilityDecision::Ask,
            $policy->decide(RiskLevel::ExternalWrite, ['tool' => 'connector.42.send'])
        );
    }

    public function test_external_write_can_be_autonomous_only_when_explicitly_enabled(): void
    {
        $policy = new CapabilityPolicy(
            allow: ['connector.*'],
            allowExternalWrites: true,
        );

        self::assertSame(
            CapabilityDecision::Allow,
            $policy->decide(RiskLevel::ExternalWrite, ['tool' => 'connector.42.send'])
        );
    }
}
