@extends('layouts.app')
@section('title', __('ui.agents'))
@section('content')
<div class="page-head"><div><h1>{{ __('ui.agents') }}</h1><p>{{ __('ui.agent_list_desc') }}</p></div><a class="btn btn-primary" href="{{ route('agents.create') }}">+ {{ __('ui.new_agent') }}</a></div>
<div class="card"><div class="toolbar"><form method="get"><input class="field" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.search') }}"><select class="select field-sm" name="status" data-auto-submit><option value="">{{ __('ui.status') }}</option><option value="active" @selected(request('status')==='active')>{{ __('ui.active') }}</option><option value="paused" @selected(request('status')==='paused')>{{ __('ui.paused') }}</option></select><button class="btn">{{ __('ui.search') }}</button></form></div>
@if($agents->isEmpty())<x-empty :title="__('ui.empty_agents')" :href="route('agents.create')" :actionLabel="__('ui.new_agent')" />@else
<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.model') }}</th><th>{{ __('ui.runs') }}</th><th>{{ __('ui.schedules') }}</th><th>{{ __('ui.actions') }}</th></tr></thead><tbody>
@foreach($agents as $agent)<tr><td><a class="row-title" href="{{ route('agents.show',$agent) }}">{{ $agent->name }}</a><div class="small muted">{{ Str::limit($agent->description,80) }}</div></td><td><x-badge :status="$agent->status" /></td><td class="mono small">{{ $agent->model ?: 'default' }}</td><td>{{ $agent->runs_count }}</td><td>{{ $agent->schedules_count }}</td><td class="nowrap"><a class="btn btn-sm" href="{{ route('agents.edit',$agent) }}">{{ __('ui.edit') }}</a></td></tr>@endforeach
</tbody></table></div><x-pagination :paginator="$agents" />@endif</div>
@endsection
