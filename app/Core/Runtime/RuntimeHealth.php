<?php

namespace App\Core\Runtime;

use App\Models\RuntimeState;
use Illuminate\Support\Facades\DB;

final class RuntimeHealth
{
    public function heartbeat(array $data = []): void
    {
        RuntimeState::query()->updateOrCreate(
            ['key' => 'scheduler'],
            ['value' => array_merge(['at' => now()->toIso8601String()], $data)],
        );
    }

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $state = RuntimeState::query()->find('scheduler')?->value ?? [];
        $jobs = 0;
        $failed = 0;
        try {
            $jobs = (int) DB::table('jobs')->count();
            $failed = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            // Installer/early boot can query health before the queue tables exist.
        }

        return [
            'runtime_mode' => (string) config('enverif.runtime.mode', RuntimeProfileDetector::SHARED),
            'queue' => (string) config('queue.default'),
            'cache' => (string) config('cache.default'),
            'heartbeat' => $state,
            'pending_jobs' => $jobs,
            'failed_jobs' => $failed,
            'php' => PHP_VERSION,
            'redis_extension' => class_exists(\Redis::class),
            'storage_writable' => is_writable(storage_path()),
            'base_url' => (string) config('app.url'),
        ];
    }
}
