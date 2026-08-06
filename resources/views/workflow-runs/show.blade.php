@extends('layouts.app')
@section('title', __('ui.workflow_run'))

@section('content')
<div class="page-head">
    <div>
        <div class="inline">
            <h1>{{ $run->workflow->name }}</h1>
            <x-badge :status="$run->status" />
            @if($run->mode === 'dry_run')
                <span class="thread-chip">Dry run</span>
            @endif
        </div>
        <p>
            Workflow run <code>{{ $run->id }}</code> · {{ $run->trigger }} trigger
            @if($run->retry_of)
                · retry of <a href="{{ route('workflow-runs.show', $run->retry_of) }}"><code>{{ $run->retry_of }}</code></a>
            @endif
        </p>
    </div>
    <div class="inline">
        <a class="btn" href="{{ route('workflows.show', $run->workflow) }}">{{ __('ui.workflow') }}</a>
        @if(in_array($run->status, ['failed', 'completed'], true))
            <form method="post" action="{{ route('workflow-runs.retry', $run) }}">
                @csrf
                <button class="btn">Retry as new run</button>
            </form>
        @endif
        @unless(in_array($run->status, ['completed', 'cancelled'], true))
            <form method="post" action="{{ route('workflow-runs.resume', $run) }}">
                @csrf
                <button class="btn">Re-queue</button>
            </form>
            <form method="post" action="{{ route('workflow-runs.cancel', $run) }}">
                @csrf
                <button class="btn btn-danger">{{ __('ui.cancel_run') }}</button>
            </form>
        @endunless
    </div>
</div>

@if($run->error)
    <div class="notice bad">
        <div>
            <strong>Execution failed</strong>
            <p>{{ $run->error }}</p>
            <p class="small">Inspect the failing node below, correct its configuration or connection, then retry as a new immutable run.</p>
        </div>
    </div>
@endif

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><h2>{{ __('ui.execution') }}</h2></div>
        <div class="card-pad workflow-run-list" data-workflow-run data-current-status="{{ $run->status }}" data-step-count="{{ $run->steps->count() }}" data-status-url="{{ route('workflow-runs.status', $run) }}">
            @forelse($run->steps as $step)
                <details class="run-step-detail" @if($step->status === 'failed') open @endif>
                    <summary class="run-step">
                        <div class="step-num">{{ $loop->iteration }}</div>
                        <div>
                            <div class="row-title">{{ $step->node_id }} <span class="muted">· {{ $step->node_type }}</span></div>
                            @if($step->error)
                                <div class="small text-bad">{{ $step->error }}</div>
                            @elseif($step->output)
                                <div class="small muted truncate">{{ json_encode($step->output, JSON_UNESCAPED_SLASHES) }}</div>
                            @endif
                        </div>
                        <x-badge :status="$step->status" />
                    </summary>
                    <div class="run-step-inspector">
                        <strong>Input</strong>
                        <div class="code">{{ json_encode($step->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                        <strong>Output</strong>
                        <div class="code">{{ json_encode($step->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                    </div>
                </details>
            @empty
                <p class="muted">{{ __('ui.waiting_first_node') }}</p>
            @endforelse
        </div>
    </section>

    <section class="card card-pad">
        <h2 class="section-title">{{ __('ui.run_context') }}</h2>
        <div class="code">{{ json_encode($run->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
        @if($run->output)
            <h2 class="section-title" style="margin-top:18px">{{ __('ui.output') }}</h2>
            <div class="code">{{ json_encode($run->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
        @endif
    </section>
</div>
@endsection
