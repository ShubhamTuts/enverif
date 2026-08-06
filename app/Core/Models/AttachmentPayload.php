<?php

namespace App\Core\Models;

final class AttachmentPayload
{
    /** @param array<string,mixed> $attachment */
    public static function safePath(array $attachment): ?string
    {
        $relative = ltrim((string) ($attachment['path'] ?? ''), '/\\');
        if ($relative === '' || str_contains($relative, '..')) return null;

        $root = realpath(storage_path('app/private'));
        $path = realpath(storage_path('app/private/' . $relative));
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return null;
        return $path;
    }

    /** @param array<string,mixed> $attachment */
    public static function isImage(array $attachment): bool
    {
        return str_starts_with(strtolower((string) ($attachment['mime_type'] ?? '')), 'image/');
    }

    /** @param array<string,mixed> $attachment */
    public static function text(array $attachment, int $limit = 18000): ?string
    {
        $path = self::safePath($attachment);
        if (!$path) return null;
        $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = str_starts_with($mime, 'text/') || in_array($extension, ['txt','md','csv','json','xml','html','log'], true);
        if (!$allowed) return null;

        $content = @file_get_contents($path, false, null, 0, max(1024, $limit * 2));
        if (!is_string($content) || $content === '') return null;
        if (!mb_check_encoding($content, 'UTF-8')) return null;
        return mb_substr($content, 0, $limit);
    }

    /** @param array<string,mixed> $attachment */
    public static function base64(array $attachment, int $maxBytes = 6_000_000): ?string
    {
        $path = self::safePath($attachment);
        if (!$path) return null;
        $size = filesize($path);
        if ($size === false || $size > $maxBytes) return null;
        $bytes = @file_get_contents($path);
        return is_string($bytes) ? base64_encode($bytes) : null;
    }

    /** @param array<string,mixed> $attachment */
    public static function label(array $attachment): string
    {
        return trim((string) ($attachment['original_name'] ?? 'attachment')) ?: 'attachment';
    }
}
