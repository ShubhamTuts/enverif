<?php

namespace App\Console\Commands;

use App\Core\Runtime\TickRunner;
use Illuminate\Console\Command;

final class EnverifTick extends Command
{
    protected $signature = 'enverif:tick {--budget= : Maximum execution budget in seconds}';
    protected $description = 'Run one bounded Enverif scheduler and queue cycle for shared hosting.';

    public function handle(TickRunner $runner): int
    {
        $budget = $this->option('budget');
        $result = $runner->run($budget !== null ? (int) $budget : null);
        $this->line($result['skipped'] ? 'Another Enverif tick is already running.' : "Enverif tick completed in {$result['duration_ms']} ms.");
        return self::SUCCESS;
    }
}
