<?php

namespace Tests\Unit;

use App\Models\AgentRun;
use App\Models\WorkflowRun;
use PHPUnit\Framework\TestCase;

final class ModelCompositionTest extends TestCase
{
    public function test_uuid_workspace_models_compose_without_trait_collision(): void
    {
        self::assertInstanceOf(AgentRun::class, new AgentRun());
        self::assertInstanceOf(WorkflowRun::class, new WorkflowRun());
        self::assertTrue(method_exists(AgentRun::class, 'resolveRouteBindingQuery'));
        self::assertTrue(method_exists(WorkflowRun::class, 'resolveRouteBindingQuery'));
    }
}
