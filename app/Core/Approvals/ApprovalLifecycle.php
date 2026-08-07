<?php

namespace App\Core\Approvals;

use App\Models\Approval;

final class ApprovalLifecycle
{
    /** @param list<string> $runIds */
    public function closeForAgentRuns(array $runIds, string $note = 'Run ended before approval was decided.'): int
    {
        $ids = array_values(array_filter(array_map('strval', $runIds)));
        if ($ids === []) return 0;

        return Approval::where('status', 'pending')
            ->whereIn('run_id', $ids)
            ->update([
                'status' => 'denied',
                'decided_by' => null,
                'decision_note' => $note,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** @param list<string> $runIds */
    public function closeForWorkflowRuns(array $runIds, string $note = 'Workflow run ended before approval was decided.'): int
    {
        $ids = array_values(array_filter(array_map('strval', $runIds)));
        if ($ids === []) return 0;

        return Approval::where('status', 'pending')
            ->whereIn('workflow_run_id', $ids)
            ->update([
                'status' => 'denied',
                'decided_by' => null,
                'decision_note' => $note,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
