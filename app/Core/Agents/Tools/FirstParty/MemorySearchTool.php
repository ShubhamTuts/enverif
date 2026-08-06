<?php

namespace App\Core\Agents\Tools\FirstParty;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Models\AgentMemory;
use App\Models\AgentRun;

final class MemorySearchTool implements AgentTool
{
    public function name(): string { return 'memory.search'; }
    public function description(): string { return 'Search durable memory created for this agent across previous runs.'; }
    public function risk(): RiskLevel { return RiskLevel::Read; }
    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string'],
            'tag' => ['type' => 'string'],
            'limit' => ['type' => 'integer'],
        ]];
    }

    public function execute(AgentRun $run, array $arguments): ToolExecutionResult
    {
        $query = AgentMemory::query()
            ->where('workspace_id', $run->workspace_id)
            ->where('agent_id', $run->agent_id);
        if (!empty($arguments['query'])) {
            $term = (string) $arguments['query'];
            $query->where(fn ($q) => $q->where('key', 'like', "%{$term}%")->orWhere('value', 'like', "%{$term}%"));
        }
        if (!empty($arguments['tag'])) {
            $query->whereJsonContains('tags', (string) $arguments['tag']);
        }
        $memories = $query->orderByDesc('importance')->orderByDesc('updated_at')
            ->limit(min(50, max(1, (int) ($arguments['limit'] ?? 12))))
            ->get(['id', 'key', 'value', 'tags', 'importance', 'updated_at']);
        if ($memories->isNotEmpty()) {
            AgentMemory::whereIn('id', $memories->pluck('id'))->update(['last_used_at' => now()]);
        }
        return ToolExecutionResult::success($memories->toArray());
    }
}
