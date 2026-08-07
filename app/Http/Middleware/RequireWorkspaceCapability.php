<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal role-to-capability bridge for the existing owner/admin/member model.
 *
 * Keeping capability names on routes avoids spreading role-name checks through
 * controllers and leaves room for custom workspace roles later.
 */
final class RequireWorkspaceCapability
{
    /** @var array<string, list<string>> */
    private const ROLE_CAPABILITIES = [
        'owner' => ['*'],
        'admin' => ['*'],
        'member' => [
            'use-chat',
            'manage-leads',
        ],
    ];

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $workspaceId = (int) session('workspace_id', 0);
        if (!$user || $workspaceId <= 0) {
            abort(403);
        }

        $workspace = $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        $role = (string) ($workspace?->pivot?->role ?? '');
        $allowed = self::ROLE_CAPABILITIES[$role] ?? [];

        if (!in_array('*', $allowed, true) && !in_array($capability, $allowed, true)) {
            abort(403, 'You do not have permission to perform this workspace action.');
        }

        return $next($request);
    }
}
