<?php

declare(strict_types=1);

namespace App\Core\Workflows;

final class WorkflowValueResolver
{
    public static function resolve(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) $out[$key] = self::resolve($item, $context);
            return $out;
        }
        if (!is_string($value)) return $value;

        if (preg_match('/^\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}$/', $value, $match)) {
            return self::get($context, $match[1]);
        }

        return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', static function (array $match) use ($context): string {
            $resolved = self::get($context, $match[1]);
            if (is_scalar($resolved) || $resolved === null) return (string) ($resolved ?? '');
            return json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }, $value) ?? $value;
    }

    private static function get(array $context, string $path): mixed
    {
        $cursor = $context;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) return null;
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }
}
