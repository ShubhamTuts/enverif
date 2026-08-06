<?php

declare(strict_types=1);

namespace App\Core\Workflows;

final class WorkflowDefinitionValidator
{
    public const TYPES = ['manual','schedule','webhook','agent','connector','skill','condition','delay','lead','campaign','approval','output'];
    public const TRIGGERS = ['manual','schedule','webhook'];

    /** @param array<string,mixed> $definition @return array{nodes:list<array<string,mixed>>,edges:list<array<string,mixed>>} */
    public static function validate(array $definition): array
    {
        $nodes = $definition['nodes'] ?? null;
        $edges = $definition['edges'] ?? [];
        if (!is_array($nodes) || $nodes === [] || count($nodes) > 100) throw new \InvalidArgumentException('Workflow must contain between 1 and 100 nodes.');
        if (!is_array($edges) || count($edges) > 200) throw new \InvalidArgumentException('Workflow contains too many edges.');

        $normalized = [];
        $ids = [];
        $hasTrigger = false;
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) throw new \InvalidArgumentException("Workflow node {$index} must be an object.");
            $id = trim((string) ($node['id'] ?? ''));
            $type = trim((string) ($node['type'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $id)) throw new \InvalidArgumentException("Workflow node {$index} has an invalid id.");
            if (isset($ids[$id])) throw new \InvalidArgumentException("Duplicate workflow node id: {$id}");
            if (!in_array($type, self::TYPES, true)) throw new \InvalidArgumentException("Unsupported workflow node type: {$type}");
            $config = $node['config'] ?? [];
            if (!is_array($config)) throw new \InvalidArgumentException("Workflow node {$id} config must be an object.");
            self::validateNodeConfig($id, $type, $config);
            $position = $node['position'] ?? ['x'=>80 + ($index % 4) * 220, 'y'=>80 + intdiv($index,4) * 130];
            if (!is_array($position)) $position = ['x'=>80,'y'=>80];
            $normalized[] = ['id'=>$id,'type'=>$type,'label'=>trim((string)($node['label']??ucfirst($type))) ?: ucfirst($type),'config'=>$config,'position'=>['x'=>(int)($position['x']??80),'y'=>(int)($position['y']??80)]];
            $ids[$id] = true;
            $hasTrigger = $hasTrigger || in_array($type, self::TRIGGERS, true);
        }
        if (!$hasTrigger) throw new \InvalidArgumentException('Workflow requires at least one manual, schedule, or webhook trigger.');

        $normalizedEdges = [];
        $graph = array_fill_keys(array_keys($ids), []);
        foreach ($edges as $index => $edge) {
            if (!is_array($edge)) throw new \InvalidArgumentException("Workflow edge {$index} must be an object.");
            $from = trim((string) ($edge['from'] ?? ''));
            $to = trim((string) ($edge['to'] ?? ''));
            if (!isset($ids[$from]) || !isset($ids[$to])) throw new \InvalidArgumentException("Workflow edge {$index} references a missing node.");
            if ($from === $to) throw new \InvalidArgumentException('Workflow nodes cannot connect to themselves.');
            $port = trim((string) ($edge['port'] ?? 'default')) ?: 'default';
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $port)) throw new \InvalidArgumentException('Workflow edge port is invalid.');
            $normalizedEdges[] = ['from'=>$from,'to'=>$to,'port'=>$port];
            $graph[$from][] = $to;
        }
        $outgoing = [];
        foreach ($normalizedEdges as $edge) {
            $key = $edge['from'].':'.$edge['port'];
            if (isset($outgoing[$key])) {
                throw new \InvalidArgumentException('Workflow node '.$edge['from'].' has more than one '.$edge['port'].' connection.');
            }
            $outgoing[$key] = $edge['to'];
        }
        foreach ($normalized as $node) {
            if ($node['type'] !== 'condition') continue;
            foreach (['true', 'false'] as $branch) {
                if (!isset($outgoing[$node['id'].':'.$branch])) {
                    throw new \InvalidArgumentException('Condition node '.$node['id'].' requires an explicit '.$branch.' branch.');
                }
            }
        }

        self::assertAcyclic($graph);
        return ['nodes'=>$normalized,'edges'=>$normalizedEdges];
    }

    /** @param array<string,mixed> $config */
    private static function validateNodeConfig(string $id, string $type, array $config): void
    {
        $positiveId = static function (string $key) use ($id, $config): void {
            if ((int) ($config[$key] ?? 0) <= 0) throw new \InvalidArgumentException("Workflow node {$id} requires {$key}.");
        };
        match ($type) {
            'agent' => $positiveId('agent_id'),
            'connector' => (function () use ($positiveId, $id, $config): void { $positiveId('connection_id'); if (trim((string)($config['action']??''))==='') throw new \InvalidArgumentException("Workflow node {$id} requires an action."); })(),
            'skill' => $positiveId('skill_id'),
            'delay' => (function () use ($id,$config): void { $seconds=(int)($config['seconds']??0); if($seconds<1||$seconds>604800)throw new \InvalidArgumentException("Workflow node {$id} delay must be 1-604800 seconds."); })(),
            'condition' => (function () use ($id,$config): void { if(trim((string)($config['path']??''))===''||!in_array((string)($config['operator']??''),['equals','not_equals','contains','gt','gte','lt','lte','exists'],true))throw new \InvalidArgumentException("Workflow node {$id} has an invalid condition."); })(),
            'campaign' => $positiveId('campaign_id'),
            default => null,
        };
    }

    /** @param array<string,list<string>> $graph */
    private static function assertAcyclic(array $graph): void
    {
        $state = [];
        $visit = function (string $node) use (&$visit, &$state, $graph): void {
            if (($state[$node] ?? 0) === 1) throw new \InvalidArgumentException('Workflow contains a cycle.');
            if (($state[$node] ?? 0) === 2) return;
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) $visit($next);
            $state[$node] = 2;
        };
        foreach (array_keys($graph) as $node) $visit($node);
    }
}
