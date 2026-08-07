<?php

namespace App\Providers;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Agents\Tools\ToolRegistry;
use App\Core\Connectors\ConnectorManager;
use App\Core\Models\ProviderManager;
use App\Models\AgentRun;
use App\Observers\AgentRunObserver;
use App\Support\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
        $this->app->singleton(ProviderManager::class);
        $this->app->singleton(ConnectorManager::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(AgentOrchestrator::class);
    }

    public function boot(): void
    {
        AgentRun::observe(AgentRunObserver::class);
    }
}
