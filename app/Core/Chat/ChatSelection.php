<?php

namespace App\Core\Chat;

final class ChatSelection
{
    /** @return array{connector_ids:list<int>,skill_ids:list<int>,workflow_ids:list<int>,agent_id:int} */
    public static function normalize(array $connectorIds, array $skillIds, array $workflowIds, mixed $agentId): array
    {
        $normalize = static function (array $values): array {
            $ids = [];
            foreach ($values as $value) {
                if (filter_var($value, FILTER_VALIDATE_INT) === false) continue;
                $id = (int) $value;
                if ($id > 0) $ids[$id] = $id;
            }
            return array_values($ids);
        };

        return [
            'connector_ids' => $normalize($connectorIds),
            'skill_ids' => $normalize($skillIds),
            'workflow_ids' => $normalize($workflowIds),
            'agent_id' => max(0, (int) $agentId),
        ];
    }
}
