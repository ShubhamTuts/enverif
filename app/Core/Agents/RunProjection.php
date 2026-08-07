<?php

namespace App\Core\Agents;

use App\Models\{AgentRun, Approval};
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Durable, redacted read model for chat execution UX.
 *
 * It exposes observable persisted activity only: agents, tool calls, statuses and
 * approvals. Model reasoning and raw tool payloads/results are intentionally absent.
 */
final class RunProjection
{
    /** @return array<string,mixed> */
    public function forRun(?string $rootRunId): array
    {
        $empty = ['root_run_id' => $rootRunId, 'runs' => [], 'events' => [], 'pending_approvals' => [], 'pending_approval_count' => 0];
        if (!$rootRunId) return $empty;

        $root = AgentRun::query()->whereKey($rootRunId)->first();
        if (!$root) return $empty;

        $runs = collect([$root]);
        $frontier = [$root->id];
        while ($frontier !== []) {
            $children = AgentRun::query()
                ->whereIn('parent_run_id', $frontier)
                ->orderBy('created_at')
                ->get();
            $new = $children->whereNotIn('id', $runs->pluck('id'))->values();
            if ($new->isEmpty()) break;
            $runs = $runs->concat($new);
            $frontier = $new->pluck('id')->all();
        }

        $runs->each(fn (AgentRun $run) => $run->loadMissing(['agent', 'steps']));
        $approvals = Approval::query()->whereIn('run_id', $runs->pluck('id')->all())->orderBy('created_at')->get();
        $approvalsByRun = $approvals->groupBy('run_id');

        $nodes = [];
        foreach ($runs as $run) {
            $nodes[(string) $run->id] = $this->runNode($run, $approvalsByRun->get($run->id, collect()));
        }
        foreach ($nodes as $id => &$node) {
            $parentId = $node['parent_run_id'];
            if ($parentId && isset($nodes[$parentId])) $nodes[$parentId]['children'][] = $id;
        }
        unset($node);

        $events = [];
        foreach ($nodes as $node) {
            $events[] = [
                'id' => 'run:'.$node['id'],
                'type' => 'agent',
                'run_id' => $node['id'],
                'label' => $node['agent_name'].' '.($this->terminal($node['status']) ? $node['status'] : 'started'),
                'status' => $node['status'],
                'at' => $node['started_at'] ?: $node['created_at'],
            ];
            foreach ($node['steps'] as $step) {
                $events[] = [
                    'id' => 'step:'.$step['id'],
                    'type' => 'step',
                    'run_id' => $node['id'],
                    'label' => $step['label'],
                    'status' => $step['status'],
                    'risk' => $step['risk_level'],
                    'at' => $step['started_at'] ?: $step['created_at'],
                ];
            }
        }
        foreach ($approvals as $approval) {
            $events[] = [
                'id' => 'approval:'.$approval->id,
                'type' => 'approval',
                'run_id' => $approval->run_id,
                'label' => (string) ($approval->summary ?: 'Approval required for '.$approval->action),
                'status' => $approval->status,
                'risk' => $approval->risk_level,
                'at' => $approval->created_at?->toIso8601String(),
            ];
        }
        usort($events, fn (array $a, array $b) => strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? '')));

        $pending = $approvals->where('status', 'pending')->map(fn (Approval $approval) => $this->approval($approval))->values()->all();

        return [
            'root_run_id' => (string) $root->id,
            'runs' => array_values($nodes),
            'events' => $events,
            'pending_approvals' => $pending,
            'pending_approval_count' => count($pending),
        ];
    }

    /** @param Collection<int,Approval> $approvals @return array<string,mixed> */
    private function runNode(AgentRun $run, Collection $approvals): array
    {
        $agentName = (string) ($run->agent?->name ?: data_get($run->context, 'agent_snapshot.name', 'Agent'));
        return [
            'id' => (string) $run->id,
            'parent_run_id' => $run->parent_run_id ? (string) $run->parent_run_id : null,
            'agent_id' => (int) $run->agent_id,
            'agent_name' => $agentName,
            'status' => (string) $run->status,
            'stop_reason' => $run->stop_reason,
            'created_at' => $run->created_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'url' => route('runs.show', $run),
            'children' => [],
            'steps' => $run->steps->map(function ($step): array {
                $tool = trim((string) $step->tool);
                return [
                    'id' => (int) $step->id,
                    'sequence' => (int) $step->sequence,
                    'type' => (string) $step->type,
                    'tool' => $tool !== '' ? $tool : null,
                    'label' => $tool !== '' ? $this->humanizeTool($tool) : ucfirst((string) $step->type),
                    'risk_level' => $step->risk_level,
                    'status' => (string) $step->status,
                    'created_at' => $step->created_at?->toIso8601String(),
                    'started_at' => $step->started_at?->toIso8601String(),
                    'finished_at' => $step->finished_at?->toIso8601String(),
                ];
            })->values()->all(),
            'approvals' => $approvals->map(fn (Approval $approval) => $this->approval($approval))->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function approval(Approval $approval): array
    {
        return [
            'id' => (int) $approval->id,
            'run_id' => $approval->run_id,
            'workflow_run_id' => $approval->workflow_run_id,
            'action' => (string) $approval->action,
            'risk_level' => (string) $approval->risk_level,
            'summary' => Str::limit((string) ($approval->summary ?: $approval->action), 500, '…'),
            'status' => (string) $approval->status,
            'payload' => $this->redact((array) ($approval->payload ?? [])),
            'created_at' => $approval->created_at?->toIso8601String(),
            'decided_at' => $approval->decided_at?->toIso8601String(),
            'decide_url' => route('approvals.decide', $approval),
        ];
    }

    private function humanizeTool(string $tool): string
    {
        $parts = explode('.', $tool);
        if (($parts[0] ?? '') === 'connector' && count($parts) >= 3) {
            return 'Connector · '.Str::headline(implode(' ', array_slice($parts, 2)));
        }
        if (($parts[0] ?? '') === 'mcp' && count($parts) >= 3) {
            return 'MCP · '.Str::headline(implode(' ', array_slice($parts, 2)));
        }
        return Str::headline(str_replace('.', ' ', $tool));
    }

    private function terminal(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'cancelled'], true);
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key && preg_match('/(?:password|passphrase|secret|token|api[_-]?key|authorization|cookie|credential)/i', $key)) {
            return '[redacted]';
        }
        if (!is_array($value)) return is_string($value) ? Str::limit($value, 1000, '…') : $value;

        $safe = [];
        foreach ($value as $childKey => $child) $safe[$childKey] = $this->redact($child, (string) $childKey);
        return $safe;
    }
}
