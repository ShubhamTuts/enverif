<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class UseWorkspace
{
    public function __construct(private readonly WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $workspaceId = (int) session('workspace_id', 0);
        $workspace = $workspaceId
            ? $user->workspaces()->where('workspaces.id', $workspaceId)->first()
            : null;
        if (!$workspace) {
            $workspace = $user->workspaces()->first();
        }
        if (!$workspace) {
            abort(403, 'No workspace is assigned to this account.');
        }

        session(['workspace_id' => $workspace->id]);
        View::share('currentWorkspace', $workspace);
        View::share('availableWorkspaces', $user->workspaces()->get());

        $this->context->set((int) $workspace->id);
        try {
            return $next($request);
        } finally {
            // Important for Octane/long-lived workers and tests: never leak tenant
            // state into the next request handled by the same PHP process.
            $this->context->clear();
        }
    }
}
