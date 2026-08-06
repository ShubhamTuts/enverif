@if($thread && $thread->messages->isNotEmpty())
    <div class="chat-transcript" data-chat-transcript>
        @foreach($thread->messages as $message)
            @php
                $execution = data_get($message->meta, 'execution', []);
                $messageAgentId = $message->role === 'assistant'
                    ? (int) data_get($message->meta, 'agent_id', $thread->default_agent_id)
                    : 0;
                $messageAgentName = $message->role === 'assistant'
                    ? (data_get($message->meta, 'agent_name') ?: ($thread->defaultAgent?->name ?? 'Enverif'))
                    : __('ui.you');
            @endphp

            <article class="chat-message {{ $message->role }}" data-message-id="{{ $message->id }}">
                <div class="message-avatar">
                    @if($message->role === 'assistant')
                        @if($messageAgentId)
                            <img src="{{ route('agents.avatar', $messageAgentId) }}" alt="">
                        @else
                            <img src="{{ asset('assets/enverif-mark.svg') }}" alt="Enverif">
                        @endif
                    @else
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    @endif
                </div>

                <div class="message-body">
                    <div class="message-meta">
                        <span>{{ $messageAgentName }}</span>

                        @if($message->role === 'assistant' && $message->kind === 'final')
                            <span class="final-chip">Final</span>
                        @endif

                        @if($message->run_id && $message->role === 'assistant')
                            <a href="{{ route('runs.show', $message->run_id) }}">{{ __('ui.view_run') }}</a>
                        @endif
                    </div>

                    @if($message->content !== '')
                        @if($message->role === 'assistant')
                            <div class="message-content markdown-body">{!! \Illuminate\Support\Str::markdown(
                                $message->content,
                                ['html_input' => 'strip', 'allow_unsafe_links' => false]
                            ) !!}</div>
                        @else
                            <div class="message-content">{!! nl2br(e($message->content)) !!}</div>
                        @endif
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
                                <span class="mention-chip mention-{{ $mention['type'] ?? 'context' }}">{{ '@'.($mention['type'] ?? 'context') }} {{ $mention['label'] }}</span>
                            @endforeach

                            @if(data_get($message->meta, 'effort'))
                                <span class="mention-chip mention-effort">{{ ucfirst(data_get($message->meta, 'effort')) }}</span>
                            @endif
                        </div>
                    @elseif($message->role === 'assistant' && $execution)
                        <div class="message-tags">
                            <span class="mention-chip mention-context">{{ ucfirst($execution['provider'] ?? 'model') }}</span>
                            @if(! empty($execution['model']))
                                <span class="mention-chip mention-skill">{{ $execution['model'] }}</span>
                            @endif
                            <span class="mention-chip mention-effort">{{ ucfirst($execution['effort'] ?? 'standard') }}</span>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @php
        $lastUser = $thread->messages->where('role', 'user')->last();
        $hasAssistant = $lastUser
            && $thread->messages
                ->where('role', 'assistant')
                ->where('run_id', $lastUser->run_id)
                ->isNotEmpty();
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
