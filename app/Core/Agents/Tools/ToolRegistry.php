<?php

namespace App\Core\Agents\Tools;

use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Execution\ExternalActionExecutor;
use App\Core\Agents\Tools\Contracts\AgentTool;
use App\Core\Agents\Tools\DTO\ToolExecutionResult;
use App\Core\Agents\Tools\FirstParty\{AgentListTool, CampaignCreateTool, DelegateAgentTool, LeadActivityTool, LeadBulkUpsertTool, LeadSearchTool, LeadUpsertTool, MemoryForgetTool, MemoryRememberTool, MemorySearchTool, ScheduleListTool, ScheduleUpsertTool};
use App\Core\Connectors\ConnectorManager;
use App\Core\Mcp\McpManager;
use App\Core\Models\ToolSchemaNormalizer;
use App\Models\{Agent, AgentRun, ConnectorConnection, McpServer};

final class ToolRegistry
{
    /** @var array<string,AgentTool> */
    private array $local = [];

    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly McpManager $mcp,
        private readonly ExternalActionExecutor $externalActions,
    ) {
        foreach ([
            new AgentListTool,
            new DelegateAgentTool,
            new LeadSearchTool,
            new LeadUpsertTool,
            new LeadBulkUpsertTool,
            new LeadActivityTool,
            new CampaignCreateTool,
            new ScheduleListTool,
            new ScheduleUpsertTool,
            new MemorySearchTool,
            new MemoryRememberTool,
            new MemoryForgetTool,
        ] as $tool) $this->local[$tool->name()] = $tool;
    }

    /**
     * @param list<int> $extraConnectorIds
     * @param list<array<string,mixed>>|null $attachedSnapshots
     * @param array<string,mixed>|null $agentSettingsSnapshot
     * @return list<array<string,mixed>>
     */
    public function definitions(
        Agent $agent,
        array $extraConnectorIds = [],
        ?array $attachedSnapshots = null,
        ?array $agentSettingsSnapshot = null,
    ): array {
        $defs = [];
        foreach ($this->local as $tool) {
            $defs[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'risk' => $tool->risk()->value,
                'parameters' => ToolSchemaNormalizer::parameters($tool->parameters()),
                'capabilities' => [],
            ];
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
            $isExtra = in_array((int) $connection->id, array_map('intval', $extraConnectorIds), true);
            $allowed = $attachedSnapshots !== null && !$isExtra
                ? ($snapshotAllowed[(int) $connection->id] ?? null)
                : $connection->pivot?->allowed_actions;
            if (is_string($allowed)) $allowed = json_decode($allowed, true);
            foreach ($this->connectors->actionsFor($connection) as $action) {
                if (is_array($allowed) && $allowed !== [] && !in_array($action->name, $allowed, true)) continue;
                $defs[] = $action->toTool('connector.' . $connection->id);
            }
        }

        $mcpIds = $this->effectiveMcpServerIds($agent, $agentSettingsSnapshot);
        $mcpServers = $mcpIds === []
            ? collect()
            : McpServer::where('workspace_id', $agent->workspace_id)
                ->where('enabled', true)
                ->whereIn('id', $mcpIds)
                ->get();

        foreach ($mcpServers as $server) {
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
                        'parameters' => ToolSchemaNormalizer::parameters($tool['inputSchema'] ?? null),
                        'capabilities' => [],
                    ];
                }
            } catch (\Throwable) {
                // An unavailable assigned MCP server must not block other tools.
            }
        }
        return $defs;
    }

    /**
     * @param list<int> $extraConnectorIds
     * @param list<array<string,mixed>>|null $attachedSnapshots
     * @param array<string,mixed>|null $agentSettingsSnapshot
     */
    public function risk(
        Agent $agent,
        string $name,
        array $extraConnectorIds = [],
        ?array $attachedSnapshots = null,
        ?array $agentSettingsSnapshot = null,
    ): RiskLevel {
        foreach ($this->definitions($agent, $extraConnectorIds, $attachedSnapshots, $agentSettingsSnapshot) as $definition) {
            if ($definition['name'] === $name) return RiskLevel::from($definition['risk']);
        }
        return RiskLevel::Destructive;
    }

    public function execute(AgentRun $run, string $name, array $arguments): ToolExecutionResult
    {
        $step = $run->steps()->where('status', 'running')->where('tool', $name)->orderBy('sequence')->first();
        $snapshotSettings = data_get($run->context, 'agent_snapshot.settings');
        $risk = $step && $step->risk_level
            ? RiskLevel::from((string) $step->risk_level)
            : $this->risk(
                $run->agent,
                $name,
                array_map('intval', (array) data_get($run->context, 'selected_connector_ids', [])),
                (array) data_get($run->context, 'agent_snapshot.connectors', []),
                is_array($snapshotSettings) ? $snapshotSettings : null,
            );

        if (!$this->isMutation($risk)) return $this->executeDirect($run, $name, $arguments);

        $stepKey = $step ? (string) $step->id : 'tool-' . hash('sha256', $name . '|' . json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $normalized = $this->externalActions->execute(
            (int) $run->workspace_id,
            'agent',
            (string) $run->id,
            $stepKey,
            $name,
            $arguments,
            function () use ($run, $name, $arguments): array {
                $result = $this->executeDirect($run, $name, $arguments);
                return ['ok' => $result->ok, 'data' => $result->data, 'message' => $result->message];
            },
        );

        return new ToolExecutionResult(
            (bool) ($normalized['ok'] ?? false),
            $normalized['data'] ?? null,
            isset($normalized['message']) ? (string) $normalized['message'] : null,
        );
    }

    private function executeDirect(AgentRun $run, string $name, array $arguments): ToolExecutionResult
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
            if (!$attached && !$tagged) return ToolExecutionResult::failure('Connector is not attached to this agent or explicitly tagged for this run.');
            if ($attached && !$tagged && is_array($attachedAllowed) && $attachedAllowed !== [] && !in_array((string) $action, $attachedAllowed, true)) {
                return ToolExecutionResult::failure('Connector action is not allowed by this agent run snapshot.');
            }
            $connection = ConnectorConnection::where('workspace_id', $run->workspace_id)->where('enabled', true)->findOrFail($connectorId);
            $available = collect($this->connectors->actionsFor($connection))->contains(fn ($candidate) => $candidate->name === (string) $action);
            if (!$available) return ToolExecutionResult::failure('Connector action is unavailable for the current connection configuration.');
            $result = $this->connectors->get($connection->driver)->execute($connection, (string) $action, $arguments);
            return new ToolExecutionResult($result->ok, $result->data, $result->message);
        }

        if (str_starts_with($name, 'mcp.')) {
            [, $serverId, $tool] = array_pad(explode('.', $name, 3), 3, null);
            $serverId = (int) $serverId;
            $snapshotSettings = data_get($run->context, 'agent_snapshot.settings');
            $allowedIds = $this->effectiveMcpServerIds(
                $run->agent,
                is_array($snapshotSettings) ? $snapshotSettings : null,
            );
            if (!in_array($serverId, $allowedIds, true)) {
                return ToolExecutionResult::failure('MCP server is not assigned to this agent run.');
            }
            $server = McpServer::where('workspace_id', $run->workspace_id)->where('enabled', true)->find($serverId);
            if (!$server) return ToolExecutionResult::failure('MCP server is unavailable in this workspace.');

            return ToolExecutionResult::success($this->mcp->call($serverId, (string) $tool, $arguments));
        }

        return ToolExecutionResult::failure('Unknown tool: ' . $name);
    }

    /**
     * Legacy agents without an explicit key retain their previous workspace-wide
     * effective MCP access. New/edited agents store the key, including an empty list.
     *
     * @param array<string,mixed>|null $settingsSnapshot
     * @return list<int>
     */
    private function effectiveMcpServerIds(Agent $agent, ?array $settingsSnapshot = null): array
    {
        $settings = $settingsSnapshot ?? (array) ($agent->settings ?? []);
        if (array_key_exists('mcp_server_ids', $settings)) {
            $requested = array_values(array_unique(array_map('intval', (array) $settings['mcp_server_ids'])));
            if ($requested === []) return [];

            return McpServer::where('workspace_id', $agent->workspace_id)
                ->where('enabled', true)
                ->whereIn('id', $requested)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return McpServer::where('workspace_id', $agent->workspace_id)
            ->where('enabled', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isMutation(RiskLevel $risk): bool
    {
        return in_array($risk, [RiskLevel::InternalWrite, RiskLevel::ExternalWrite, RiskLevel::Destructive, RiskLevel::Secrets], true);
    }
}
