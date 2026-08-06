@extends('layouts.app')
@section('title', $thread?->title ?? __('ui.new_chat'))
@section('crumb', $thread?->title ?? __('ui.new_chat'))
@section('body-class', 'chat-page')
@section('content-class', 'chat-content')

@section('content')
@php
    $currentConnection = $modelConnections->firstWhere('id', (int) $selectedConnectionId);
    $currentModels = $currentConnection ? ($modelCatalog[$currentConnection->provider] ?? []) : [];
    $customSelectedModel = $selectedModel !== ''
        && $currentConnection
        && ! in_array($selectedModel, $currentModels, true);
@endphp

<div
    class="chat-shell"
    data-chat-shell
    data-model-catalog='@json($modelCatalog)'
    @if($thread)
        data-status-url="{{ route('chat.status', $thread) }}"
        data-thread-id="{{ $thread->id }}"
    @endif
>
    @if($thread)
        <div class="chat-thread-toolbar">
            <div class="chat-thread-state">
                <span class="status-dot"></span>
                <span>{{ $thread->defaultAgent?->name ?: __('ui.agent') }}</span>
                @if($thread->default_effort)
                    <span class="thread-chip">{{ ucfirst($thread->default_effort) }}</span>
                @endif
            </div>

            <details class="thread-menu">
                <summary aria-label="Chat actions">•••</summary>
                <div class="thread-menu-popover">
                    <form method="post" action="{{ route('chat.rename', $thread) }}">
                        @csrf
                        @method('PUT')
                        <label>
                            Rename chat
                            <input class="field" name="title" value="{{ $thread->title }}" maxlength="160" required>
                        </label>
                        <button class="btn btn-sm">Save name</button>
                    </form>

                    <form method="post" action="{{ route('chat.archive', $thread) }}">
                        @csrf
                        <button class="btn btn-sm">{{ $thread->archived_at ? 'Restore chat' : 'Archive chat' }}</button>
                    </form>

                    <form method="post" action="{{ route('chat.destroy', $thread) }}" onsubmit="return confirm('Delete this chat and its private attachments?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger">Delete chat</button>
                    </form>
                </div>
            </details>
        </div>
    @endif

    <div class="chat-scroll" data-chat-scroll>
        @if(! $thread || $thread->messages->isEmpty())
            @if($chatQuery !== '' || $showArchived)
                <div class="chat-history-results">
                    <div class="between">
                        <div>
                            <span class="eyebrow">Chat history</span>
                            <h2>{{ $showArchived ? 'Archived chats' : 'Search results' }}</h2>
                        </div>
                        <a class="btn btn-sm" href="{{ route('chat.index') }}">Clear</a>
                    </div>

                    @forelse($threads as $historyThread)
                        <a class="history-result" href="{{ route('chat.show', $historyThread) }}">
                            <strong>{{ $historyThread->title }}</strong>
                            <small>{{ $historyThread->last_message_at?->diffForHumans() }}</small>
                        </a>
                    @empty
                        <p class="muted">No matching chats.</p>
                    @endforelse

                    <x-pagination :paginator="$threads" />
                </div>
            @else
                <div class="chat-welcome">
                    <img src="{{ asset('assets/enverif-mark.svg') }}" alt="">
                    <span class="eyebrow">{{ __('ui.revenue_agent_os') }}</span>
                    <h1>{{ __('ui.chat_welcome_title') }}</h1>
                    <p>{{ __('ui.chat_welcome_desc') }}</p>
                    <div class="prompt-suggestions">
                        <button type="button" data-suggest="{{ __('ui.suggest_prospects_prompt') }}">{{ __('ui.find_qualified_prospects') }}</button>
                        <button type="button" data-suggest="{{ __('ui.suggest_followups_prompt') }}">{{ __('ui.prepare_followups') }}</button>
                        <button type="button" data-suggest="{{ __('ui.suggest_workflow_prompt') }}">{{ __('ui.design_workflow') }}</button>
                    </div>
                </div>
            @endif
        @else
            <div class="chat-transcript" data-chat-transcript>
                @foreach($thread->messages as $message)
                    @php
                        $execution = data_get($message->meta, 'execution', []);
                    @endphp

                    <article class="chat-message {{ $message->role }}" data-message-id="{{ $message->id }}">
                        <div class="message-avatar">
                            @if($message->role === 'assistant')
                                @php
                                    $messageAgentId = (int) data_get($message->meta, 'agent_id', $thread->default_agent_id);
                                @endphp

                                @if($messageAgentId)
                                    <img src="{{ route('agents.avatar', $messageAgentId) }}" alt="">
                                @else
                                    <img src="{{ asset('assets/enverif-mark.svg') }}" alt="Enverif">
                                @endif
                            @else
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            @endif
                        </div>

                        <div class="message-body">
                            <div class="message-meta">
                                <span>{{ $message->role === 'assistant' ? ($thread->defaultAgent?->name ?? 'Enverif') : __('ui.you') }}</span>

                                @if($message->role === 'assistant' && $message->kind === 'final')
                                    <span class="final-chip">Final</span>
                                @endif

                                @if($message->run_id && $message->role === 'assistant')
                                    <a href="{{ route('runs.show', $message->run_id) }}">{{ __('ui.view_run') }}</a>
                                @endif
                            </div>

                            @if($message->content !== '')
                                <div class="message-content">{!! nl2br(e($message->content)) !!}</div>
                            @endif

                            @if($message->attachments->isNotEmpty())
                                <div class="message-attachments">
                                    @foreach($message->attachments as $attachment)
                                        <a class="attachment-chip" href="{{ route('chat.attachments.show', $attachment) }}">
                                            <span>↗</span>
                                            <b>{{ $attachment->original_name }}</b>
                                            <small>{{ number_format($attachment->size_bytes / 1024, 1) }} KB</small>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if($message->role === 'user' && is_array($message->meta))
                                <div class="message-tags">
                                    @foreach(($message->meta['mentions'] ?? []) as $mention)
                                        <span>@{{ $mention['type'] }} {{ $mention['label'] }}</span>
                                    @endforeach

                                    @if(data_get($message->meta, 'model'))
                                        <span>model {{ data_get($message->meta, 'model') }}</span>
                                    @endif

                                    @if(data_get($message->meta, 'effort'))
                                        <span>{{ ucfirst(data_get($message->meta, 'effort')) }}</span>
                                    @endif
                                </div>
                            @elseif($message->role === 'assistant' && $execution)
                                <div class="message-tags">
                                    <span>{{ $execution['provider'] ?? 'model' }}</span>
                                    <span>{{ $execution['model'] ?? '' }}</span>
                                    <span>{{ ucfirst($execution['effort'] ?? 'standard') }}</span>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @php
                $lastUser = $thread->messages->where('role', 'user')->last();
                $hasAssistant = $lastUser
                    && $thread->messages->where('role', 'assistant')->where('run_id', $lastUser->run_id)->isNotEmpty();
            @endphp

            @if($lastUser && ! $hasAssistant)
                <div class="chat-thinking" data-chat-thinking>
                    <img src="{{ asset('assets/enverif-mark.svg') }}" alt="">
                    <span></span><span></span><span></span>
                    <em data-chat-stage>{{ $thread->defaultAgent?->name ?? __('ui.agent') }} {{ __('ui.is_working') }}</em>
                    <form method="post" action="{{ route('chat.stop', $thread) }}">
                        @csrf
                        <button class="btn btn-sm">Stop</button>
                    </form>
                </div>
            @endif
        @endif
    </div>

    <div class="chat-composer-wrap">
        <form class="chat-composer" method="post" enctype="multipart/form-data" action="{{ route('chat.send', $thread) }}" data-chat-form>
            @csrf

            <div class="composer-selection-row">
                <label class="composer-select">
                    <span>Agent</span>
                    <select name="agent_id" data-chat-agent>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((int) $selectedAgentId === $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="composer-select">
                    <span>Connection</span>
                    <select name="model_connection_id" data-chat-model-connection>
                        <option value="">Agent default</option>
                        @foreach($modelConnections as $connection)
                            <option value="{{ $connection->id }}" data-provider="{{ $connection->provider }}" @selected((int) $selectedConnectionId === $connection->id)>
                                {{ $connection->name }} · {{ $connection->provider }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="composer-select">
                    <span>Model</span>
                    <select name="model" data-chat-model>
                        <option value="">Connection default</option>
                        @foreach($currentModels as $modelId)
                            <option value="{{ $modelId }}" @selected(! $customSelectedModel && $selectedModel === $modelId)>{{ $modelId }}</option>
                        @endforeach
                        <option value="__custom__" @selected($customSelectedModel)>Custom model…</option>
                    </select>
                </label>

                <label class="composer-select">
                    <span>Effort</span>
                    <select name="effort" data-chat-effort>
                        <option value="fast" @selected($selectedEffort === 'fast')>Fast</option>
                        <option value="standard" @selected($selectedEffort === 'standard')>Standard</option>
                        <option value="deep" @selected($selectedEffort === 'deep')>Deep</option>
                    </select>
                </label>

                <label class="composer-scope">
                    <input type="checkbox" name="persist_defaults" value="1" checked>
                    <span>Keep for this chat</span>
                </label>
            </div>

            <div class="custom-model-row" data-chat-custom-model-wrap @if(! $customSelectedModel) hidden @endif>
                <input class="field mono" name="custom_model" data-chat-custom-model value="{{ $customSelectedModel ? $selectedModel : '' }}" placeholder="Custom provider model ID">
            </div>

            <div class="attachment-preview" data-attachment-preview></div>
            <textarea name="prompt" data-chat-prompt rows="1" placeholder="{{ __('ui.message_enverif') }}" maxlength="20000">{{ old('prompt') }}</textarea>

            <div class="composer-controls">
                <div class="composer-left">
                    <button type="button" class="composer-plus" data-context-toggle aria-label="{{ __('ui.tag_context') }}">+</button>

                    <label class="composer-attach" title="Attach images or files">
                        <input type="file" name="attachments[]" data-chat-attachments multiple accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,text/markdown,text/csv,application/json,.doc,.docx,.xls,.xlsx">
                        <span>⌁</span>
                    </label>

                    <div class="context-menu" data-context-menu hidden>
                        <div class="context-search">
                            <input type="search" placeholder="Type @agent, @plugin, @skill…" data-context-search>
                        </div>

                        <div class="context-groups">
                            <section>
                                <strong>{{ __('ui.agents') }}</strong>
                                @forelse($agents as $agent)
                                    <label class="context-option" data-context-item data-context-type="agent">
                                        <input type="radio" name="agent_context" value="{{ $agent->id }}" @checked((int) $selectedAgentId === $agent->id) data-agent-context>
                                        <span class="context-icon agent">
                                            @if($agent->avatar_path)
                                                <img src="{{ route('agents.avatar', $agent) }}" alt="">
                                            @else
                                                A
                                            @endif
                                        </span>
                                        <span>
                                            <b>{{ $agent->name }}</b>
                                            <small>{{ $agent->description }}</small>
                                        </span>
                                    </label>
                                @empty
                                    <p>{{ __('ui.no_active_agents') }} <a href="{{ route('agents.create') }}">{{ __('ui.create_one') }}</a>.</p>
                                @endforelse
                            </section>

                            <section>
                                <strong>{{ __('ui.plugins') }}</strong>
                                @foreach($connectors as $connector)
                                    <label class="context-option" data-context-item data-context-type="plugin">
                                        <input type="checkbox" name="connector_ids[]" value="{{ $connector->id }}">
                                        <span class="context-icon plugin">
                                            <img src="{{ \App\Core\Plugins\PluginPresentation::iconFor($connector->driver) }}" alt="">
                                        </span>
                                        <span>
                                            <b>{{ $connector->name }}</b>
                                            <small>{{ $connector->driver }} · by Codefreex</small>
                                        </span>
                                    </label>
                                @endforeach
                            </section>

                            <section>
                                <strong>{{ __('ui.skills') }}</strong>
                                @foreach($skills as $skill)
                                    <label class="context-option" data-context-item data-context-type="skill">
                                        <input type="checkbox" name="skill_ids[]" value="{{ $skill->id }}">
                                        <span class="context-icon skill">S</span>
                                        <span>
                                            <b>{{ $skill->name }}</b>
                                            <small>{{ $skill->slug }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </section>

                            <section>
                                <strong>{{ __('ui.workflows') }}</strong>
                                @foreach($workflows as $workflow)
                                    <label class="context-option" data-context-item data-context-type="workflow">
                                        <input type="checkbox" name="workflow_ids[]" value="{{ $workflow->id }}">
                                        <span class="context-icon workflow">W</span>
                                        <span>
                                            <b>{{ $workflow->name }}</b>
                                            <small>{{ $workflow->description }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </section>

                            <section>
                                <strong>Leads</strong>
                                @foreach($leads as $lead)
                                    <label class="context-option" data-context-item data-context-type="lead">
                                        <input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}">
                                        <span class="context-icon lead">L</span>
                                        <span>
                                            <b>{{ trim($lead->first_name.' '.$lead->last_name) ?: ($lead->company ?: 'Lead #'.$lead->id) }}</b>
                                            <small>{{ $lead->company }} {{ $lead->email }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </section>

                            <section>
                                <strong>Campaigns</strong>
                                @foreach($campaigns as $campaign)
                                    <label class="context-option" data-context-item data-context-type="campaign">
                                        <input type="checkbox" name="campaign_ids[]" value="{{ $campaign->id }}">
                                        <span class="context-icon campaign">C</span>
                                        <span><b>{{ $campaign->name }}</b></span>
                                    </label>
                                @endforeach
                            </section>
                        </div>
                    </div>

                    <div class="selected-context" data-selected-context></div>
                </div>

                <button class="send-button" type="submit" aria-label="{{ __('ui.send') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="m5 12 7-7 7 7M12 5v14"/>
                    </svg>
                </button>
            </div>
        </form>

        <p class="composer-note">{{ __('ui.external_send_note') }} · Files are private to this chat/workspace.</p>
    </div>
</div>
@endsection
