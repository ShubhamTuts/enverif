<?php

declare(strict_types=1);

namespace App\Core\Skills;

final class SourceProvenanceValidator
{
    /** @var list<string> */
    private const TRUSTED_HOSTS = ['github.com', 'gitlab.com', 'codeberg.org'];

    public static function validate(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        return in_array($host, self::TRUSTED_HOSTS, true);
    }

    public static function checksum(string $content): string
    {
        return hash('sha256', $content);
    }
}
