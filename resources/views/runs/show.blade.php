@extends('layouts.app')
@section('title', __('ui.run').' '.$run->id)
@section('content')
@php
    $activeStatuses = ['running', 'queued', 'awaiting_approval', 'waiting_child'];
    $waitingText = match ($run->status) {
        'awaiting_approval' => __('ui.approval_paused'),
        'waiting_child' => __('ui.waiting_delegated_agent'),
        default => __('ui.agent_working'),
    };
@endphp
<div class="page-head">
    <div>
        <div class="inline"><h1>{{ $run->agent?->name ?? __('ui.run') }}</h1><x-badge :status="$run->status" /></div>
        <p class="mono">{{ $run->id }}</p>
    </div>
    <div class="page-actions">
        @if(in_array($run->status, $activeStatuses, true))
            <form method="post" action="{{ route('runs.cancel', $run) }}">@csrf<button class="btn btn-danger">{{ __('ui.cancel') }}</button></form>
        @elseif(in_array($run->status, ['failed', 'cancelled'], true))
            <form method="post" action="{{ route('runs.retry', $run) }}">@csrf<button class="btn">{{ __('ui.retry') }}</button></form>
        @endif
    </div>
</div>

<div class="grid grid-3">
    <section class="card span-2">
        <div class="card-head"><h2>{{ __('ui.result') }}</h2><span class="small muted" id="run-state">{{ $run->status }}</span></div>
        <div class="card-pad">
            <div class="small muted">{{ __('ui.input') }}</div>
            <div class="code" style="margin:7px 0 16px">{{ $run->input }}</div>
            <div class="run-console" id="run-output">{{ $run->output ?: $waitingText }}</div>
        </div>
    </section>
    <aside class="card card-pad">
        <div class="stack">
            <div class="between"><span>{{ __('ui.turns') }}</span><strong id="run-steps">{{ $run->step_count }}</strong></div>
            <div class="between"><span>{{ __('ui.tokens') }}</span><strong id="run-tokens">{{ number_format($run->input_tokens + $run->output_tokens) }}</strong></div>
            <div class="between"><span>{{ __('ui.cost') }}</span><strong id="run-cost">${{ number_format((float) $run->estimated_cost_usd, 4) }}</strong></div>
            <div class="between"><span>{{ __('ui.stop') }}</span><strong>{{ $run->stop_reason ?: '—' }}</strong></div>
            @if($run->parent)
                <div class="divider"></div>
                <div class="small muted">{{ __('ui.parent_run') }}</div>
                <a class="mono" href="{{ route('runs.show', $run->parent) }}">{{ $run->parent->id }}</a>
            @endif
            @if($run->children->isNotEmpty())
                <div class="divider"></div>
                <div class="small muted">{{ __('ui.delegated_runs') }}</div>
                <div class="stack" style="gap:6px">
                    @foreach($run->children as $child)
                        <a class="between" href="{{ route('runs.show', $child) }}"><span class="mono">{{ Str::limit($child->id, 18) }}</span><x-badge :status="$child->status" /></a>
                    @endforeach
                </div>
            @endif
            <div class="divider"></div>
            <div class="small muted">{{ __('ui.started') }}</div><div>{{ $run->started_at?->format('M j, Y H:i:s') ?? '—' }}</div>
            <div class="small muted">{{ __('ui.finished') }}</div><div>{{ $run->finished_at?->format('M j, Y H:i:s') ?? '—' }}</div>
        </div>
    </aside>
</div>

<section class="card" style="margin-top:16px">
    <div class="card-head"><h2>{{ __('ui.run_trace') }}</h2><span class="small muted">{{ __('ui.durable_steps') }}</span></div>
    <div class="card-pad" id="run-trace">
        @forelse($run->steps as $step)
            @php $childRunId = data_get($step->input, 'child_run_id'); @endphp
            <div class="run-step">
                <div class="step-num">{{ $step->sequence }}</div>
                <div>
                    <div class="row-title">{{ $step->tool ?: $step->type }}</div>
                    <div class="small muted">{{ $step->status }} · <span class="risk-{{ $step->risk_level }}">{{ $step->risk_level ?: 'internal' }}</span></div>
                    @if($childRunId)
                        <div style="margin-top:8px"><a class="mono" href="{{ route('runs.show', $childRunId) }}">{{ __('ui.delegated_run') }} → {{ $childRunId }}</a></div>
                    @endif
                    @if($step->output)<div class="code" style="margin-top:8px">{{ Str::limit($step->output, 2000) }}</div>@endif
                </div>
                <span class="small muted">{{ $step->finished_at?->diffForHumans() }}</span>
            </div>
        @empty
            <div class="muted">{{ __('ui.no_tool_steps') }}</div>
        @endforelse
    </div>
</section>

@if(in_array($run->status, $activeStatuses, true))
<script>
const runCopy = {
    approval: @json(__('ui.approval_paused')),
    delegated: @json(__('ui.waiting_delegated_agent')),
    working: @json(__('ui.agent_working')),
};
setInterval(async () => {
    try {
        const response = await fetch(@json(route('runs.status', $run)), {headers: {'Accept': 'application/json'}});
        if (!response.ok) return;
        const data = await response.json();
        document.getElementById('run-state').textContent = data.status;
        const fallback = data.status === 'awaiting_approval' ? runCopy.approval : (data.status === 'waiting_child' ? runCopy.delegated : runCopy.working);
        document.getElementById('run-output').textContent = data.output || fallback;
        document.getElementById('run-steps').textContent = data.step_count;
        document.getElementById('run-tokens').textContent = Number(data.tokens || 0).toLocaleString();
        document.getElementById('run-cost').textContent = '$' + Number(data.cost || 0).toFixed(4);
        if (['completed', 'failed', 'cancelled'].includes(data.status)) location.reload();
    } catch (error) {}
}, 3000);
</script>
@endif
@endsection
