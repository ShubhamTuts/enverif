<?php

declare(strict_types=1);

namespace App\Core\Runtime;

use Illuminate\Support\Facades\Log;

/**
 * Opportunistically drains a small amount of queued work after interactive HTTP requests.
 *
 * Shared/compatibility hosting cannot rely on a resident queue worker. The scheduled
 * enverif:tick command remains authoritative for automation, but interactive chat/agent/
 * workflow actions should not look broken merely because the next one-minute cron tick has
 * not fired yet. Laravel runs terminating callbacks after the response has been sent; the
 * same TickRunner lock prevents this kick from racing the real cron/Web Cron runner.
 */
final class WebQueueKick
{
    public function afterResponse(?int $budgetSeconds = null): void
    {
        $mode = (string) config('enverif.runtime.mode', RuntimeProfileDetector::SHARED);
        if (! in_array($mode, [RuntimeProfileDetector::SHARED, RuntimeProfileDetector::COMPATIBILITY], true)) {
            return;
        }

        if ((string) config('queue.default') === 'sync' || app()->runningInConsole()) {
            return;
        }

        $budgetSeconds ??= max(5, min(30, (int) config('enverif.runtime.web_kick_budget', 20)));

        app()->terminating(static function () use ($budgetSeconds): void {
            try {
                app(TickRunner::class)->run($budgetSeconds);
            } catch (\Throwable $e) {
                Log::warning('Enverif interactive queue kick failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
