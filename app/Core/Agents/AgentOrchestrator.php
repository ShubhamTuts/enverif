<?php

namespace App\Core\Agents;

use App\Core\Agents\Contracts\CapabilityDecision;
use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\Security\CapabilityPolicy;
use App\Core\Agents\Tools\ToolRegistry;
use App\Core\Audit\AuditLogger;
use App\Core\Models\DTO\ModelRequest;
use App\Core\Models\ProviderManager;
use App\Jobs\ContinueAgentRunJob;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\Approval;
use App\Models\ModelConnection;
use App\Support\WorkspaceContext;

final class AgentOrchestrator
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly ToolRegistry $tools,
        private readonly AuditLogger $audit,
        private readonly SystemPromptBuilder $prompts,
        private readonly WorkspaceContext $workspace,
    ) {}

    /** @param array<string,mixed> $context */
    public function start(Agent $agent, string $input, ?string $parentRunId = null, array $context = []): AgentRun
    {
        if (!isset($context['agent_snapshot']) || !is_array($context['agent_snapshot'])) {
            $connectors = $agent->connectors()
                ->where('connector_connections.enabled', true)
                ->get()
                ->map(function ($connection): array {
                    $allowed = $connection->pivot?->allowed_actions;
                    if (is_string($allowed)) $allowed = json_decode($allowed, true);
                    return [
                        'id' => (int) $connection->id,
                        'allowed_actions' => is_array($allowed) ? array_values($allowed) : null,
                    ];
                })->values()->all();
            $context['agent_snapshot'] = [
                'id' => (int) $agent->id,
                'name' => (string) $agent->name,
                'instructions' => (string) $agent->instructions,
                'model_connection_id' => $agent->model_connection_id ? (int) $agent->model_connection_id : null,
                'model' => $agent->model,
                'default_effort' => $agent->default_effort ?: 'standard',
                'max_steps' => (int) $agent->max_steps,
                'max_runtime_seconds' => (int) $agent->max_runtime_seconds,
                'max_cost_usd' => (float) $agent->max_cost_usd,
                'policy' => (array) ($agent->policy ?? []),
                'skill_ids' => $agent->skills()->where('status', 'active')->pluck('skills.id')->map(fn ($id) => (int) $id)->all(),
                'connectors' => $connectors,
            ];
        }

        $run = AgentRun::create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'parent_run_id' => $parentRunId,
            'status' => 'queued',
            'input' => $input,
            'started_at' => now(),
            'context' => $context,
        ]);
        foreach ((array) ($context['conversation_history'] ?? []) as $message) {
            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') continue;
            AgentMessage::create(['run_id' => $run->id, 'role' => $role, 'content' => $content]);
        }
        AgentMessage::create(['run_id' => $run->id, 'role' => 'user', 'content' => $input]);
        $this->audit->record(
            $agent->workspace_id,
            'agent.run.started',
            'agent',
            $agent->id,
            ['input_length' => mb_strlen($input), 'parent_run_id' => $parentRunId],
            $run->id,
        );
        ContinueAgentRunJob::dispatch($run->id);
        return $run;
    }

    public function advance(string $runId): void
    {
        $run = AgentRun::withoutGlobalScopes()->with('agent')->find($runId);
        if (!$run || $this->isTerminal($run->status)) return;

        $this->workspace->set((int) $run->workspace_id);
        $run->refresh()->load('agent');

        if ($run->status === 'waiting_child') {
            if (!$this->resumeChild($run)) return;
            if (!$this->processPendingSteps($run)) return;
            ContinueAgentRunJob::dispatch($run->id);
            return;
        }

        if ($run->status === 'awaiting_approval') {
            if (!$this->resumeApproval($run)) return;
            if (!$this->processPendingSteps($run)) return;
            ContinueAgentRunJob::dispatch($run->id);
            return;
        }

        if (!$this->processPendingSteps($run)) return;

        $agent = $run->agent;
        $snapshot = (array) data_get($run->context, 'agent_snapshot', []);
        $bounds = new RunBounds(
            (int) ($snapshot['max_steps'] ?? $agent->max_steps),
            (int) ($snapshot['max_runtime_seconds'] ?? $agent->max_runtime_seconds),
            (float) ($snapshot['max_cost_usd'] ?? $agent->max_cost_usd),
        );
        $runtime = $run->started_at ? now()->diffInSeconds($run->started_at, true) : 0;
        $stop = $bounds->shouldStop((int) $run->step_count, (int) $runtime, (float) $run->estimated_cost_usd);
        if ($stop) {
            $run->update(['status' => 'failed', 'stop_reason' => $stop, 'finished_at' => now()]);
            $this->audit->record($run->workspace_id, 'agent.run.stopped', 'agent_run', $run->id, ['reason' => $stop], $run->id);
            $this->wakeParent($run);
            return;
        }

        $connection = $this->connectionFor($run, $agent);
        if (!$connection) {
            $this->fail($run, 'No enabled model connection is configured for this agent.', 'missing_model_connection');
            return;
        }

        $provider = $this->providers->get($connection->provider);
        $requestedModel = trim((string) data_get($run->context, 'model', ''));
        $model = $requestedModel !== '' ? $requestedModel : (($snapshot['model'] ?? null) ?: $connection->default_model ?: $provider->models()[0]);
        $effort = (string) data_get($run->context, 'effort', ($snapshot['default_effort'] ?? null) ?: $agent->default_effort ?: 'standard');
        if (!in_array($effort, ['fast', 'standard', 'deep'], true)) $effort = 'standard';
        $context = (array) $run->context;
        $context['execution'] = [
            'model_connection_id' => $connection->id,
            'provider' => $connection->provider,
            'model' => $model,
            'effort' => $effort,
        ];
        $run->update(['context' => $context]);
        $extraConnectorIds = array_map('intval', (array) data_get($run->context, 'selected_connector_ids', []));
        $definitions = $this->tools->definitions($agent, $extraConnectorIds, (array) ($snapshot['connectors'] ?? []));
        $messages = $run->messages()->orderBy('id')->get()->map(function (AgentMessage $message): array {
            $row = ['role' => $message->role, 'content' => $message->content];
            $meta = $message->meta ?? [];
            foreach (['tool_calls', 'tool_call_id', 'tool_name'] as $key) {
                if (array_key_exists($key, $meta)) $row[$key] = $meta[$key];
            }
            return $row;
        })->all();

        $run->update(['status' => 'running']);
        try {
            $response = $provider->complete(
                $connection,
                new ModelRequest(
                    $model,
                    $this->prompts->build($agent, (array) $run->context),
                    $messages,
                    $definitions,
                    4096,
                    $effort,
                    array_values(array_filter((array) data_get($run->context, 'attachments', []), 'is_array')),
                ),
            );
        } catch (\Throwable $e) {
            report($e);
            $this->fail($run, $this->providerFailureMessage($e, $connection->provider, $model), 'provider_error');
            return;
        }

        $run->refresh();
        if ($run->status === 'cancelled') return;

        $run->increment('step_count');
        $run->increment('input_tokens', $response->inputTokens);
        $run->increment('output_tokens', $response->outputTokens);
        $pricing = $connection->pricing ?? [];
        $turnCost = (($response->inputTokens * (float) ($pricing['input_per_million'] ?? 0))
                + ($response->outputTokens * (float) ($pricing['output_per_million'] ?? 0))) / 1_000_000;
        if ($turnCost > 0) $run->increment('estimated_cost_usd', $turnCost);

        $meta = [];
        if ($response->toolCalls) {
            $meta['tool_calls'] = array_map(
                fn ($call) => ['id' => $call->id, 'name' => $call->name, 'arguments' => $call->arguments],
                $response->toolCalls,
            );
        }
        AgentMessage::create([
            'run_id' => $run->id,
            'role' => 'assistant',
            'content' => $response->content,
            'meta' => $meta ?: null,
        ]);
        $this->audit->record($run->workspace_id, 'agent.model.completed', 'agent_run', $run->id, [
            'provider' => $connection->provider,
            'model' => $model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'estimated_cost_usd' => $turnCost,
            'tool_calls' => count($response->toolCalls),
            'effort' => $effort,
        ], $run->id);

        if (!$response->toolCalls) {
            $run->update([
                'status' => 'completed',
                'output' => $response->content,
                'stop_reason' => $response->finishReason ?: 'final',
                'finished_at' => now(),
            ]);
            $audit = (new RunAuditor)->audit($run->fresh());
            $this->audit->record($run->workspace_id, 'agent.run.completed', 'agent_run', $run->id, $audit, $run->id);
            $this->wakeParent($run);
            return;
        }

        $sequence = (int) $run->steps()->max('sequence');
        foreach ($response->toolCalls as $call) {
            AgentRunStep::create([
                'run_id' => $run->id,
                'sequence' => ++$sequence,
                'type' => 'tool',
                'status' => 'pending',
                'tool' => $call->name,
                'risk_level' => $this->tools->risk($agent, $call->name, $extraConnectorIds, (array) ($snapshot['connectors'] ?? []))->value,
                'input' => ['tool_call_id' => $call->id, 'arguments' => $call->arguments],
            ]);
        }
        if ($this->processPendingSteps($run)) ContinueAgentRunJob::dispatch($run->id);
    }

    private function processPendingSteps(AgentRun $run): bool
    {
        $run->load('agent');
        foreach ($run->steps()->where('status', 'pending')->orderBy('sequence')->get() as $step) {
            $risk = RiskLevel::from((string) $step->risk_level);
            $decision = $this->decisionFor($run, $risk, (string) $step->tool);
            $this->audit->record($run->workspace_id, 'agent.permission.decision', 'agent_run_step', $step->id, [
                'tool' => $step->tool,
                'risk' => $risk->value,
                'decision' => $decision->value,
            ], $run->id);

            if ($decision === CapabilityDecision::Deny) {
                $step->update(['status' => 'failed', 'output' => 'Capability policy denied this tool call.', 'finished_at' => now()]);
                $this->appendToolResult($run, $step, false, 'Capability policy denied this tool call.');
                continue;
            }

            if ($decision === CapabilityDecision::Ask) {
                $step->update(['status' => 'awaiting_approval', 'started_at' => now()]);
                Approval::firstOrCreate(
                    ['workspace_id' => $run->workspace_id, 'run_step_id' => $step->id, 'status' => 'pending'],
                    [
                        'run_id' => $run->id,
                        'action' => (string) $step->tool,
                        'risk_level' => $risk->value,
                        'summary' => 'Agent requests permission to run ' . $step->tool . '.',
                        'payload' => $step->input,
                    ],
                );
                $run->update(['status' => 'awaiting_approval']);
                $this->audit->record($run->workspace_id, 'agent.approval.requested', 'agent_run_step', $step->id, [
                    'tool' => $step->tool,
                    'risk' => $risk->value,
                ], $run->id);
                return false;
            }

            if (!$this->executeStep($run, $step)) return false;
        }
        return true;
    }

    private function decisionFor(AgentRun $run, RiskLevel $risk, string $tool): CapabilityDecision
    {
        $decisions = [$this->policy((array) data_get($run->context, 'agent_snapshot.policy', (array) ($run->agent->policy ?? [])))->decide($risk, ['tool' => $tool])];
        foreach ((array) data_get($run->context, 'policy_ceiling_chain', []) as $ceiling) {
            if (is_array($ceiling)) {
                $decisions[] = $this->policy($ceiling)->decide($risk, ['tool' => $tool]);
            }
        }
        return CapabilityDecision::strictest(...$decisions);
    }

    /** @param array<string,mixed> $data */
    private function policy(array $data): CapabilityPolicy
    {
        return new CapabilityPolicy(
            allow: (array) ($data['allow'] ?? []),
            deny: (array) ($data['deny'] ?? []),
            allowExternalWrites: (bool) ($data['allow_external_writes'] ?? false),
            allowDestructive: (bool) ($data['allow_destructive'] ?? false),
        );
    }

    private function resumeApproval(AgentRun $run): bool
    {
        $step = $run->steps()->where('status', 'awaiting_approval')->orderBy('sequence')->first();
        if (!$step) {
            $run->update(['status' => 'running']);
            return true;
        }
        $approval = Approval::where('run_step_id', $step->id)->latest('id')->first();
        if (!$approval || $approval->status === 'pending') return false;
        if ($approval->status !== 'approved') {
            $step->update(['status' => 'failed', 'output' => 'The requested action was denied.', 'finished_at' => now()]);
            $this->appendToolResult($run, $step, false, 'The requested action was denied.');
            $run->update(['status' => 'running']);
            return true;
        }
        if (!$this->executeStep($run, $step)) return false;
        $run->update(['status' => 'running']);
        return true;
    }

    private function executeStep(AgentRun $run, AgentRunStep $step): bool
    {
        if ($step->tool === 'agents.delegate') {
            return $this->delegateStep($run, $step);
        }

        $input = $step->input ?? [];
        $step->update(['status' => 'running', 'started_at' => $step->started_at ?: now()]);
        try {
            $result = $this->tools->execute($run, (string) $step->tool, (array) ($input['arguments'] ?? []));
            $step->update(['status' => $result->ok ? 'completed' : 'failed', 'output' => $result->text(), 'finished_at' => now()]);
            $this->appendToolResult($run, $step, $result->ok, $result->text());
            $this->audit->record($run->workspace_id, 'agent.tool.completed', 'agent_run_step', $step->id, [
                'tool' => $step->tool,
                'ok' => $result->ok,
            ], $run->id);
        } catch (\Throwable $e) {
            report($e);
            $text = 'Tool execution failed. See server logs for details.';
            $step->update(['status' => 'failed', 'output' => $text, 'finished_at' => now()]);
            $this->appendToolResult($run, $step, false, $text);
            $this->audit->record($run->workspace_id, 'agent.tool.failed', 'agent_run_step', $step->id, [
                'tool' => $step->tool,
                'error' => get_class($e),
            ], $run->id);
        }
        return true;
    }

    private function delegateStep(AgentRun $run, AgentRunStep $step): bool
    {
        $input = $step->input ?? [];
        $arguments = (array) ($input['arguments'] ?? []);
        $targetId = (int) ($arguments['agent_id'] ?? 0);
        $prompt = trim((string) ($arguments['prompt'] ?? ''));
        $depth = (int) data_get($run->context, 'delegation_depth', 0);
        $maxDepth = max(0, (int) config('enverif.run.max_delegation_depth', 3));
        $chain = array_values(array_map('intval', (array) data_get($run->context, 'delegation_agent_chain', [])));
        $chain[] = (int) $run->agent_id;

        $target = Agent::withoutGlobalScopes()
            ->where('workspace_id', $run->workspace_id)
            ->where('status', 'active')
            ->find($targetId);
        if (!$target || $prompt === '' || mb_strlen($prompt) > 20000) {
            return $this->failDelegationStep($run, $step, 'Delegated agent or prompt is invalid.');
        }
        if ($depth >= $maxDepth) {
            return $this->failDelegationStep($run, $step, 'Maximum delegation depth reached.');
        }
        if (in_array($targetId, $chain, true)) {
            return $this->failDelegationStep($run, $step, 'Delegation cycle detected.');
        }

        $ceiling = array_values(array_filter((array) data_get($run->context, 'policy_ceiling_chain', []), 'is_array'));
        $ceiling[] = (array) ($run->agent->policy ?? []);
        $child = $this->start($target, $prompt, $run->id, [
            'delegation_depth' => $depth + 1,
            'delegation_agent_chain' => $chain,
            'policy_ceiling_chain' => $ceiling,
            'delegated_by_step_id' => $step->id,
        ]);

        $input['child_run_id'] = $child->id;
        $step->update(['status' => 'waiting_child', 'input' => $input, 'started_at' => now()]);
        $run->update(['status' => 'waiting_child']);
        $this->audit->record($run->workspace_id, 'agent.delegation.started', 'agent_run_step', $step->id, [
            'child_run_id' => $child->id,
            'target_agent_id' => $target->id,
            'depth' => $depth + 1,
        ], $run->id);
        return false;
    }

    private function failDelegationStep(AgentRun $run, AgentRunStep $step, string $message): bool
    {
        $step->update(['status' => 'failed', 'output' => $message, 'finished_at' => now()]);
        $this->appendToolResult($run, $step, false, $message);
        return true;
    }

    private function resumeChild(AgentRun $run): bool
    {
        $step = $run->steps()->where('status', 'waiting_child')->orderBy('sequence')->first();
        if (!$step) {
            $run->update(['status' => 'running']);
            return true;
        }
        $childId = (string) data_get($step->input, 'child_run_id', '');
        $child = $childId !== ''
            ? AgentRun::withoutGlobalScopes()->where('workspace_id', $run->workspace_id)->where('parent_run_id', $run->id)->find($childId)
            : null;
        if (!$child) {
            $message = 'Delegated run could not be found.';
            $step->update(['status' => 'failed', 'output' => $message, 'finished_at' => now()]);
            $this->appendToolResult($run, $step, false, $message);
            $run->update(['status' => 'running']);
            return true;
        }
        if (!$this->isTerminal($child->status)) return false;

        $ok = $child->status === 'completed';
        $message = $child->output ?: 'Delegated agent ended with status ' . $child->status . ($child->stop_reason ? ' (' . $child->stop_reason . ')' : '') . '.';
        $step->update(['status' => $ok ? 'completed' : 'failed', 'output' => $message, 'finished_at' => now()]);
        $this->appendToolResult($run, $step, $ok, $message);
        $run->update(['status' => 'running']);
        $this->audit->record($run->workspace_id, 'agent.delegation.finished', 'agent_run_step', $step->id, [
            'child_run_id' => $child->id,
            'child_status' => $child->status,
        ], $run->id);
        return true;
    }

    private function appendToolResult(AgentRun $run, AgentRunStep $step, bool $ok, string $content): void
    {
        $input = $step->input ?? [];
        AgentMessage::create([
            'run_id' => $run->id,
            'role' => 'tool',
            'content' => $content,
            'meta' => [
                'tool_call_id' => $input['tool_call_id'] ?? null,
                'tool_name' => $step->tool,
                'ok' => $ok,
            ],
        ]);
    }

    private function connectionFor(AgentRun $run, Agent $agent): ?ModelConnection
    {
        $overrideId = (int) data_get($run->context, 'model_connection_id', 0);
        if ($overrideId > 0) {
            $connection = ModelConnection::where('workspace_id', $agent->workspace_id)
                ->where('enabled', true)
                ->find($overrideId);
            if ($connection) return $connection;
        }
        $snapshotConnectionId = (int) data_get($run->context, 'agent_snapshot.model_connection_id', 0);
        $defaultConnectionId = $snapshotConnectionId > 0 ? $snapshotConnectionId : (int) $agent->model_connection_id;
        if ($defaultConnectionId > 0) {
            $connection = ModelConnection::where('workspace_id', $agent->workspace_id)
                ->where('enabled', true)
                ->find($defaultConnectionId);
            if ($connection) return $connection;
        }
        return ModelConnection::where('workspace_id', $agent->workspace_id)->where('enabled', true)->first();
    }

    private function providerFailureMessage(\Throwable $e, string $provider, string $model): string
    {
        $detail = trim($e->getMessage());
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            $response = $e->response;
            $status = $response?->status();
            $body = $response?->json();
            $apiMessage = data_get($body, 'error.message')
                ?? data_get($body, 'error.msg')
                ?? data_get($body, 'message')
                ?? data_get($body, 'error');
            if (is_array($apiMessage)) {
                $apiMessage = json_encode($apiMessage, JSON_UNESCAPED_SLASHES);
            }
            if (is_string($apiMessage) && trim($apiMessage) !== '') {
                $detail = trim($apiMessage);
            }
            if ($status) {
                $detail = "HTTP {$status}: {$detail}";
            }
        }

        $detail = preg_replace('/\s+/', ' ', $detail) ?: 'Check the provider connection, model ID, and API key.';
        $detail = mb_substr($detail, 0, 420);
        $hint = match ($provider) {
            'deepseek' => ' Use a current DeepSeek model such as deepseek-v4-flash or deepseek-v4-pro (legacy IDs are remapped automatically).',
            'openai' => ' Confirm the OpenAI model ID and that the key can access Chat Completions.',
            'anthropic' => ' Confirm the Claude model ID (for example claude-sonnet-5 or claude-opus-5).',
            'gemini' => ' Confirm the Gemini model ID (for example gemini-3.6-flash or gemini-2.5-pro).',
            default => '',
        };

        return "Model request failed for {$provider}/{$model}. {$detail}{$hint}";
    }

    private function fail(AgentRun $run, string $message, string $reason): void
    {
        $run->update(['status' => 'failed', 'output' => $message, 'stop_reason' => $reason, 'finished_at' => now()]);
        $this->audit->record($run->workspace_id, 'agent.run.failed', 'agent_run', $run->id, ['reason' => $reason], $run->id);
        $this->wakeParent($run);
    }

    public function wakeParent(AgentRun $run): void
    {
        if ($run->parent_run_id) ContinueAgentRunJob::dispatch((string) $run->parent_run_id);
    }

    private function isTerminal(string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'cancelled'], true);
    }
}
