<?php

namespace App\Observers;

use App\Core\Chat\ChatRunMaterializer;
use App\Models\AgentRun;

final class AgentRunObserver
{
    public function updated(AgentRun $run): void
    {
        if (! $run->wasChanged('status')) return;
        if (! in_array((string) $run->status, ['completed', 'failed', 'cancelled'], true)) return;

        app(ChatRunMaterializer::class)->materialize($run);
    }
}
