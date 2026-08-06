<?php

declare(strict_types=1);

namespace App\Core\Runtime;

final class WebCronSignature
{
    public static function sign(string $secret, int $timestamp, string $nonce): string
    {
        if ($secret === '' || $nonce === '') {
            throw new \InvalidArgumentException('Cron secret and nonce are required.');
        }

        return hash_hmac('sha256', $timestamp . ':' . $nonce, $secret);
    }

    public static function verify(string $secret, int $timestamp, string $nonce, string $signature, ?int $now = null, int $ttlSeconds = 300): bool
    {
        $now ??= time();
        if ($timestamp <= 0 || $nonce === '' || $signature === '' || abs($now - $timestamp) > $ttlSeconds) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $nonce), $signature);
    }

    public static function stableToken(string $secret): string
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('Cron secret is required.');
        }

        return hash_hmac('sha256', 'enverif-web-cron-v1', $secret);
    }

    public static function verifyStable(string $secret, string $token): bool
    {
        if ($secret === '' || $token === '') {
            return false;
        }

        return hash_equals(self::stableToken($secret), $token);
    }
}
