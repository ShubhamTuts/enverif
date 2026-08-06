<?php

namespace App\Core\Runtime;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

final class TickRunner
{
    public function __construct(private readonly RuntimeHealth $health) {}

    /** @return array{ok:bool,skipped:bool,duration_ms:int,queue_output:string,schedule_output:string} */
    public function run(?int $budgetSeconds = null): array
    {
        $budgetSeconds ??= max(10, min(240, (int) config('enverif.runtime.tick_budget', 45)));
        $started = microtime(true);
        $lock = Cache::lock('enverif:runtime:tick', $budgetSeconds + 30);

        if (!$lock->get()) {
            return ['ok' => true, 'skipped' => true, 'duration_ms' => 0, 'queue_output' => '', 'schedule_output' => ''];
        }

        try {
            Artisan::call('enverif:schedules:due');
            $scheduleOutput = trim(Artisan::output());

            $remaining = max(5, $budgetSeconds - (int) ceil(microtime(true) - $started) - 2);
            $connection = (string) config('queue.default');
            Artisan::call('queue:work', [
                'connection' => $connection,
                '--queue' => 'agents,default',
                '--stop-when-empty' => true,
                '--max-time' => $remaining,
                '--tries' => 3,
                '--sleep' => 1,
            ]);
            $queueOutput = trim(Artisan::output());

            $duration = (int) round((microtime(true) - $started) * 1000);
            $this->health->heartbeat([
                'duration_ms' => $duration,
                'queue_connection' => $connection,
                'runtime_mode' => (string) config('enverif.runtime.mode', RuntimeProfileDetector::SHARED),
            ]);

            return ['ok' => true, 'skipped' => false, 'duration_ms' => $duration, 'queue_output' => $queueOutput, 'schedule_output' => $scheduleOutput];
        } finally {
            $lock->release();
        }
    }
}
