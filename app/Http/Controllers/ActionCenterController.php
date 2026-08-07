<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Http\Request;

final class ActionCenterController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Approval::query()->latest();
        $status = (string) $request->input('status', 'pending');
        if (in_array($status, ['pending', 'approved', 'denied'], true)) $query->where('status', $status);

        $approvals = $query->paginate(30)->withQueryString();
        $pendingCount = Approval::query()->where('status', 'pending')->count();

        if ($request->expectsJson()) {
            return response()->json([
                'pending_count' => $pendingCount,
                'items' => collect($approvals->items())->map(fn (Approval $approval) => [
                    'id' => $approval->id,
                    'run_id' => $approval->run_id,
                    'workflow_run_id' => $approval->workflow_run_id,
                    'action' => $approval->action,
                    'risk_level' => $approval->risk_level,
                    'summary' => $approval->summary,
                    'status' => $approval->status,
                    'created_at' => $approval->created_at?->toIso8601String(),
                    'decided_at' => $approval->decided_at?->toIso8601String(),
                    'decide_url' => route('approvals.decide', $approval),
                ])->values(),
            ]);
        }

        return view('action-center.index', compact('approvals', 'pendingCount', 'status'));
    }
}
