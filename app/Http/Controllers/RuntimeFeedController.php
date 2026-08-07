<?php

namespace App\Http\Controllers;

use App\Models\{AgentRun, Approval, ChatMessage, ChatThread};
use Illuminate\Http\Request;

final class RuntimeFeedController extends Controller
{
    public function __invoke(Request $request)
    {
        $workspaceId = (int) session('workspace_id');
        $threads = ChatThread::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get(['id', 'title', 'last_message_at']);

        $threadIds = $threads->pluck('id')->all();
        $latestMessages = $threadIds === []
            ? collect()
            : ChatMessage::query()
                ->whereIn('thread_id', $threadIds)
                ->where('role', 'user')
                ->whereNotNull('run_id')
                ->orderByDesc('id')
                ->get(['id', 'thread_id', 'run_id'])
                ->unique('thread_id')
                ->keyBy('thread_id');

        $runIds = $latestMessages->pluck('run_id')->filter()->unique()->values()->all();
        $runs = $runIds === []
            ? collect()
            : AgentRun::query()->with('agent:id,name')->whereIn('id', $runIds)->get()->keyBy('id');

        // Resolve descendants in batches so approvals created by sub-agents are surfaced
        // on the parent chat without loading a full RunProjection for every feed row.
        $runToRoot = [];
        foreach ($runIds as $runId) $runToRoot[(string) $runId] = (string) $runId;
        $allRunIds = array_map('strval', $runIds);
        $frontier = $allRunIds;
        while ($frontier !== []) {
            $children = AgentRun::query()
                ->whereIn('parent_run_id', $frontier)
                ->get(['id', 'parent_run_id']);
            $next = [];
            foreach ($children as $child) {
                $childId = (string) $child->id;
                if (isset($runToRoot[$childId])) continue;
                $parentId = (string) $child->parent_run_id;
                $rootId = $runToRoot[$parentId] ?? null;
                if (! $rootId) continue;
                $runToRoot[$childId] = $rootId;
                $allRunIds[] = $childId;
                $next[] = $childId;
            }
            $frontier = $next;
        }

        $approvalCounts = [];
        if ($allRunIds !== []) {
            foreach (Approval::query()->whereIn('run_id', $allRunIds)->where('status', 'pending')->get(['run_id']) as $approval) {
                $rootId = $runToRoot[(string) $approval->run_id] ?? null;
                if ($rootId) $approvalCounts[$rootId] = ($approvalCounts[$rootId] ?? 0) + 1;
            }
        }

        $items = [];
        foreach ($threads as $thread) {
            $message = $latestMessages->get($thread->id);
            $run = $message ? $runs->get($message->run_id) : null;
            if (! $run) continue;

            $agent = $run->agent;
            $runId = (string) $run->id;
            $items[] = [
                'thread_id' => $thread->id,
                'thread_url' => route('chat.show', $thread),
                'activity_url' => route('chat.activity', $thread),
                'title' => $thread->title,
                'run_id' => $run->id,
                'run_url' => route('runs.show', $run),
                'agent_id' => (int) $run->agent_id,
                'agent_name' => (string) ($agent?->name ?: data_get($run->context, 'agent_snapshot.name', 'Agent')),
                'agent_avatar_url' => $agent ? route('agents.avatar', $agent) : null,
                'status' => (string) $run->status,
                'approval_count' => (int) ($approvalCounts[$runId] ?? 0),
                'updated_at' => ($run->finished_at ?: $run->started_at ?: $thread->last_message_at)?->toIso8601String(),
            ];
        }

        $activeCount = collect($items)
            ->reject(fn (array $item) => in_array($item['status'], ['completed', 'failed', 'cancelled'], true))
            ->count();

        return response()->json([
            'threads' => $items,
            'summary' => [
                'active_count' => $activeCount,
                'approval_count' => array_sum($approvalCounts),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
