<?php

declare(strict_types=1);

namespace App\Core\Agents\Contracts;

enum CapabilityDecision: string
{
    case Allow = 'allow';
    case Ask = 'ask';
    case Deny = 'deny';

    public static function strictest(self ...$decisions): self
    {
        $rank = [self::Allow->value => 0, self::Ask->value => 1, self::Deny->value => 2];
        $strictest = self::Allow;
        foreach ($decisions as $decision) {
            if ($rank[$decision->value] > $rank[$strictest->value]) {
                $strictest = $decision;
            }
        }
        return $strictest;
    }
}
