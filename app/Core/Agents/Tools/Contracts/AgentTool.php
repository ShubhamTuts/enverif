<?php
namespace App\Core\Agents\Tools\Contracts;use App\Core\Agents\Contracts\RiskLevel;use App\Core\Agents\Tools\DTO\ToolExecutionResult;use App\Models\AgentRun;
interface AgentTool {public function name():string;public function description():string;public function risk():RiskLevel;/** @return array<string,mixed> */public function parameters():array;/** @param array<string,mixed> $arguments */public function execute(AgentRun $run,array $arguments):ToolExecutionResult;}
