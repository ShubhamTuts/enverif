<?php

declare(strict_types=1);

namespace App\Core\Runtime;

final class RuntimeProfileDetector
{
    public const PERFORMANCE = 'performance';
    public const SHARED = 'shared';
    public const COMPATIBILITY = 'compatibility';

    public static function select(bool $redisAvailable, bool $cliCronAvailable, bool $persistentWorkersAvailable = false): string
    {
        if ($redisAvailable && $persistentWorkersAvailable) {
            return self::PERFORMANCE;
        }

        return $cliCronAvailable ? self::SHARED : self::COMPATIBILITY;
    }

    /** @return array{queue:string,cache:string,requires_redis:bool,cron:string} */
    public static function configuration(string $profile): array
    {
        return match ($profile) {
            self::PERFORMANCE => ['queue' => 'redis', 'cache' => 'redis', 'requires_redis' => true, 'cron' => 'daemon'],
            self::SHARED => ['queue' => 'database', 'cache' => 'database', 'requires_redis' => false, 'cron' => 'cli'],
            self::COMPATIBILITY => ['queue' => 'database', 'cache' => 'database', 'requires_redis' => false, 'cron' => 'web'],
            default => throw new \InvalidArgumentException('Unknown runtime profile.'),
        };
    }
}
