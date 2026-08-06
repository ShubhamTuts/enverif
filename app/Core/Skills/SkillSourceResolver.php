<?php

declare(strict_types=1);

namespace App\Core\Skills;

final class SkillSourceResolver
{
    public static function downloadUrl(string $sourceUrl, string $ref = 'main'): string
    {
        if (!SourceProvenanceValidator::validate($sourceUrl)) {
            throw new \InvalidArgumentException('Skill source must use HTTPS on a trusted Git host.');
        }

        $parts = parse_url($sourceUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/')), 'strlen'));
        if (count($segments) < 2) {
            throw new \InvalidArgumentException('Skill source must point to a repository root.');
        }
        $segments[array_key_last($segments)] = preg_replace('/\.git$/i', '', $segments[array_key_last($segments)]) ?: '';
        if ($segments[array_key_last($segments)] === '') {
            throw new \InvalidArgumentException('Skill repository name is invalid.');
        }

        $safeRef = rawurlencode($ref !== '' ? $ref : 'main');

        return match ($host) {
            'github.com' => self::github($segments, $safeRef),
            'gitlab.com' => self::gitlab($segments, $safeRef, $ref),
            'codeberg.org' => self::codeberg($segments, $safeRef),
            default => throw new \InvalidArgumentException('Unsupported trusted Git host.'),
        };
    }

    /** @param list<string> $segments */
    private static function github(array $segments, string $ref): string
    {
        if (count($segments) !== 2) {
            throw new \InvalidArgumentException('GitHub skill source must point to the repository root.');
        }
        return 'https://codeload.github.com/' . rawurlencode($segments[0]) . '/' . rawurlencode($segments[1]) . '/zip/' . $ref;
    }

    /** @param list<string> $segments */
    private static function gitlab(array $segments, string $safeRef, string $rawRef): string
    {
        if (in_array('-', $segments, true)) {
            throw new \InvalidArgumentException('GitLab skill source must point to the repository root.');
        }
        $path = implode('/', array_map('rawurlencode', $segments));
        $repo = rawurlencode((string) end($segments));
        return 'https://gitlab.com/' . $path . '/-/archive/' . $safeRef . '/' . $repo . '-' . rawurlencode($rawRef !== '' ? $rawRef : 'main') . '.zip';
    }

    /** @param list<string> $segments */
    private static function codeberg(array $segments, string $ref): string
    {
        if (count($segments) !== 2) {
            throw new \InvalidArgumentException('Codeberg skill source must point to the repository root.');
        }
        return 'https://codeberg.org/' . rawurlencode($segments[0]) . '/' . rawurlencode($segments[1]) . '/archive/' . $ref . '.zip';
    }
}
