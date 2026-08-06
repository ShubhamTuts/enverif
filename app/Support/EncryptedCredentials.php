<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

/**
 * Safe access helpers for Laravel encrypted credential arrays.
 * Decrypt failures usually mean APP_KEY changed after credentials were stored.
 */
final class EncryptedCredentials
{
    public const DECRYPT_MESSAGE = 'API key cannot be decrypted — re-enter the key under AI Models (APP_KEY mismatch or corrupted credentials).';

    public const CONNECTOR_DECRYPT_MESSAGE = 'Plugin credentials cannot be decrypted — re-enter secrets under Plugins (APP_KEY mismatch or corrupted credentials).';

    public const MCP_DECRYPT_MESSAGE = 'MCP credentials cannot be decrypted — re-enter the token under MCP Servers (APP_KEY mismatch or corrupted credentials).';

    /**
     * @param  callable(): mixed  $reader
     * @return array<string, mixed>
     */
    public static function read(callable $reader, string $message = self::DECRYPT_MESSAGE): array
    {
        try {
            $data = $reader();
        } catch (DecryptException $e) {
            throw new RuntimeException($message, 0, $e);
        }

        return is_array($data) ? $data : [];
    }

    public static function isDecryptFailure(\Throwable $e): bool
    {
        if ($e instanceof DecryptException) {
            return true;
        }
        if ($e instanceof RuntimeException && $e->getPrevious() instanceof DecryptException) {
            return true;
        }

        $haystack = strtolower($e->getMessage());

        return str_contains($haystack, 'mac is invalid')
            || str_contains($haystack, 'cannot be decrypted')
            || str_contains($haystack, 'app_key mismatch');
    }

    public static function userMessage(?string $context = 'model'): string
    {
        return match ($context) {
            'connector', 'plugin' => self::CONNECTOR_DECRYPT_MESSAGE,
            'mcp' => self::MCP_DECRYPT_MESSAGE,
            default => self::DECRYPT_MESSAGE,
        };
    }
}
