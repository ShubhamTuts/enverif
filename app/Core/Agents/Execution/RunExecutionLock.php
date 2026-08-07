<?php

namespace App\Core\Agents\Execution;

use Closure;
use Illuminate\Support\Facades\Cache;

final class RunExecutionLock
{
    private const TTL_SECONDS = 930;

    public function agent(string $runId, Closure $callback): bool
    {
        return $this->run('agent', $runId, $callback);
    }

    public function workflow(string $runId, Closure $callback): bool
    {
        return $this->run('workflow', $runId, $callback);
    }

    private function run(string $type, string $runId, Closure $callback): bool
    {
        $lock = Cache::lock("enverif:run:{$type}:{$runId}", self::TTL_SECONDS);
        if (!$lock->get()) {
            return false;
        }

        try {
            $callback();
            return true;
        } finally {
            $lock->release();
        }
    }
}
