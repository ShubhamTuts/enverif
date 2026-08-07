<?php

namespace App\Http\Controllers;

use App\Core\Runtime\WebQueueKick;
use App\Jobs\{ContinueAgentRunJob, ContinueWorkflowRunJob};
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query = Approval::latest();
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        return view('approvals.index', ['approvals' => $query->paginate(25)->withQueryString()]);
    }

    public function decide(Request $request, Approval $approval, WebQueueKick $queueKick)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,denied',
            'note' => 'nullable|string|max:1000',
        ]);

        $won = DB::transaction(function () use ($approval, $data, $request): bool {
            $locked = Approval::query()->whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') return false;

            $locked->update([
                'status' => $data['decision'],
                'decision_note' => $data['note'] ?? null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            return true;
        }, 3);

        abort_unless($won, 409, 'This approval has already been decided.');

        if ($approval->run_id) ContinueAgentRunJob::dispatch($approval->run_id);
        if ($approval->workflow_run_id) ContinueWorkflowRunJob::dispatch($approval->workflow_run_id);
        $queueKick->afterResponse();

        if ($request->expectsJson()) return response()->json(['ok' => true, 'status' => $data['decision']]);
        return back()->with('status', __('ui.saved'));
    }
}
