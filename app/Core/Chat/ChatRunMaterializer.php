<?php

namespace App\Core\Chat;

use App\Models\{AgentRun, ChatMessage, ChatThread};
use Illuminate\Support\Facades\DB;

final class ChatRunMaterializer
{
    /** @var list<string> */
    private const TERMINAL = ['completed', 'failed', 'cancelled'];

    public function materialize(AgentRun $run): ?ChatMessage
    {
        if (! in_array((string) $run->status, self::TERMINAL, true)) return null;

        $messageId = (int) data_get($run->context, 'chat_message_id', 0);
        $userMessage = $messageId > 0 ? ChatMessage::query()->find($messageId) : null;
        if (! $userMessage) {
            $userMessage = ChatMessage::query()
                ->where('run_id', $run->id)
                ->where('role', 'user')
                ->latest('id')
                ->first();
        }
        if (! $userMessage) return null;

        $threadId = (int) ($userMessage->thread_id ?: data_get($run->context, 'conversation_id', 0));
        if ($threadId <= 0) return null;

        return DB::transaction(function () use ($run, $userMessage, $threadId): ?ChatMessage {
            $thread = ChatThread::withoutGlobalScopes()
                ->whereKey($threadId)
                ->where('workspace_id', $run->workspace_id)
                ->lockForUpdate()
                ->first();
            if (! $thread) return null;

            $content = trim((string) ($run->output ?: $run->stop_reason));
            if ($content === '') {
                $content = $run->status === 'cancelled'
                    ? 'Run cancelled.'
                    : 'The agent finished without a text response.';
            }

            $meta = [
                'status' => (string) $run->status,
                'agent_id' => $run->agent_id,
                'agent_name' => (string) data_get($run->context, 'agent_snapshot.name', 'Enverif'),
                'execution' => data_get($run->context, 'execution'),
                'stop_reason' => $run->stop_reason,
            ];

            $assistant = ChatMessage::query()
                ->where('thread_id', $thread->id)
                ->where('run_id', $run->id)
                ->where('role', 'assistant')
                ->first();

            if (! $assistant) {
                $assistant = new ChatMessage([
                    'thread_id' => $thread->id,
                    'run_id' => $run->id,
                    'role' => 'assistant',
                ]);
            }
            $assistant->fill([
                'kind' => 'final',
                'status' => (string) $run->status,
                'content' => $content,
                'meta' => $meta,
            ]);
            $assistant->save();

            $userMessage->forceFill([
                'run_id' => $run->id,
                'status' => (string) $run->status,
            ])->save();

            $thread->forceFill(['last_message_at' => now()])->save();

            return $assistant->fresh();
        }, 3);
    }

    public function isMaterialized(AgentRun $run): bool
    {
        return ChatMessage::query()
            ->where('run_id', $run->id)
            ->where('role', 'assistant')
            ->where('kind', 'final')
            ->exists();
    }
}
