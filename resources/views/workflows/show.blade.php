@extends('layouts.app')
@section('title', $workflow->name)

@section('content')
<div class="page-head">
    <div>
        <div class="inline">
            <h1>{{ $workflow->name }}</h1>
            <x-badge :status="$workflow->status" />
        </div>
        <p>{{ $workflow->description ?: __('ui.durable_revenue_workflow') }} · v{{ $workflow->version }}</p>
    </div>
    <div class="inline">
        <a class="btn" href="{{ route('workflows.edit', $workflow) }}">{{ __('ui.edit_builder') }}</a>
        <form method="post" action="{{ route('workflows.destroy', $workflow) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger" data-confirm="{{ __('ui.delete_workflow_confirm') }}">{{ __('ui.delete') }}</button>
        </form>
    </div>
</div>

@if($runtimeErrors)
    <div class="notice bad">
        <div>
            <strong>Workflow is not executable yet.</strong>
            <ul class="error-list">
                @foreach($runtimeErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <p class="small">Fix these nodes before activation or execution. Dry-run and live-run both validate resources first.</p>
        </div>
    </div>
@else
    <div class="notice good">✓ <span>Runtime validation passed. Referenced agents, skills, connectors and campaigns are available.</span></div>
@endif

<div class="grid grid-2">
    <section class="card card-pad">
        <h2 class="section-title">{{ __('ui.run_now') }}</h2>
        <form method="post" action="{{ route('workflows.run', $workflow) }}">
            @csrf
            <div class="form-group">
                <label class="form-label">{{ __('ui.prompt_objective') }}</label>
                <textarea class="textarea" name="prompt" rows="5" placeholder="Find 20 qualified prospects and prepare personalized outreach..."></textarea>
            </div>
            <details>
                <summary class="small muted">{{ __('ui.advanced_json_input') }}</summary>
                <textarea class="textarea code" name="input_json" rows="5" placeholder='{"segment":"SaaS","country":"US"}'></textarea>
            </details>
            <div class="inline" style="margin-top:12px">
                <button class="btn btn-primary" @disabled($runtimeErrors)>{{ __('ui.run_workflow') }}</button>
                <button class="btn" formaction="{{ route('workflows.test', $workflow) }}" @disabled($runtimeErrors)>Test run (no external effects)</button>
            </div>
        </form>
    </section>

    <section class="card card-pad">
        <h2 class="section-title">{{ __('ui.webhook_trigger') }}</h2>
        <p class="help">{{ __('ui.webhook_help') }}</p>
        <div class="code">{{ route('workflows.webhook', [$workflow, $workflow->webhook_secret]) }}</div>
        <form method="post" action="{{ route('workflows.webhook.regenerate', $workflow) }}" style="margin-top:10px">
            @csrf
            <button class="btn btn-sm">{{ __('ui.regenerate_secret') }}</button>
        </form>
        <div class="divider"></div>
        <h2 class="section-title">{{ __('ui.safety') }}</h2>
        <div class="stack small">
            <div class="between">
                <span>{{ __('ui.autonomous_external_writes') }}</span>
                <x-badge :status="data_get($workflow->settings, 'allow_external_writes') ? 'active' : 'pending'" />
            </div>
            <div class="between">
                <span>{{ __('ui.destructive_enabled') }}</span>
                <x-badge :status="data_get($workflow->settings, 'allow_destructive') ? 'warn' : 'paused'" />
            </div>
        </div>
    </section>
</div>

<section class="card" style="margin-top:16px">
    <div class="card-head"><h2>{{ __('ui.recent_runs') }}</h2></div>
    @if($runs->isEmpty())
        <x-empty :title="__('ui.no_workflow_runs')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('ui.started') }}</th><th>Mode</th><th>{{ __('ui.trigger') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.current_node') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($runs as $run)
                        <tr>
                            <td>{{ $run->created_at->diffForHumans() }}</td>
                            <td>{{ $run->mode === 'dry_run' ? 'Test' : 'Live' }}</td>
                            <td>{{ $run->trigger }}</td>
                            <td><x-badge :status="$run->status" /></td>
                            <td>{{ $run->current_node_id ?: '—' }}</td>
                            <td><a class="btn btn-sm" href="{{ route('workflow-runs.show', $run) }}">{{ __('ui.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$runs" />
    @endif
</section>
@endsection
