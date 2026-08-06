<?php

declare(strict_types=1);

namespace App\Core\Agents;

final class RunBounds
{
    public function __construct(
        public readonly int $maxSteps = 40,
        public readonly int $maxRuntimeSeconds = 900,
        public readonly float $maxEstimatedCostUsd = 10.0,
    ) {}

    public function shouldStop(int $steps, int $runtimeSeconds, float $estimatedCostUsd): ?string
    {
        if ($steps >= $this->maxSteps) {
            return 'max_steps';
        }
        if ($runtimeSeconds > $this->maxRuntimeSeconds) {
            return 'max_runtime';
        }
        if ($this->maxEstimatedCostUsd > 0 && $estimatedCostUsd > $this->maxEstimatedCostUsd) {
            return 'max_cost';
        }
        return null;
    }
}
