<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Runtime\{InstallationState, InstallerBootstrapPolicy};
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PrepareInstallerRuntime
{
    public function __construct(private readonly InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        $stores = InstallerBootstrapPolicy::frameworkStores($this->installationState->isInstalled());

        if ($stores === null) {
            return $next($request);
        }

        $this->installationState->clearStaleMarker();
        $key = InstallerBootstrapPolicy::bootstrapKey(
            storage_path('app/bootstrap.key'),
            config('app.key')
        );

        config([
            'app.key' => $key,
            'session.driver' => $stores['session'],
            'session.files' => storage_path('framework/sessions'),
            'cache.default' => $stores['cache'],
            'queue.default' => $stores['queue'],
        ]);

        return $next($request);
    }
}
