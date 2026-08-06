<?php

namespace App\Core\Workflows;

use App\Core\Connectors\ConnectorManager;
use App\Models\{Agent,Campaign,ConnectorConnection,Skill,Workflow};

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
                        if (!Agent::whereKey((int) ($config['agent_id'] ?? 0))->where('status','active')->exists()) $errors[] = "{$label}: select an active agent.";
                        break;
                    case 'skill':
                        if (!Skill::whereKey((int) ($config['skill_id'] ?? 0))->where('status','active')->exists()) $errors[] = "{$label}: select an active skill.";
                        if (!empty($config['agent_id']) && !Agent::whereKey((int) $config['agent_id'])->where('status','active')->exists()) $errors[] = "{$label}: selected skill executor agent is unavailable.";
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
