<?php

namespace App\Core\Agents\Tools;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Core\Agents\Tools\FirstParty\{AgentListTool, DelegateAgentTool, LeadActivityTool, LeadSearchTool, LeadUpsertTool, MemoryForgetTool, MemoryRememberTool, MemorySearchTool};
use App\Core\Connectors\ConnectorManager;
use App\Core\Mcp\McpManager;
use App\Models\{Agent, AgentRun, ConnectorConnection, McpServer};

final class ToolRegistry
{
    /** @var array<string,AgentTool> */
    private array $local = [];

    public function __construct(private readonly ConnectorManager $connectors, private readonly McpManager $mcp)
    {
        foreach ([new AgentListTool, new DelegateAgentTool, new LeadSearchTool, new LeadUpsertTool, new LeadActivityTool, new MemorySearchTool, new MemoryRememberTool, new MemoryForgetTool] as $tool) {
            $this->local[$tool->name()] = $tool;
        }
    }

    /** @param list<int> $extraConnectorIds @param list<array<string,mixed>>|null $attachedSnapshots @return list<array<string,mixed>> */
    public function definitions(Agent $agent, array $extraConnectorIds = [], ?array $attachedSnapshots = null): array
    {
        $defs = [];
        foreach ($this->local as $tool) {
            $defs[] = ['name' => $tool->name(), 'description' => $tool->description(), 'risk' => $tool->risk()->value, 'parameters' => $tool->parameters()];
        }

        $snapshotAllowed = [];
        if ($attachedSnapshots !== null) {
            $attachedIds = [];
            foreach ($attachedSnapshots as $snapshot) {
                if (!is_array($snapshot) || (int) ($snapshot['id'] ?? 0) <= 0) continue;
                $id = (int) $snapshot['id'];
                $attachedIds[] = $id;
                $snapshotAllowed[$id] = is_array($snapshot['allowed_actions'] ?? null) ? array_values($snapshot['allowed_actions']) : null;
            }
            $attached = $attachedIds
                ? ConnectorConnection::where('workspace_id', $agent->workspace_id)->where('enabled', true)->whereIn('id', $attachedIds)->get()
                : collect();
        } else {
            $attached = $agent->connectors()->where('connector_connections.enabled', true)->get();
        }
        $extra = $extraConnectorIds
            ? ConnectorConnection::where('workspace_id', $agent->workspace_id)->where('enabled', true)->whereIn('id', array_map('intval', $extraConnectorIds))->get()
            : collect();
        foreach ($attached->concat($extra)->unique('id') as $connection) {
            $driver = $this->connectors->get($connection->driver);
            $isExtra = in_array((int) $connection->id, array_map('intval', $extraConnectorIds), true);
            $allowed = $attachedSnapshots !== null && !$isExtra
                ? ($snapshotAllowed[(int) $connection->id] ?? null)
                : $connection->pivot?->allowed_actions;
            if (is_string($allowed)) $allowed = json_decode($allowed, true);
            foreach ($driver->actions() as $action) {
                if (is_array($allowed) && $allowed !== [] && !in_array($action->name, $allowed, true)) continue;
                $defs[] = $action->toTool('connector.' . $connection->id);
            }
        }

        foreach (McpServer::where('workspace_id', $agent->workspace_id)->where('enabled', true)->get() as $server) {
            try {
                foreach ((new \App\Core\Mcp\McpClient($server))->tools() as $tool) {
                    $annotations = $tool['annotations'] ?? [];
                    $risk = ($annotations['destructiveHint'] ?? false)
                        ? RiskLevel::Destructive
                        : (($annotations['readOnlyHint'] ?? false) ? RiskLevel::Read : RiskLevel::ExternalWrite);
                    $defs[] = [
                        'name' => 'mcp.' . $server->id . '.' . ($tool['name'] ?? 'tool'),
                        'description' => (string) ($tool['description'] ?? 'MCP tool'),
                        'risk' => $risk->value,
                        'parameters' => $tool['inputSchema'] ?? ['type' => 'object', 'properties' => []],
                    ];
                }
            } catch (\Throwable) {
                // A temporarily unavailable MCP server should not prevent the agent from using other tools.
            }
        }
        return $defs;
    }

    /** @param list<int> $extraConnectorIds @param list<array<string,mixed>>|null $attachedSnapshots */
    public function risk(Agent $agent, string $name, array $extraConnectorIds = [], ?array $attachedSnapshots = null): RiskLevel
    {
        foreach ($this->definitions($agent, $extraConnectorIds, $attachedSnapshots) as $definition) {
            if ($definition['name'] === $name) return RiskLevel::from($definition['risk']);
        }
        return RiskLevel::Destructive;
    }

    public function execute(AgentRun $run, string $name, array $arguments): ToolExecutionResult
    {
        if (isset($this->local[$name])) return $this->local[$name]->execute($run, $arguments);

        if (str_starts_with($name, 'connector.')) {
            [, $id, $action] = array_pad(explode('.', $name, 3), 3, null);
            $connectorId = (int) $id;
            $allowedIds = array_map('intval', (array) data_get($run->context, 'selected_connector_ids', []));
            $snapshotConnectors = data_get($run->context, 'agent_snapshot.connectors');
            $attached = false;
            $attachedAllowed = null;
            if (is_array($snapshotConnectors)) {
                foreach ($snapshotConnectors as $snapshot) {
                    if (!is_array($snapshot) || (int) ($snapshot['id'] ?? 0) !== $connectorId) continue;
                    $attached = true;
                    $attachedAllowed = is_array($snapshot['allowed_actions'] ?? null) ? array_values($snapshot['allowed_actions']) : null;
                    break;
                }
            } else {
                $attachedConnection = $run->agent->connectors()->whereKey($connectorId)->where('connector_connections.enabled', true)->first();
                $attached = $attachedConnection !== null;
                if ($attachedConnection) {
                    $attachedAllowed = $attachedConnection->pivot?->allowed_actions;
                    if (is_string($attachedAllowed)) $attachedAllowed = json_decode($attachedAllowed, true);
                }
            }
            $tagged = in_array($connectorId, $allowedIds, true);
            if (!$attached && !$tagged) {
                return ToolExecutionResult::failure('Connector is not attached to this agent or explicitly tagged for this run.');
            }
            if ($attached && !$tagged && is_array($attachedAllowed) && $attachedAllowed !== [] && !in_array((string) $action, $attachedAllowed, true)) {
                return ToolExecutionResult::failure('Connector action is not allowed by this agent run snapshot.');
            }
            $connection = ConnectorConnection::where('workspace_id', $run->workspace_id)->where('enabled', true)->findOrFail($connectorId);
            $result = $this->connectors->get($connection->driver)->execute($connection, (string) $action, $arguments);
            return new ToolExecutionResult($result->ok, $result->data, $result->message);
        }

        if (str_starts_with($name, 'mcp.')) {
            [, $serverId, $tool] = array_pad(explode('.', $name, 3), 3, null);
            return ToolExecutionResult::success($this->mcp->call((int) $serverId, (string) $tool, $arguments));
        }

        return ToolExecutionResult::failure('Unknown tool: ' . $name);
    }
}
