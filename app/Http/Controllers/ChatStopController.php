<?php

namespace App\Http\Controllers;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Approvals\ApprovalLifecycle;
use App\Core\Chat\ChatRunMaterializer;
use App\Models\{AgentRun, ChatThread};
use Illuminate\Http\Request;

final class ChatStopController extends Controller
{
    public function __invoke(
        Request $request,
        ChatThread $thread,
        AgentOrchestrator $orchestrator,
        ApprovalLifecycle $approvals,
        ChatRunMaterializer $materializer,
    ) {
        abort_unless((int) $thread->user_id === (int) $request->user()->id, 403);

        $runId = (string) $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');
        $run = $runId !== '' ? AgentRun::find($runId) : null;

        if ($run && ! in_array((string) $run->status, ['completed', 'failed', 'cancelled'], true)) {
            $now = now();
            $frontier = [(string) $run->id];
            $runIds = [];

            while ($frontier !== []) {
                $runIds = array_merge($runIds, $frontier);
                $next = AgentRun::withoutGlobalScopes()
                    ->where('workspace_id', $run->workspace_id)
                    ->whereIn('parent_run_id', $frontier)
                    ->pluck('id')
                    ->map('strval')
                    ->all();

                AgentRun::withoutGlobalScopes()
                    ->where('workspace_id', $run->workspace_id)
                    ->whereIn('id', $frontier)
                    ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => $now,
                        'finished_at' => $now,
                        'stop_reason' => 'cancelled',
                        'updated_at' => $now,
                    ]);

                $frontier = $next;
            }

            $approvals->closeForAgentRuns(array_values(array_unique($runIds)), 'Run cancelled by the operator.');
            $run = $run->fresh();
            $materializer->materialize($run);
            $orchestrator->wakeParent($run);
        }

        return redirect()->route('chat.show', $thread)->with('status', 'Agent run stopped.');
    }
}
