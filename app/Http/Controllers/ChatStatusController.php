<?php

namespace App\Http\Controllers;

use App\Core\Chat\ChatRunMaterializer;
use App\Models\{AgentRun, ChatAttachment, ChatMessage, ChatThread};
use Illuminate\Http\Request;

final class ChatStatusController extends Controller
{
    public function __invoke(
        Request $request,
        ChatThread $thread,
        ChatRunMaterializer $materializer,
    ) {
        abort_unless((int) $thread->user_id === (int) $request->user()->id, 403);

        $latestRunId = (string) $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');
        $run = $latestRunId !== '' ? AgentRun::with('agent')->find($latestRunId) : null;
        $terminal = $run && in_array((string) $run->status, ['completed', 'failed', 'cancelled'], true);

        if ($run && $terminal) {
            $materializer->materialize($run);
        }

        $terminalMessagePresent = (bool) ($run && $terminal && $materializer->isMaterialized($run));
        $busy = $run ? (! $terminal || ! $terminalMessagePresent) : false;
        $agentName = (string) ($run?->agent?->name ?: data_get($run?->context, 'agent_snapshot.name', 'Agent'));

        // Active chat polling is deliberately small. The dedicated /activity endpoint
        // owns run-tree/tool/approval projection, so /status must not repeat that work
        // or reload the entire transcript every ~900ms.
        if ($busy) {
            $latestStep = $run?->steps()->latest('sequence')->first();
            $tool = trim((string) ($latestStep?->tool ?? ''));
            $stepType = trim((string) ($latestStep?->type ?? ''));
            $stage = match (true) {
                $run?->status === 'awaiting_approval' => $agentName.' needs approval',
                $tool !== '' => 'Using '.$tool,
                $stepType === 'model' => $agentName.' is thinking…',
                $run?->status === 'waiting_child' => $agentName.' is waiting on a sub-agent…',
                $run?->status === 'queued' => $agentName.' is queued…',
                default => $agentName.' is working…',
            };

            return response()->json([
                'transcript_html' => null,
                'transcript_version' => null,
                'title' => $thread->title,
                'busy' => true,
                'terminal_message_present' => false,
                'run' => $run ? $this->runPayload($run, $agentName, $stage) : null,
            ]);
        }

        $thread = $thread->fresh()->load(['messages.attachments', 'defaultAgent']);
        $messages = $thread->messages->map(fn (ChatMessage $message) => [
            'id' => $message->id,
            'role' => $message->role,
            'kind' => $message->kind,
            'status' => $message->status,
            'content' => $message->content,
            'run_id' => $message->run_id,
            'meta' => $message->meta,
            'attachments' => $message->attachments->map(fn (ChatAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ]),
            'created_at' => $message->created_at?->toIso8601String(),
        ])->values();

        $lastMessage = $thread->messages->last();
        $transcriptVersion = implode(':', [
            (string) ($lastMessage?->id ?? 0),
            (string) ($lastMessage?->status ?? ''),
            (string) $thread->messages->count(),
            (string) ($lastMessage?->updated_at?->getTimestampMs() ?? 0),
        ]);

        return response()->json([
            'messages' => $messages,
            'transcript_html' => view('chat._transcript', [
                'thread' => $thread,
                'user' => $request->user(),
            ])->render(),
            'transcript_version' => $transcriptVersion,
            'title' => $thread->title,
            'busy' => false,
            'terminal_message_present' => $terminalMessagePresent,
            'run' => $run ? $this->runPayload($run, $agentName, '') : null,
        ]);
    }

    /** @return array<string,mixed> */
    private function runPayload(AgentRun $run, string $agentName, string $stage): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'output' => $run->output,
            'stop_reason' => $run->stop_reason,
            'execution' => data_get($run->context, 'execution'),
            'agent_name' => $agentName,
            'stage' => $stage,
            'url' => route('runs.show', $run),
        ];
    }
}
