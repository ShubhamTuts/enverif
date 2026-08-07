<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentRun;
use App\Models\Lead;

final class LeadUpsertTool implements AgentTool
{
    public function name(): string { return 'leads.upsert'; }
    public function description(): string { return 'Create or update a lead in the current Enverif workspace. Use only evidence-grounded fields. Existing leads are matched by ID, email, LinkedIn URL or website before a new record is created.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => ['type' => 'integer'],
            'email' => ['type' => 'string'],
            'first_name' => ['type' => 'string'],
            'last_name' => ['type' => 'string'],
            'phone' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'company' => ['type' => 'string'],
            'website' => ['type' => 'string'],
            'linkedin_url' => ['type' => 'string'],
            'city' => ['type' => 'string'],
            'country' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'score' => ['type' => 'integer'],
            'source' => ['type' => 'string'],
            'source_url' => ['type' => 'string'],
            'research_summary' => ['type' => 'string'],
            'data' => ['type' => 'object'],
        ]];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $lead = $this->upsert($run, $arguments);

        return ToolExecutionResult::success($lead->only([
            'id', 'first_name', 'last_name', 'email', 'title', 'company', 'website',
            'linkedin_url', 'status', 'score', 'source', 'source_url',
        ]));
    }

    public function upsert(AgentRun $run, array $arguments): Lead
    {
        $id = (int) ($arguments['id'] ?? 0);
        unset($arguments['id']);

        $allowed = array_flip((new Lead)->getFillable());
        unset($allowed['workspace_id']);
        $fields = array_intersect_key($arguments, $allowed);

        foreach (['email', 'website', 'linkedin_url'] as $key) {
            if (isset($fields[$key]) && is_string($fields[$key])) {
                $fields[$key] = trim($fields[$key]);
            }
        }
        if (! empty($fields['email'])) {
            $fields['email'] = strtolower((string) $fields['email']);
        }
        if (isset($fields['score'])) {
            $fields['score'] = max(0, min(100, (int) $fields['score']));
        }

        $lead = null;
        if ($id > 0) {
            $lead = Lead::where('workspace_id', $run->workspace_id)->findOrFail($id);
        } elseif (! empty($fields['email'])) {
            $lead = Lead::where('workspace_id', $run->workspace_id)
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $fields['email'])])
                ->first();
        } elseif (! empty($fields['linkedin_url'])) {
            $lead = Lead::where('workspace_id', $run->workspace_id)
                ->where('linkedin_url', (string) $fields['linkedin_url'])
                ->first();
        } elseif (! empty($fields['website'])) {
            $lead = Lead::where('workspace_id', $run->workspace_id)
                ->where('website', (string) $fields['website'])
                ->first();
        }

        if ($lead) {
            $lead->update($fields);
            return $lead->refresh();
        }

        $fields['workspace_id'] = $run->workspace_id;
        return Lead::create($fields);
    }
}
