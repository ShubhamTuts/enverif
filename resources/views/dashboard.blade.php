@extends('layouts.app')
@section('title', __('ui.overview'))
@section('content')
<div class="page-head">
  <div><h1>{{ __('ui.overview') }}</h1><p>{{ __('ui.tagline') }} {{ __('ui.no_lock_in') }}</p></div>
  <div class="page-actions"><a class="btn" href="{{ route('leads.create') }}">+ {{ __('ui.new_lead') }}</a><a class="btn btn-primary" href="{{ route('agents.create') }}">+ {{ __('ui.new_agent') }}</a></div>
</div>
<div class="grid grid-4">
  @foreach([['agents',$metrics['agents'],__('ui.active')],['leads',$metrics['leads'],__('ui.hot_leads')],['campaigns',$metrics['campaigns'],__('ui.status')],['approvals',$metrics['approvals'],__('ui.needs_approval')]] as $m)
    <div class="card metric"><div class="metric-label">{{ __('ui.'.$m[0]) }}</div><div class="metric-value">{{ number_format($m[1]) }}</div><div class="metric-note">{{ $m[2] }}</div></div>
  @endforeach
</div>
<div class="grid grid-3" style="margin-top:16px">
  <section class="card span-2"><div class="card-head"><h2>{{ __('ui.recent_runs') }}</h2><a class="small muted" href="{{ route('agents.index') }}">{{ __('ui.agents') }} →</a></div>
    @if($runs->isEmpty()) <x-empty :title="__('ui.empty_agents')" /> @else
    <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.agent') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.created') }}</th><th>{{ __('ui.result') }}</th></tr></thead><tbody>
      @foreach($runs as $run)<tr><td><a class="row-title" href="{{ route('runs.show',$run) }}">{{ $run->agent?->name ?? '—' }}</a></td><td><x-badge :status="$run->status" /></td><td class="muted small">{{ $run->created_at?->diffForHumans() }}</td><td class="truncate" style="max-width:360px">{{ $run->output ?: $run->stop_reason ?: '—' }}</td></tr>@endforeach
    </tbody></table></div>@endif
  </section>
  <section class="card"><div class="card-head"><h2>{{ __('ui.next_runs') }}</h2><a class="small muted" href="{{ route('schedules.index') }}">{{ __('ui.schedules') }} →</a></div><div class="card-pad stack">
    @forelse($schedules as $s)<div class="between"><div><div class="row-title">{{ $s->name }}</div><div class="small muted">{{ $s->agent?->name }} · <span class="mono">{{ $s->cron_expression }}</span></div></div><div class="small nowrap">{{ $s->next_run_at?->diffForHumans() }}</div></div>@empty<div class="muted small">{{ __('ui.empty_schedules') }}</div>@endforelse
  </div></section>
</div>
<div class="grid grid-2" style="margin-top:16px">
  <section class="card"><div class="card-head"><h2>{{ __('ui.hot_leads') }}</h2><a class="small muted" href="{{ route('leads.index') }}">{{ __('ui.leads') }} →</a></div><div class="card-pad stack">
    @forelse($hotLeads as $lead)<a class="between" href="{{ route('leads.show',$lead) }}"><div class="inline"><div class="score">{{ $lead->score }}</div><div><div class="row-title">{{ $lead->name ?: $lead->company ?: $lead->email ?: 'Lead #'.$lead->id }}</div><div class="small muted">{{ $lead->company }}{{ $lead->title ? ' · '.$lead->title : '' }}</div></div></div><span>→</span></a>@empty<div class="muted small">{{ __('ui.empty_leads') }}</div>@endforelse
  </div></section>
  <section class="card"><div class="card-head"><h2>{{ __('ui.extend') }}</h2></div><div class="grid grid-2 card-pad">
    <a class="integration-card card" href="{{ route('connectors.index') }}"><div class="integration-icon">↗</div><div><h3>{{ __('ui.connectors') }}</h3><p>{{ __('ui.dashboard_connector_copy') }}</p></div></a>
    <a class="integration-card card" href="{{ route('skills.index') }}"><div class="integration-icon">⌁</div><div><h3>{{ __('ui.skills') }}</h3><p>{{ __('ui.dashboard_skills_copy') }}</p></div></a>
  </div></section>
</div>
@endsection
