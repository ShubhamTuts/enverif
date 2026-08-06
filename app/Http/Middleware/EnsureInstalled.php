<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Runtime\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInstalled
{
    public function __construct(private readonly InstallationState $installationState) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->installationState->isInstalled()) {
            return redirect()->route('install.index');
        }

        return $next($request);
    }
}
