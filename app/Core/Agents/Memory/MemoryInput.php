<?php

declare(strict_types=1);

namespace App\Core\Agents\Memory;

final class MemoryInput
{
    /** @param list<mixed> $tags @return array{key:string,value:string,tags:list<string>,importance:int} */
    public static function normalize(string $key, string $value, array $tags = [], int $importance = 50): array
    {
        $key = trim($key);
        $value = trim($value);
        if ($key === '' || self::length($key) > 160) {
            throw new \InvalidArgumentException('Memory key must contain 1–160 characters.');
        }
        if ($value === '' || self::length($value) > 20000) {
            throw new \InvalidArgumentException('Memory value must contain 1–20,000 characters.');
        }
        if ($importance < 0 || $importance > 100) {
            throw new \InvalidArgumentException('Memory importance must be between 0 and 100.');
        }
        $normalizedTags = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') continue;
            if (self::length($tag) > 40) $tag = self::slice($tag, 40);
            $normalizedTags[$tag] = true;
            if (count($normalizedTags) >= 20) break;
        }
        return ['key' => $key, 'value' => $value, 'tags' => array_keys($normalizedTags), 'importance' => $importance];
    }


    public static function containsLikelySecret(string $value): bool
    {
        return preg_match('/(?:api[_ -]?key|access[_ -]?token|password|secret)\s*[:=]\s*\S{8,}/i', $value) === 1
            || preg_match('/\bsk-[A-Za-z0-9_-]{20,}\b/', $value) === 1
            || preg_match('/\bBearer\s+[A-Za-z0-9._~+\/-]{16,}/i', $value) === 1;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private static function slice(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}

