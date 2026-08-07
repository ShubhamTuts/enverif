<?php

namespace App\Core\Plugins;

use App\Models\{Agent, AgentSchedule, ConnectorConnection, Workflow};
use App\Support\WorkspaceContext;

final class PluginDependencyInspector
{
    public function __construct(
        private readonly PluginRegistry $plugins,
        private readonly WorkspaceContext $workspace,
    ) {}

    /** @return array<string,mixed> */
    public function forConnection(ConnectorConnection $connection): array
    {
        $workspaceId = (int) $connection->workspace_id;

        return $this->workspace->run($workspaceId, function () use ($connection): array {
            $agentIds = Agent::query()
                ->whereHas('connectors', fn ($query) => $query->where('connector_connections.id', $connection->id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $agents = $agentIds === [] ? collect() : Agent::query()->whereIn('id', $agentIds)->orderBy('name')->get(['id','name','status']);

            $workflows = Workflow::query()->get(['id','name','status','definition'])
                ->filter(fn (Workflow $workflow): bool => $this->containsConnection((array) $workflow->definition, (int) $connection->id))
                ->values();
            $workflowIds = $workflows->pluck('id')->map(fn ($id) => (int) $id)->all();

            $schedules = AgentSchedule::query()
                ->where(function ($query) use ($agentIds, $workflowIds): void {
                    if ($agentIds !== []) $query->whereIn('agent_id', $agentIds);
                    if ($workflowIds !== []) {
                        if ($agentIds !== []) $query->orWhereIn('workflow_id', $workflowIds);
                        else $query->whereIn('workflow_id', $workflowIds);
                    }
                    if ($agentIds === [] && $workflowIds === []) $query->whereRaw('1 = 0');
                })
                ->orderBy('name')
                ->get(['id','name','enabled','agent_id','workflow_id']);

            return [
                'agents' => $agents->map(fn (Agent $agent) => ['id'=>$agent->id,'name'=>$agent->name,'status'=>$agent->status])->values()->all(),
                'workflows' => $workflows->map(fn (Workflow $workflow) => ['id'=>$workflow->id,'name'=>$workflow->name,'status'=>$workflow->status])->values()->all(),
                'schedules' => $schedules->map(fn (AgentSchedule $schedule) => ['id'=>$schedule->id,'name'=>$schedule->name,'enabled'=>(bool)$schedule->enabled])->values()->all(),
                'blocking_count' => $agents->count() + $workflows->count(),
            ];
        });
    }

    /** @return array<string,mixed> */
    public function forPlugin(string $slug): array
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new \InvalidArgumentException('Invalid plugin slug.');
        $driverIds = [];
        foreach ($this->plugins->connectorDrivers() as $id => $driver) {
            $meta = $this->plugins->metadata((string) $id);
            if ((string) ($meta['slug'] ?? '') === $slug) $driverIds[] = (string) $id;
        }
        if ($driverIds === []) {
            $manifest = $this->plugins->metadata($slug);
            if ($manifest === []) throw new \InvalidArgumentException('Plugin not found.');
        }

        // Plugin uninstall is an explicit installation-wide administrative operation.
        // Inspect every workspace without weakening normal fail-closed tenant scopes.
        $connections = $driverIds === []
            ? collect()
            : ConnectorConnection::withoutGlobalScopes()
                ->whereIn('driver', array_values(array_unique($driverIds)))
                ->orderBy('workspace_id')
                ->orderBy('name')
                ->get();
        $items = [];
        $blocking = 0;
        foreach ($connections as $connection) {
            $dependencies = $this->forConnection($connection);
            $blocking += 1 + (int) $dependencies['blocking_count'];
            $items[] = [
                'id' => $connection->id,
                'workspace_id' => $connection->workspace_id,
                'name' => $connection->name,
                'driver' => $connection->driver,
                'dependencies' => $dependencies,
            ];
        }

        return [
            'connections' => $items,
            'blocking_count' => $blocking,
        ];
    }

    private function containsConnection(mixed $value, int $connectionId, ?string $key = null): bool
    {
        if (!is_array($value)) {
            return in_array($key, ['connection_id','connector_id','connector_connection_id'], true)
                && (int) $value === $connectionId;
        }
        foreach ($value as $childKey => $child) {
            if ($this->containsConnection($child, $connectionId, is_string($childKey) ? $childKey : null)) return true;
        }
        return false;
    }
}
