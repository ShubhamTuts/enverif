<?php

declare(strict_types=1);

namespace App\Core\Runtime;

use RuntimeException;

final class InstallerBootstrapPolicy
{
    /** @return array{session:string,cache:string,queue:string}|null */
    public static function frameworkStores(bool $installed): ?array
    {
        if ($installed) {
            return null;
        }

        return [
            'session' => 'file',
            'cache' => 'file',
            'queue' => 'sync',
        ];
    }

    public static function bootstrapKey(string $path, ?string $configuredKey): string
    {
        $configuredKey = trim((string) $configuredKey);
        if ($configuredKey !== '') {
            return $configuredKey;
        }

        if (is_file($path)) {
            return self::readValidKey($path);
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the installer bootstrap-key directory. Make storage/app writable.');
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $handle = @fopen($path, 'x');

        if ($handle !== false) {
            try {
                if (fwrite($handle, $key . PHP_EOL) === false || !fflush($handle)) {
                    throw new RuntimeException('Unable to persist the installer bootstrap key. Make storage/app writable.');
                }
                @chmod($path, 0600);
            } finally {
                fclose($handle);
            }

            return $key;
        }

        // Another concurrent first request may have won the atomic create race.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if (is_file($path)) {
                return self::readValidKey($path);
            }
            usleep(20_000);
        }

        throw new RuntimeException('Unable to initialize the installer bootstrap key. Make storage/app writable.');
    }

    private static function readValidKey(string $path): string
    {
        $key = trim((string) @file_get_contents($path));
        if (!str_starts_with($key, 'base64:')) {
            throw new RuntimeException('The installer bootstrap key is invalid. Delete storage/app/bootstrap.key and retry.');
        }

        $decoded = base64_decode(substr($key, 7), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('The installer bootstrap key is invalid. Delete storage/app/bootstrap.key and retry.');
        }

        return $key;
    }
}
