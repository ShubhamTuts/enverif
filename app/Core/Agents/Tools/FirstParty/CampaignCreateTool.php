<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\{AgentRun, Campaign, Lead};
use Illuminate\Support\Facades\DB;

final class CampaignCreateTool implements AgentTool
{
    private const CHANNELS = ['email', 'linkedin', 'call', 'research', 'webhook'];

    public function name(): string { return 'campaigns.create'; }
    public function description(): string { return 'Create a draft multi-step campaign from verified Enverif leads. This only builds the campaign and sequence; it never sends messages or starts external actions. Leads missing the contact route required by the sequence are marked as needing enrichment rather than queued.'; }
    public function risk(): RiskLevel { return RiskLevel::InternalWrite; }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'lead_ids' => ['type' => 'array', 'maxItems' => 200, 'items' => ['type' => 'integer']],
                'steps' => [
                    'type' => 'array',
                    'maxItems' => 20,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'channel' => ['type' => 'string', 'enum' => self::CHANNELS],
                            'action' => ['type' => 'string'],
                            'delay_minutes' => ['type' => 'integer'],
                            'content' => ['type' => 'string'],
                            'requires_approval' => ['type' => 'boolean'],
                        ],
                        'required' => ['channel', 'action'],
                    ],
                ],
            ],
            'required' => ['name', 'steps'],
        ];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $name = trim((string) ($arguments['name'] ?? ''));
        $description = trim((string) ($arguments['description'] ?? ''));
        $steps = array_values(array_filter((array) ($arguments['steps'] ?? []), 'is_array'));
        $leadIds = array_values(array_unique(array_filter(array_map('intval', (array) ($arguments['lead_ids'] ?? [])), fn (int $id): bool => $id > 0)));

        if ($name === '' || mb_strlen($name) > 140) return ToolExecutionResult::failure('Campaign name must contain 1–140 characters.');
        if (mb_strlen($description) > 2000) return ToolExecutionResult::failure('Campaign description cannot exceed 2,000 characters.');
        if ($steps === [] || count($steps) > 20) return ToolExecutionResult::failure('Campaign sequence must contain 1–20 steps.');
        if (count($leadIds) > 200) return ToolExecutionResult::failure('A campaign creation request is limited to 200 lead IDs.');

        $normalizedSteps = [];
        foreach ($steps as $index => $step) {
            $channel = strtolower(trim((string) ($step['channel'] ?? '')));
            $action = trim((string) ($step['action'] ?? ''));
            $delay = max(0, min(525600, (int) ($step['delay_minutes'] ?? 0)));
            $content = (string) ($step['content'] ?? '');
            if (! in_array($channel, self::CHANNELS, true)) return ToolExecutionResult::failure('Campaign step '.($index + 1).' has an unsupported channel.');
            if ($action === '' || mb_strlen($action) > 80) return ToolExecutionResult::failure('Campaign step '.($index + 1).' requires an action of at most 80 characters.');
            if (mb_strlen($content) > 30000) return ToolExecutionResult::failure('Campaign step '.($index + 1).' content exceeds 30,000 characters.');
            $normalizedSteps[] = [
                'position' => $index + 1,
                'channel' => $channel,
                'action' => $action,
                'delay_minutes' => $delay,
                'content' => ['template' => $content],
                'requires_approval' => array_key_exists('requires_approval', $step) ? (bool) $step['requires_approval'] : true,
            ];
        }

        $leads = $leadIds === [] ? collect() : Lead::where('workspace_id', $run->workspace_id)
            ->whereIn('id', $leadIds)
            ->get(['id', 'email', 'phone', 'linkedin_url']);
        $validLeadIds = $leads->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($validLeadIds);
        $expectedLeadIds = $leadIds;
        sort($expectedLeadIds);
        if ($validLeadIds !== $expectedLeadIds) return ToolExecutionResult::failure('One or more campaign lead IDs are unavailable in this workspace.');

        $channels = array_values(array_unique(array_column($normalizedSteps, 'channel')));
        $memberStatus = [];
        foreach ($leads as $lead) {
            $memberStatus[(int) $lead->id] = $this->isReachableForChannels($lead, $channels) ? 'queued' : 'needs_enrichment';
        }

        $campaign = DB::transaction(function () use ($run, $name, $description, $normalizedSteps, $validLeadIds, $memberStatus): Campaign {
            $campaign = Campaign::create([
                'workspace_id' => $run->workspace_id,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'status' => 'draft',
                'settings' => [],
            ]);
            foreach ($normalizedSteps as $step) $campaign->steps()->create($step);
            if ($validLeadIds !== []) {
                $payload = [];
                foreach ($validLeadIds as $leadId) {
                    $status = $memberStatus[$leadId] ?? 'needs_enrichment';
                    $payload[$leadId] = [
                        'status' => $status,
                        'current_step' => 0,
                        'next_action_at' => $status === 'queued' ? now() : null,
                    ];
                }
                $campaign->leads()->syncWithoutDetaching($payload);
            }
            return $campaign;
        });

        return ToolExecutionResult::success([
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'lead_ids' => $validLeadIds,
            'ready_lead_count' => count(array_filter($memberStatus, fn ($status) => $status === 'queued')),
            'needs_enrichment_count' => count(array_filter($memberStatus, fn ($status) => $status === 'needs_enrichment')),
            'member_statuses' => $memberStatus,
            'steps' => $campaign->steps()->get(['id', 'position', 'channel', 'action', 'delay_minutes', 'requires_approval'])->toArray(),
            'started' => false,
        ]);
    }

    /** @param list<string> $channels */
    private function isReachableForChannels(Lead $lead, array $channels): bool
    {
        foreach ($channels as $channel) {
            if ($channel === 'email' && trim((string) $lead->email) === '') return false;
            if ($channel === 'linkedin' && trim((string) $lead->linkedin_url) === '') return false;
            if ($channel === 'call' && trim((string) $lead->phone) === '') return false;
        }
        return true;
    }
}
