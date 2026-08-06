<?php

namespace App\Core\Workflows;

use App\Core\Connectors\ConnectorManager;
use App\Models\{Agent,Campaign,ConnectorConnection,ModelConnection,Skill,Workflow};

final class WorkflowRuntimeValidator
{
    public function __construct(private readonly ConnectorManager $connectors) {}

    /** @return list<string> */
    public function validate(Workflow $workflow): array
    {
        $definition = WorkflowDefinitionValidator::validate((array) $workflow->definition);
        $errors = [];
        foreach ($definition['nodes'] as $node) {
            $id = (string) $node['id'];
            $label = (string) ($node['label'] ?? $id);
            $config = (array) ($node['config'] ?? []);
            try {
                switch ($node['type']) {
                    case 'agent':
                        $agent = Agent::whereKey((int) ($config['agent_id'] ?? 0))->where('status','active')->first();
                        if (!$agent) {
                            $errors[] = "{$label}: select an active agent.";
                            break;
                        }
                        if (!$agent->model_connection_id || !ModelConnection::whereKey((int) $agent->model_connection_id)->where('enabled', true)->exists()) {
                            $errors[] = "{$label}: agent model connection is missing or disabled.";
                        }
                        break;
                    case 'skill':
                        $skill = Skill::whereKey((int) ($config['skill_id'] ?? 0))
                            ->where(fn ($query) => $query->whereNull('workspace_id')->orWhere('workspace_id', $workflow->workspace_id))
                            ->where('status', 'active')
                            ->first();
                        if (!$skill) $errors[] = "{$label}: select an active skill available to this workspace.";

                        if (!empty($config['agent_id'])) {
                            $executor = Agent::whereKey((int) $config['agent_id'])->where('status','active')->first();
                            if (!$executor) {
                                $errors[] = "{$label}: selected skill executor agent is unavailable.";
                            } elseif (!$executor->model_connection_id || !ModelConnection::whereKey((int) $executor->model_connection_id)->where('enabled', true)->exists()) {
                                $errors[] = "{$label}: selected skill executor agent model connection is missing or disabled.";
                            }
                        } else {
                            $readyExecutor = Agent::where('status', 'active')
                                ->whereNotNull('model_connection_id')
                                ->whereHas('modelConnection', fn ($query) => $query->where('enabled', true))
                                ->exists();
                            if (!$readyExecutor) {
                                $errors[] = "{$label}: skill nodes require at least one active agent with an enabled model connection.";
                            }
                        }
                        break;
                    case 'connector':
                        $connection = ConnectorConnection::whereKey((int) ($config['connection_id'] ?? 0))->where('enabled',true)->first();
                        if (!$connection) { $errors[] = "{$label}: select an enabled connector connection."; break; }
                        $driver = $this->connectors->get($connection->driver);
                        $action = trim((string) ($config['action'] ?? ''));
                        if ($action === '' || !collect($driver->actions())->contains(fn ($item) => $item->name === $action)) $errors[] = "{$label}: selected connector action is unavailable.";
                        break;
                    case 'campaign':
                        if (!Campaign::whereKey((int) ($config['campaign_id'] ?? 0))->exists()) $errors[] = "{$label}: select an existing campaign.";
                        break;
                    case 'delay':
                        $seconds = (int) ($config['seconds'] ?? 0);
                        if ($seconds < 1 || $seconds > 604800) $errors[] = "{$label}: delay must be between 1 second and 7 days.";
                        break;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$label}: ".$e->getMessage();
            }
        }
        return array_values(array_unique($errors));
    }

    public function assertExecutable(Workflow $workflow): void
    {
        $errors = $this->validate($workflow);
        if ($errors) throw new \RuntimeException(implode(' ', $errors));
    }
}
