<?php

declare(strict_types=1);

namespace App\Core\Email;

use App\Core\Agents\Contracts\RiskLevel;

final class MailActionPolicy
{
    public static function risk(string $action): RiskLevel
    {
        return match ($action) {
            'account', 'search', 'thread' => RiskLevel::Read,
            'draft' => RiskLevel::InternalWrite,
            'send', 'reply' => RiskLevel::ExternalWrite,
            default => throw new \InvalidArgumentException("Unknown mail action: {$action}"),
        };
    }
}
