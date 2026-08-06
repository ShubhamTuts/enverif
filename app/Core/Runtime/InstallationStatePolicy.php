<?php

declare(strict_types=1);

namespace App\Core\Runtime;

final class InstallationStatePolicy
{
    public const FRESH = 'fresh';
    public const INCOMPLETE = 'incomplete';
    public const INSTALLED = 'installed';
    public const STALE_MARKER = 'stale_marker';

    public static function classify(bool $markerExists, bool $schemaReady, bool $ownerMembershipExists): string
    {
        if ($schemaReady && $ownerMembershipExists) {
            return self::INSTALLED;
        }

        if ($markerExists && !$schemaReady) {
            return self::STALE_MARKER;
        }

        if ($schemaReady) {
            return self::INCOMPLETE;
        }

        return self::FRESH;
    }

    public static function isInstalled(bool $markerExists, bool $schemaReady, bool $ownerMembershipExists): bool
    {
        return self::classify($markerExists, $schemaReady, $ownerMembershipExists) === self::INSTALLED;
    }
}
