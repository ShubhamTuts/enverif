<?php
namespace App\Core\Scheduling;
final class ScheduleTarget { public static function type(mixed $agentId,mixed $workflowId):string {$agent=(int)($agentId?:0);$workflow=(int)($workflowId?:0);if(($agent>0)==($workflow>0))throw new \InvalidArgumentException('Schedule must target exactly one agent or workflow.');return $agent>0?'agent':'workflow';}}
