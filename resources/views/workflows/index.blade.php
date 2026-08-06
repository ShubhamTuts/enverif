@extends('layouts.app')
@section('title', __('ui.workflows'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ __('ui.workflows') }}</h1>
        <p>{{ __('ui.workflows_desc') }}</p>
    </div>
    <a class="btn btn-primary" href="{{ route('workflows.create') }}">+ {{ __('ui.new_workflow') }}</a>
</div>

<div class="toolbar">
<form class="filter-row" method="get">
    <input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search_workflows') }}">
    <select class="select" name="status">
        <option value="">{{ __('ui.all_statuses') }}</option>
        @foreach(['draft', 'active', 'paused'] as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    <button class="btn">{{ __('ui.filter') }}</button>
</form>
</div>

@if($workflows->isEmpty())
    <x-empty :title="__('ui.no_workflows')" :description="__('ui.no_workflows_desc')" />
@else
    <div class="grid grid-3">
        @foreach($workflows as $workflow)
            <a class="card workflow-card" href="{{ route('workflows.show', $workflow) }}">
                <div class="between">
                            <div class="workflow-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5h5v5H5V5Zm9 9h5v5h-5v-5ZM10 7.5h4a3 3 0 0 1 3 3V14M7.5 10v4a3 3 0 0 0 3 3H14"/></svg>
                            </div>
                    <x-badge :status="$workflow->status" />
                </div>
                <h3>{{ $workflow->name }}</h3>
                <p>{{ $workflow->description ?: __('ui.durable_revenue_workflow') }}</p>
                <div class="workflow-card-meta">
                    <span>{{ count($workflow->definition['nodes'] ?? []) }} {{ __('ui.nodes') }}</span>
                    <span>{{ $workflow->runs_count }} {{ __('ui.runs') }}</span>
                    <span>v{{ $workflow->version }}</span>
                </div>
            </a>
        @endforeach
    </div>
    <div class="card" style="margin-top:14px">
        <x-pagination :paginator="$workflows" />
    </div>
@endif
@endsection
