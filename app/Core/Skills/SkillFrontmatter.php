<?php

declare(strict_types=1);

namespace App\Core\Skills;

use InvalidArgumentException;

final class SkillFrontmatter
{
    /** @return array{name:string,description:string,version:string,capabilities:list<string>,body:string} */
    public static function parse(string $content): array
    {
        if (!preg_match('/^---\s*\R(.*?)\R---\s*\R?(.*)$/s', $content, $matches)) {
            throw new InvalidArgumentException('SKILL.md must begin with YAML frontmatter.');
        }

        $meta = [];
        foreach (preg_split('/\R/', trim($matches[1])) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $inner = trim(substr($value, 1, -1));
                $meta[$key] = $inner === '' ? [] : array_values(array_filter(array_map(
                    static fn (string $v): string => trim($v, " \t\n\r\0\x0B\"'"),
                    explode(',', $inner),
                )));
            } else {
                $meta[$key] = trim($value, "\"'");
            }
        }

        $name = trim((string) ($meta['name'] ?? ''));
        if ($name === '' || preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $name) !== 1) {
            throw new InvalidArgumentException('Skill name is required and must use lowercase kebab-case.');
        }

        return [
            'name' => $name,
            'description' => trim((string) ($meta['description'] ?? '')),
            'version' => trim((string) ($meta['version'] ?? '1.0.0')),
            'capabilities' => array_values(array_map('strval', (array) ($meta['capabilities'] ?? []))),
            'body' => trim($matches[2]),
        ];
    }
}
