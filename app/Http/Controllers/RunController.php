<?php

namespace App\Http\Controllers;

use App\Core\Agents\AgentOrchestrator;
use App\Models\AgentRun;

final class RunController extends Controller
{
    public function show(AgentRun $run)
    {
        $run->load(['agent', 'steps', 'parent.agent', 'children.agent']);
        return view('runs.show', ['run' => $run]);
    }

    public function status(AgentRun $run)
    {
        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'output' => $run->output,
            'stop_reason' => $run->stop_reason,
            'step_count' => $run->step_count,
            'tokens' => $run->input_tokens + $run->output_tokens,
            'cost' => (float) $run->estimated_cost_usd,
            'steps' => $run->steps()->orderBy('sequence')->get(['id', 'sequence', 'status', 'tool', 'risk_level', 'output', 'input']),
        ]);
    }

    public function cancel(AgentRun $run, AgentOrchestrator $orchestrator)
    {
        if (!$this->terminal($run->status)) {
            $now = now();
            $frontier = [$run->id];
            while ($frontier !== []) {
                AgentRun::withoutGlobalScopes()->whereIn('id', $frontier)->where('workspace_id', $run->workspace_id)
                    ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                    ->update(['status' => 'cancelled', 'cancelled_at' => $now, 'finished_at' => $now, 'stop_reason' => 'cancelled']);
                $frontier = AgentRun::withoutGlobalScopes()->whereIn('parent_run_id', $frontier)
                    ->where('workspace_id', $run->workspace_id)->pluck('id')->all();
            }
            $orchestrator->wakeParent($run->fresh());
        }
        return back()->with('status', __('ui.run_cancelled'));
    }

    public function retry(AgentRun $run, AgentOrchestrator $orchestrator)
    {
        if (!in_array($run->status, ['failed', 'cancelled'], true)) {
            return back();
        }
        $run->load('agent');
        $retry = $orchestrator->start($run->agent, (string) $run->input);
        return redirect()->route('runs.show', $retry)->with('status', __('ui.run_requeued'));
    }

    private function terminal(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'cancelled'], true);
    }
}
