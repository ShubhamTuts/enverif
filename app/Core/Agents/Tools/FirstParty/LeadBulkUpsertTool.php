<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentRun;
use Illuminate\Support\Facades\DB;

final class LeadBulkUpsertTool implements AgentTool
{
    public function name(): string { return 'leads.upsert_many'; }
    public function description(): string { return 'Persist a researched batch of up to 100 evidence-grounded leads into Enverif Leads, deduplicating by ID, email, LinkedIn URL or website.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'leads' => [
                    'type' => 'array',
                    'maxItems' => 100,
                    'items' => (new LeadUpsertTool)->parameters(),
                ],
            ],
            'required' => ['leads'],
        ];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $rows = array_values(array_filter((array) ($arguments['leads'] ?? []), 'is_array'));
        if ($rows === []) {
            return ToolExecutionResult::failure('leads must contain at least one lead.');
        }
        if (count($rows) > 100) {
            return ToolExecutionResult::failure('A single bulk lead write is limited to 100 leads.');
        }

        $writer = new LeadUpsertTool;
        $saved = DB::transaction(function () use ($writer, $run, $rows): array {
            $out = [];
            foreach ($rows as $row) {
                $lead = $writer->upsert($run, $row);
                $out[] = $lead->only(['id', 'first_name', 'last_name', 'email', 'title', 'company', 'website', 'status', 'score', 'source']);
            }
            return $out;
        });

        return ToolExecutionResult::success([
            'count' => count($saved),
            'leads' => $saved,
        ]);
    }
}
