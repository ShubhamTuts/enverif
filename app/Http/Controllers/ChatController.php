<?php

namespace App\Http\Controllers;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Chat\ChatHistoryBuilder;
use App\Core\Chat\ChatSelection;
use App\Core\Models\ProviderManager;
use App\Core\Runtime\WebQueueKick;
use App\Models\{Agent, AgentRun, Campaign, ChatAttachment, ChatMessage, ChatThread, ConnectorConnection, Lead, ModelConnection, Skill, Workflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChatController extends Controller
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function index(Request $request)
    {
        return view('chat.index', $this->viewData($request, null));
    }

    public function show(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);
        $this->syncTerminalMessages($thread);
        return view('chat.index', $this->viewData($request, $thread->fresh()));
    }

    public function send(Request $request, AgentOrchestrator $orchestrator, WebQueueKick $queueKick, ?ChatThread $thread = null)
    {
        if ($thread) {
            $this->authorizeThread($request, $thread);
            $activeRunId = (string) $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');
            if ($activeRunId !== '') {
                $activeRun = AgentRun::find($activeRunId);
                if ($activeRun && ! in_array($activeRun->status, ['completed', 'failed', 'cancelled'], true)) {
                    throw ValidationException::withMessages([
                        'prompt' => 'Wait for the current agent run to finish or stop it before sending another message.',
                    ]);
                }
            }
        }

        $data = $request->validate([
            'prompt' => 'nullable|string|max:20000',
            'agent_id' => 'nullable|integer',
            'model_connection_id' => 'nullable|integer',
            'model' => 'nullable|string|max:160',
            'custom_model' => 'nullable|string|max:160',
            'effort' => 'nullable|in:fast,standard,deep',
            'persist_defaults' => 'nullable|boolean',
            'connector_ids' => 'array|max:20', 'connector_ids.*' => 'integer',
            'skill_ids' => 'array|max:20', 'skill_ids.*' => 'integer',
            'workflow_ids' => 'array|max:20', 'workflow_ids.*' => 'integer',
            'lead_ids' => 'array|max:20', 'lead_ids.*' => 'integer',
            'campaign_ids' => 'array|max:20', 'campaign_ids.*' => 'integer',
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|max:5120|mimes:jpg,jpeg,png,webp,gif,pdf,txt,md,csv,json,xml,html,doc,docx,xls,xlsx',
        ]);

        $prompt = trim((string) ($data['prompt'] ?? ''));
        $uploads = $request->file('attachments', []);
        if ($prompt === '' && count($uploads) === 0) {
            throw ValidationException::withMessages(['prompt' => 'Enter a message or attach at least one file.']);
        }

        // --- Auto-resolve or auto-bootstrap the agent ---
        $agentId = (int) ($data['agent_id'] ?? ($thread?->default_agent_id ?: $thread?->agent_id ?: 0));
        if ($agentId <= 0) $agentId = (int) Agent::where('status', 'active')->value('id');

        // Zero-friction first-use: if no agent exists yet, auto-create a default one.
        if ($agentId <= 0) {
            $agent = $this->bootstrapDefaultAgent($request);
            if (!$agent) {
                throw ValidationException::withMessages(['agent_id' => 'Connect an AI model first, then Enverif will start automatically.']);
            }
            $agentId = $agent->id;
        }

        $agent = Agent::whereKey($agentId)->where('status', 'active')->first();
        if (!$agent) {
            // Try fallback to any active agent
            $agent = Agent::where('status', 'active')->first();
            if (!$agent) {
                throw ValidationException::withMessages(['agent_id' => 'No active agent found. Connect a model and Enverif will configure itself.']);
            }
        }

        $selection = ChatSelection::normalize(
            (array) ($data['connector_ids'] ?? []),
            (array) ($data['skill_ids'] ?? []),
            (array) ($data['workflow_ids'] ?? []),
            $agent->id,
        );

        $connectorIds = ConnectorConnection::whereIn('id', $selection['connector_ids'])->where('enabled', true)->pluck('id')->map(fn ($v) => (int) $v)->all();
        $skillIds = Skill::whereIn('id', $selection['skill_ids'])->where('status', 'active')->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', session('workspace_id')))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $workflowIds = Workflow::whereIn('id', $selection['workflow_ids'])->pluck('id')->map(fn ($v) => (int) $v)->all();
        $leadIds = Lead::whereIn('id', array_map('intval', (array) ($data['lead_ids'] ?? [])))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $campaignIds = Campaign::whereIn('id', array_map('intval', (array) ($data['campaign_ids'] ?? [])))->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (count($connectorIds) !== count($selection['connector_ids']) || count($skillIds) !== count($selection['skill_ids']) || count($workflowIds) !== count($selection['workflow_ids']) || count($leadIds) !== count((array) ($data['lead_ids'] ?? [])) || count($campaignIds) !== count((array) ($data['campaign_ids'] ?? []))) {
            throw ValidationException::withMessages(['prompt' => 'One or more tagged capabilities are unavailable in this workspace.']);
        }

        [$connection, $model] = $this->resolveModelSelection($data, $thread, $agent);
        if (!$connection) {
            throw ValidationException::withMessages(['model_connection_id' => 'Connect an AI model (OpenAI, Claude, Gemini or DeepSeek) under AI Models before sending.']);
        }
        $effort = (string) ($data['effort'] ?? $thread?->default_effort ?: $agent->default_effort ?: 'standard');
        if (!in_array($effort, ['fast', 'standard', 'deep'], true)) $effort = 'standard';
        $persistDefaults = !$thread || $request->boolean('persist_defaults', true);

        if (!$thread) {
            $firstUpload = count($uploads) ? reset($uploads) : null;
            $titleSource = $prompt !== '' ? $prompt : (string) ($firstUpload?->getClientOriginalName() ?? 'New chat');
            $thread = ChatThread::create([
                'user_id' => $request->user()->id,
                'agent_id' => $agent->id,
                'default_agent_id' => $agent->id,
                'default_model_connection_id' => $connection?->id,
                'default_model' => $model,
                'default_effort' => $effort,
                'title' => Str::limit($titleSource, 70, '…'),
                'last_message_at' => now(),
            ]);
        } elseif ($persistDefaults) {
            $thread->update([
                'agent_id' => $agent->id,
                'default_agent_id' => $agent->id,
                'default_model_connection_id' => $connection?->id,
                'default_model' => $model,
                'default_effort' => $effort,
                'archived_at' => null,
            ]);
        }

        $mentions = $this->mentionSnapshots($connectorIds, $skillIds, $workflowIds, $leadIds, $campaignIds, $agent);
        $userMessage = ChatMessage::create([
            'thread_id' => $thread->id,
            'role' => 'user',
            'kind' => 'message',
            'status' => 'submitted',
            'content' => $prompt,
            'meta' => [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'model_connection_id' => $connection?->id,
                'model' => $model,
                'effort' => $effort,
                'persist_defaults' => $persistDefaults,
                'connector_ids' => $connectorIds,
                'skill_ids' => $skillIds,
                'workflow_ids' => $workflowIds,
                'lead_ids' => $leadIds,
                'campaign_ids' => $campaignIds,
                'mentions' => $mentions,
            ],
        ]);

        $attachmentContext = $this->storeAttachments($request, $thread, $userMessage, $uploads);
        $history = ChatHistoryBuilder::fromTranscript(
            $thread->messages()->where('id', '<', $userMessage->id)->get(['role', 'content'])->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])->all(),
            24,
        );
        $context = [
            'conversation_id' => $thread->id,
            'chat_message_id' => $userMessage->id,
            'conversation_history' => $history,
            'model_connection_id' => $connection?->id,
            'model' => $model,
            'effort' => $effort,
            'selected_connector_ids' => $connectorIds,
            'selected_skill_ids' => $skillIds,
            'selected_workflow_ids' => $workflowIds,
            'selected_lead_ids' => $leadIds,
            'selected_campaign_ids' => $campaignIds,
            'mentions' => $mentions,
            'attachments' => $attachmentContext,
        ];

        $runInput = $prompt !== '' ? $prompt : 'Review the attached files and respond to the operator.';
        $run = $orchestrator->start($agent, $runInput, null, $context);
        $queueKick->afterResponse();
        $meta = (array) $userMessage->meta;
        $meta['run_id'] = $run->id;
        $meta['attachment_ids'] = array_column($attachmentContext, 'id');
        $userMessage->update(['run_id' => $run->id, 'meta' => $meta, 'status' => 'running']);
        $thread->update(['last_message_at' => now(), 'archived_at' => null]);
        $this->syncTerminalMessages($thread);

        $threadUrl = route('chat.show', $thread);
        if ($request->expectsJson()) {
            $thread = $thread->fresh()->load(['messages.attachments', 'defaultAgent']);
            return response()->json([
                'ok' => true,
                'thread_id' => $thread->id,
                'run_id' => $run->id,
                'thread_url' => $threadUrl,
                'send_url' => route('chat.send', $thread),
                'status_url' => route('chat.status', $thread),
                'title' => $thread->title,
                'transcript_html' => $this->renderTranscript($request, $thread),
            ], 202);
        }

        return redirect()->to($threadUrl);
    }

    public function status(Request $request, ChatThread $thread, WebQueueKick $queueKick)
    {
        $this->authorizeThread($request, $thread);
        $this->syncTerminalMessages($thread);
        $messages = $thread->messages()->with('attachments')->latest('id')->limit(100)->get()->reverse()->values()->map(fn (ChatMessage $message) => [
            'id' => $message->id,
            'role' => $message->role,
            'kind' => $message->kind,
            'status' => $message->status,
            'content' => $message->content,
            'run_id' => $message->run_id,
            'meta' => $message->meta,
            'attachments' => $message->attachments->map(fn (ChatAttachment $attachment) => ['id'=>$attachment->id,'name'=>$attachment->original_name,'mime_type'=>$attachment->mime_type,'size_bytes'=>$attachment->size_bytes]),
            'created_at' => $message->created_at?->toIso8601String(),
        ]);
        $latestRunId = $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');
        $run = $latestRunId ? AgentRun::with('agent')->find($latestRunId) : null;
        $latestStep = $run?->steps()->latest('sequence')->first();
        // Shared hosting: keep draining the agent queue while the browser polls, so live
        // progress does not stall until the next cron tick or a full page refresh.
        if ($run && ! in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            $queueKick->afterResponse(12);
        }
        $agentName = (string) ($run?->agent?->name ?: data_get($run?->context, 'agent_snapshot.name', 'Agent'));
        $stage = '';
        if ($run && ! in_array($run->status, ['completed', 'failed', 'cancelled'], true)) {
            $tool = trim((string) ($latestStep?->tool ?? ''));
            $stepType = trim((string) ($latestStep?->type ?? ''));
            $stage = match (true) {
                $run->status === 'awaiting_approval' => $agentName.' needs approval',
                $tool !== '' => 'Using '.$tool,
                $stepType === 'model' => $agentName.' is thinking…',
                $run->status === 'waiting_child' => $agentName.' is waiting on a sub-agent…',
                $run->status === 'queued' => $agentName.' is queued…',
                default => $agentName.' is working…',
            };
        }
        $thread = $thread->fresh()->load(['messages.attachments', 'defaultAgent']);
        return response()->json([
            'messages' => $messages,
            'transcript_html' => $this->renderTranscript($request, $thread),
            'title' => $thread->title,
            'busy' => $run ? ! in_array($run->status, ['completed', 'failed', 'cancelled'], true) : false,
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'output' => $run->output,
                'stop_reason' => $run->stop_reason,
                'execution' => data_get($run->context, 'execution'),
                'agent_name' => $agentName,
                'stage' => $stage,
            ] : null,
        ]);
    }

    public function stop(Request $request, ChatThread $thread, AgentOrchestrator $orchestrator)
    {
        $this->authorizeThread($request, $thread);
        $runId = (string) $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');
        $run = $runId !== '' ? AgentRun::find($runId) : null;
        if ($run && !in_array($run->status, ['completed','failed','cancelled'], true)) {
            $now = now(); $frontier = [$run->id];
            while ($frontier !== []) {
                AgentRun::withoutGlobalScopes()->whereIn('id',$frontier)->where('workspace_id',$run->workspace_id)->whereNotIn('status',['completed','failed','cancelled'])->update(['status'=>'cancelled','cancelled_at'=>$now,'finished_at'=>$now,'stop_reason'=>'cancelled']);
                $frontier = AgentRun::withoutGlobalScopes()->whereIn('parent_run_id',$frontier)->where('workspace_id',$run->workspace_id)->pluck('id')->all();
            }
            $orchestrator->wakeParent($run->fresh());
        }
        $this->syncTerminalMessages($thread);
        return back()->with('status','Agent run stopped.');
    }

    public function rename(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);
        $data = $request->validate(['title' => 'required|string|max:160']);
        $thread->update(['title' => trim($data['title'])]);
        return back()->with('status', 'Chat renamed.');
    }

    public function archive(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);
        $thread->update(['archived_at' => $thread->archived_at ? null : now()]);
        return redirect()->route('chat.index')->with('status', $thread->fresh()->archived_at ? 'Chat archived.' : 'Chat restored.');
    }

    public function destroy(Request $request, ChatThread $thread)
    {
        $this->authorizeThread($request, $thread);
        foreach ($thread->attachments()->get() as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $thread->delete();
        return redirect()->route('chat.index')->with('status', 'Chat deleted.');
    }

    private function renderTranscript(Request $request, ChatThread $thread): string
    {
        return view('chat._transcript', [
            'thread' => $thread,
            'user' => $request->user(),
        ])->render();
    }

    private function syncTerminalMessages(ChatThread $thread): void
    {
        $pending = $thread->messages()->where('role', 'user')->whereNotNull('run_id')->get();
        foreach ($pending as $message) {
            if (ChatMessage::where('thread_id', $thread->id)->where('run_id', $message->run_id)->where('role', 'assistant')->exists()) continue;
            $run = AgentRun::find($message->run_id);
            if (!$run || !in_array($run->status, ['completed', 'failed', 'cancelled'], true)) continue;
            $content = trim((string) ($run->output ?: $run->stop_reason));
            if ($content === '') $content = $run->status === 'cancelled' ? 'Run cancelled.' : 'The agent finished without a text response.';
            ChatMessage::create([
                'thread_id' => $thread->id,
                'run_id' => $run->id,
                'role' => 'assistant',
                'kind' => 'final',
                'status' => $run->status,
                'content' => $content,
                'meta' => [
                    'status' => $run->status,
                    'agent_id' => $run->agent_id,
                    'agent_name' => (string) data_get($run->context, 'agent_snapshot.name', $run->agent?->name ?: 'Enverif'),
                    'execution' => data_get($run->context, 'execution'),
                    'stop_reason' => $run->stop_reason,
                ],
            ]);
            $message->update(['status' => $run->status]);
            $thread->update(['last_message_at' => now()]);
        }
    }

    private function viewData(Request $request, ?ChatThread $thread): array
    {
        $q = trim((string) $request->query('q', ''));
        $showArchived = $request->boolean('archived');
        $query = ChatThread::where('user_id', $request->user()->id)->with('defaultAgent')->orderByDesc('last_message_at');
        $showArchived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('title', 'like', '%'.$q.'%')->orWhereHas('messages', fn ($messages) => $messages->where('content', 'like', '%'.$q.'%'));
            });
        }
        $threads = $query->paginate(30)->withQueryString();
        $agents = Agent::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug', 'description', 'avatar_path', 'model_connection_id', 'model', 'default_effort']);
        $modelConnections = ModelConnection::where('enabled', true)->orderBy('name')->get(['id','name','provider','default_model']);
        $selectedAgentId = (int) old('agent_id', $thread?->default_agent_id ?: $thread?->agent_id ?: $agents->first()?->id);
        $selectedAgent = $agents->firstWhere('id', $selectedAgentId);
        $selectedConnectionId = (int) old('model_connection_id', $thread?->default_model_connection_id ?: $selectedAgent?->model_connection_id ?: 0);
        $selectedConnection = $modelConnections->firstWhere('id', $selectedConnectionId);
        $selectedModel = (string) old('model', $thread?->default_model ?: $selectedAgent?->model ?: $selectedConnection?->default_model ?: '');

        return [
            'thread' => $thread?->load(['messages.attachments', 'defaultAgent', 'modelConnection']),
            'threads' => $threads,
            'agents' => $agents,
            'modelConnections' => $modelConnections,
            'modelCatalog' => $this->providers->catalog(),
            'connectors' => ConnectorConnection::where('enabled', true)->orderBy('name')->get(['id', 'name', 'driver']),
            'skills' => Skill::where('status', 'active')->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', session('workspace_id')))->orderBy('name')->get(['id', 'name', 'slug']),
            'workflows' => Workflow::whereIn('status', ['active', 'draft'])->orderBy('name')->get(['id', 'name', 'description']),
            'leads' => Lead::latest()->limit(50)->get(['id','first_name','last_name','company','email']),
            'campaigns' => Campaign::orderBy('name')->limit(50)->get(['id','name']),
            'selectedAgentId' => $selectedAgentId,
            'selectedConnectionId' => $selectedConnectionId,
            'selectedModel' => $selectedModel,
            'selectedEffort' => old('effort', $thread?->default_effort ?: $selectedAgent?->default_effort ?: 'standard'),
            'chatQuery' => $q,
            'showArchived' => $showArchived,
        ];
    }

    /**
     * Auto-bootstrap a default "Enverif Assistant" agent the first time a user sends
     * a chat message without any agent configured. This gives zero-friction first-use:
     * connect a model, type a message, get a response — no manual agent creation needed.
     */
    private function bootstrapDefaultAgent(Request $request): ?Agent
    {
        $workspaceId = (int) session('workspace_id');
        if ($workspaceId <= 0) return null;

        $connection = ModelConnection::where('workspace_id', $workspaceId)->where('enabled', true)->first();
        if (!$connection) return null;

        return Agent::create([
            'workspace_id' => $workspaceId,
            'name' => 'Enverif Assistant',
            'slug' => 'enverif-assistant-' . Str::lower(Str::random(5)),
            'description' => 'Default assistant. Research, answer questions, manage leads, run workflows and coordinate specialist agents.',
            'instructions' => "You are Enverif, an intelligent revenue and research assistant.\n\n"
                . "You can:\n"
                . "- Research companies, prospects and markets\n"
                . "- Answer questions using available tools and skills\n"
                . "- Search and update leads and campaigns\n"
                . "- Draft content, emails and outreach\n"
                . "- Run and coordinate workflows\n"
                . "- Delegate to specialist agents when appropriate\n\n"
                . "Always be concise, evidence-based and action-oriented. "
                . "Distinguish verified facts from assumptions. "
                . "Never claim an action succeeded unless confirmed by a tool result.",
            'status' => 'active',
            'model_connection_id' => $connection->id,
            'model' => $connection->default_model,
            'default_effort' => 'standard',
            'max_steps' => 40,
            'max_runtime_seconds' => 900,
            'max_cost_usd' => 10,
            'policy' => ['allow' => [], 'deny' => [], 'allow_external_writes' => false, 'allow_destructive' => false],
        ]);
    }

    /** @param array<string,mixed> $data @return array{0:?ModelConnection,1:?string} */
    private function resolveModelSelection(array $data, ?ChatThread $thread, Agent $agent): array
    {
        $connectionId = (int) ($data['model_connection_id'] ?? $thread?->default_model_connection_id ?: $agent->model_connection_id ?: 0);
        $connection = $connectionId > 0 ? ModelConnection::whereKey($connectionId)->where('enabled', true)->first() : null;
        if ($connectionId > 0 && !$connection) throw ValidationException::withMessages(['model_connection_id' => 'The selected model connection is unavailable in this workspace.']);

        // Fall back to any enabled connection in the workspace
        if (!$connection) {
            $connection = ModelConnection::where('workspace_id', $agent->workspace_id)->where('enabled', true)->first();
        }

        $submitted = array_key_exists('model', $data);
        $model = $submitted ? trim((string) ($data['model'] ?? '')) : trim((string) ($thread?->default_model ?: ''));
        if ($model === '__custom__') {
            $model = trim((string) ($data['custom_model'] ?? ''));
            if ($model === '') throw ValidationException::withMessages(['custom_model' => 'Enter the custom model ID.']);
        }
        if ($model === '' && $connection) $model = $connection->default_model ?: ($this->providers->get($connection->provider)->models()[0] ?? null);
        if ($model !== '' && $connection && $submitted && !in_array($model, $this->providers->get($connection->provider)->models(), true) && trim((string) ($data['custom_model'] ?? '')) === '') {
            // Custom model ID entered inline — allow it through rather than blocking
            $model = $model;
        }
        return [$connection, $model !== '' ? $model : null];
    }

    /** @param list<int> $connectorIds @param list<int> $skillIds @param list<int> $workflowIds @param list<int> $leadIds @param list<int> $campaignIds @return list<array<string,mixed>> */
    private function mentionSnapshots(array $connectorIds, array $skillIds, array $workflowIds, array $leadIds, array $campaignIds, Agent $agent): array
    {
        $mentions = [['type'=>'agent','id'=>$agent->id,'label'=>$agent->name]];
        foreach (ConnectorConnection::whereIn('id',$connectorIds)->get(['id','name']) as $row) $mentions[]=['type'=>'plugin','id'=>$row->id,'label'=>$row->name];
        foreach (Skill::whereIn('id',$skillIds)->get(['id','name']) as $row) $mentions[]=['type'=>'skill','id'=>$row->id,'label'=>$row->name];
        foreach (Workflow::whereIn('id',$workflowIds)->get(['id','name']) as $row) $mentions[]=['type'=>'workflow','id'=>$row->id,'label'=>$row->name];
        foreach (Lead::whereIn('id',$leadIds)->get(['id','first_name','last_name','company']) as $row) $mentions[]=['type'=>'lead','id'=>$row->id,'label'=>trim($row->first_name.' '.$row->last_name) ?: ($row->company ?: 'Lead #'.$row->id)];
        foreach (Campaign::whereIn('id',$campaignIds)->get(['id','name']) as $row) $mentions[]=['type'=>'campaign','id'=>$row->id,'label'=>$row->name];
        return $mentions;
    }

    /** @param array<int,\Illuminate\Http\UploadedFile> $uploads @return list<array<string,mixed>> */
    private function storeAttachments(Request $request, ChatThread $thread, ChatMessage $message, array $uploads): array
    {
        $result = [];
        foreach ($uploads as $upload) {
            if (!$upload || !$upload->isValid()) continue;
            $safeName = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
            $extension = strtolower($upload->getClientOriginalExtension());
            $filename = Str::uuid()->toString().'-'.($safeName ?: 'file').($extension ? '.'.$extension : '');
            $path = $upload->storeAs('chat/'.$thread->workspace_id.'/'.$thread->id, $filename, 'local');
            if (!$path) throw ValidationException::withMessages(['attachments' => 'One attachment could not be stored. Check storage permissions.']);
            $attachment = ChatAttachment::create([
                'workspace_id' => $thread->workspace_id,
                'user_id' => $request->user()->id,
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType(),
                'size_bytes' => $upload->getSize() ?: 0,
            ]);
            $result[] = [
                'id' => $attachment->id,
                'path' => $attachment->path,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ];
        }
        return $result;
    }

    private function authorizeThread(Request $request, ChatThread $thread): void
    {
        abort_unless((int) $thread->user_id === (int) $request->user()->id, 403);
    }
}
