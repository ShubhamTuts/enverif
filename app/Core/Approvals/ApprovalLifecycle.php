<?php

namespace App\Core\Approvals;

use App\Models\{AgentRun, Approval, WorkflowRun};

final class ApprovalLifecycle
{
    /** @param list<string> $runIds */
    public function closeForAgentRuns(array $runIds, string $note = 'Run ended before approval was decided.'): int
    {
        $ids = array_values(array_filter(array_map('strval', $runIds)));
        if ($ids === []) return 0;

        return Approval::where('status', 'pending')
            ->whereIn('run_id', $ids)
            ->update($this->terminalDecision($note));
    }

    /** @param list<string> $runIds */
    public function closeForWorkflowRuns(array $runIds, string $note = 'Workflow run ended before approval was decided.'): int
    {
        $ids = array_values(array_filter(array_map('strval', $runIds)));
        if ($ids === []) return 0;

        return Approval::where('status', 'pending')
            ->whereIn('workflow_run_id', $ids)
            ->update($this->terminalDecision($note));
    }

    public function closeStaleForWorkspace(int $workspaceId): int
    {
        $pending = Approval::where('workspace_id', $workspaceId)
            ->where('status', 'pending')
            ->get(['run_id', 'workflow_run_id']);
        if ($pending->isEmpty()) return 0;

        $agentRunIds = $pending->pluck('run_id')->filter()->map('strval')->values()->all();
        $workflowRunIds = $pending->pluck('workflow_run_id')->filter()->map('strval')->values()->all();

        $terminalAgentIds = $agentRunIds === [] ? [] : AgentRun::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $agentRunIds)
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->pluck('id')->map('strval')->all();
        $terminalWorkflowIds = $workflowRunIds === [] ? [] : WorkflowRun::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $workflowRunIds)
            ->whereIn('status', ['completed', 'failed', 'cancelled'])
            ->pluck('id')->map('strval')->all();

        $closed = $this->closeForAgentRuns($terminalAgentIds);
        $closed += $this->closeForWorkflowRuns($terminalWorkflowIds);

        return $closed;
    }

    /** @return array<string,mixed> */
    private function terminalDecision(string $note): array
    {
        return [
            'status' => 'denied',
            'decided_by' => null,
            'decision_note' => $note,
            'decided_at' => now(),
            'updated_at' => now(),
        ];
    }
}
