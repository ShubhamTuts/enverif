<?php

declare(strict_types=1);

namespace App\Core\Agents\Security;

use App\Core\Agents\Contracts\CapabilityDecision;
use App\Core\Agents\Contracts\RiskLevel;

final class CapabilityPolicy
{
    /** @param list<string> $allow @param list<string> $deny */
    public function __construct(
        private readonly array $allow = [],
        private readonly array $deny = [],
        private readonly bool $allowExternalWrites = false,
        private readonly bool $allowDestructive = false,
    ) {}

    /** @param array<string,mixed> $context */
    public function decide(RiskLevel $risk, array $context): CapabilityDecision
    {
        $tool = (string) ($context['tool'] ?? '');
        if ($tool !== '' && $this->matchesAny($tool, $this->deny)) {
            return CapabilityDecision::Deny;
        }
        if ($tool !== '' && $this->matchesAny($tool, $this->allow)) {
            // Destructive and secret-bearing operations always retain a human checkpoint.
            if ($risk === RiskLevel::Destructive) {
                return $this->allowDestructive ? CapabilityDecision::Ask : CapabilityDecision::Deny;
            }
            if ($risk === RiskLevel::Secrets) {
                return CapabilityDecision::Ask;
            }
            return CapabilityDecision::Allow;
        }

        return match ($risk) {
            RiskLevel::Read, RiskLevel::InternalWrite, RiskLevel::Network => CapabilityDecision::Allow,
            RiskLevel::ExternalWrite => $this->allowExternalWrites
                ? CapabilityDecision::Allow
                : CapabilityDecision::Ask,
            RiskLevel::Destructive => $this->allowDestructive
                ? CapabilityDecision::Ask
                : CapabilityDecision::Deny,
            RiskLevel::Secrets => CapabilityDecision::Ask,
        };
    }

    /** @param list<string> $patterns */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $quoted = preg_quote($pattern, '/');
            $regex = '/^' . str_replace('\\*', '.*', $quoted) . '$/i';
            if (preg_match($regex, $value) === 1) {
                return true;
            }
        }
        return false;
    }
}
