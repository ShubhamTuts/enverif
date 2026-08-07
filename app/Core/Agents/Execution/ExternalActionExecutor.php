<?php

namespace App\Core\Agents\Execution;

use App\Models\ExternalActionExecution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Persists the intent for a real-world mutation before performing it.
 *
 * A completed or provider-rejected action is replayed from the persisted result.
 * An action whose request may have reached the provider but did not produce a
 * trustworthy response is marked unknown_outcome and is never blindly retried.
 */
final class ExternalActionExecutor
{
    /**
     * @param array<string,mixed> $arguments
     * @param callable():array{ok:bool,data:mixed,message:?string} $operation
     * @return array{ok:bool,data:mixed,message:?string}
     */
    public function execute(
        int $workspaceId,
        string $runType,
        string $runId,
        string $stepKey,
        string $action,
        array $arguments,
        callable $operation,
    ): array {
        $argumentsHash = hash('sha256', json_encode($this->normalize($arguments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $identity = [
            'workspace_id' => $workspaceId,
            'run_type' => $runType,
            'run_id' => $runId,
            'step_key' => $stepKey,
            'action' => $action,
        ];

        $claim = DB::transaction(function () use ($identity, $argumentsHash): array {
            $execution = ExternalActionExecution::query()
                ->where($identity)
                ->lockForUpdate()
                ->first();

            if (!$execution) {
                try {
                    $execution = ExternalActionExecution::create($identity + [
                        'arguments_hash' => $argumentsHash,
                        'status' => 'pending',
                    ]);
                } catch (QueryException) {
                    // Another worker may have won the unique insert between our read
                    // and write. Fetch its row under the same transactional lock.
                    $execution = ExternalActionExecution::query()
                        ->where($identity)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            if (!hash_equals((string) $execution->arguments_hash, $argumentsHash)) {
                throw new LogicException('External action identity cannot be reused with different arguments.');
            }

            if (in_array($execution->status, ['completed', 'failed_before_send'], true)) {
                return ['replay' => true, 'result' => (array) ($execution->result ?? [])];
            }

            if (in_array($execution->status, ['running', 'unknown_outcome'], true)) {
                throw new ExternalActionOutcomeUnknown('The external action may already have reached the provider. Reconcile its outcome before retrying.');
            }

            $execution->update([
                'status' => 'running',
                'started_at' => $execution->started_at ?: now(),
                'finished_at' => null,
                'error_class' => null,
            ]);

            return ['replay' => false, 'execution_id' => $execution->id];
        }, 3);

        if ($claim['replay']) {
            return $claim['result'];
        }

        $executionId = (int) $claim['execution_id'];

        try {
            $result = $operation();
            if (!is_array($result) || !array_key_exists('ok', $result)) {
                throw new LogicException('External action operation must return the normalized connector/tool result array.');
            }

            $status = $result['ok'] ? 'completed' : 'failed_before_send';
            ExternalActionExecution::query()->whereKey($executionId)->update([
                'status' => $status,
                'result' => $result,
                'external_id' => $this->externalId($result['data'] ?? null),
                'finished_at' => now(),
            ]);

            return $result;
        } catch (Throwable $e) {
            // At the generic transport boundary we cannot know whether a mutating
            // request failed before or after the remote system accepted it.
            ExternalActionExecution::query()->whereKey($executionId)->update([
                'status' => 'unknown_outcome',
                'error_class' => $e::class,
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function externalId(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        foreach (['external_id', 'message_id', 'id'] as $key) {
            $value = $data[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr((string) $value, 0, 191);
            }
        }

        return null;
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
