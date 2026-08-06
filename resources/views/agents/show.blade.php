@extends('layouts.app')
@section('title', $agent->name)
@section('content')
<div class="page-head"><div><div class="inline"><h1>{{ $agent->name }}</h1><x-badge :status="$agent->status" /></div><p>{{ $agent->description }}</p></div><div class="page-actions"><a class="btn" href="{{ route('agents.edit',$agent) }}">{{ __('ui.edit') }}</a></div></div>

<div class="grid grid-3">
    <section class="card span-2">
        <div class="card-head"><h2>{{ __('ui.run_agent') }}</h2></div>
        <form class="card-pad" method="post" action="{{ route('agents.run',$agent) }}">@csrf
            <div class="form-group"><label class="form-label">{{ __('ui.prompt') }}</label><textarea class="textarea" name="prompt" required placeholder="{{ __('ui.agent_prompt_example') }}"></textarea></div>
            <div style="margin-top:12px"><button class="btn btn-primary">{{ __('ui.run_agent') }}</button></div>
        </form>
    </section>
    <aside class="card card-pad"><div class="stack"><div><div class="small muted">{{ __('ui.model') }}</div><div class="row-title">{{ $agent->model ?: $agent->modelConnection?->default_model ?: __('ui.provider_default') }}</div><div class="small muted">{{ $agent->modelConnection?->name ?: __('ui.no_connection') }}</div></div><div class="divider"></div><div class="between"><span>{{ __('ui.max_steps') }}</span><strong>{{ $agent->max_steps }}</strong></div><div class="between"><span>{{ __('ui.max_runtime') }}</span><strong>{{ $agent->max_runtime_seconds }}s</strong></div><div class="between"><span>{{ __('ui.max_cost') }}</span><strong>${{ number_format((float)$agent->max_cost_usd,2) }}</strong></div></div></aside>
</div>

<div class="grid grid-3" style="margin-top:16px">
    <section class="card span-2"><div class="card-head"><h2>{{ __('ui.recent_runs') }}</h2></div>@if($runs->isEmpty())<x-empty title="{{ __('ui.no_runs') }}" />@else<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.created') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.result') }}</th><th>{{ __('ui.turns') }}</th></tr></thead><tbody>@foreach($runs as $run)<tr><td><a class="row-title" href="{{ route('runs.show',$run) }}">{{ $run->created_at?->format('M j, H:i') }}</a></td><td><x-badge :status="$run->status" /></td><td class="truncate" style="max-width:430px">{{ $run->output ?: $run->stop_reason ?: '—' }}</td><td>{{ $run->step_count }}</td></tr>@endforeach</tbody></table></div><x-pagination :paginator="$runs" />@endif</section>
    <aside class="card card-pad"><h3 class="section-title">{{ __('ui.capabilities_title') }}</h3><div class="stack"><div><div class="small muted">{{ __('ui.skills') }}</div><div style="margin-top:6px">@forelse($agent->skills as $s)<span class="badge info">{{ $s->name }}</span> @empty<span class="muted small">{{ __('ui.none') }}</span>@endforelse</div></div><div><div class="small muted">{{ __('ui.connectors') }}</div><div style="margin-top:6px">@forelse($agent->connectors as $c)<span class="badge">{{ $c->name }}</span> @empty<span class="muted small">{{ __('ui.none') }}</span>@endforelse</div></div><div class="divider"></div><div class="small muted">{{ __('ui.instructions') }}</div><div class="code">{{ $agent->instructions }}</div></div></aside>
</div>

<section class="card" style="margin-top:16px">
    <div class="card-head"><div><h2>{{ __('ui.durable_memory') }}</h2><div class="small muted">{{ __('ui.memory_desc') }}</div></div></div>
    <div class="grid grid-3 card-pad" style="align-items:start">
        <div class="span-2">
            @if($memories->isEmpty())
                <x-empty :title="__('ui.no_memory')" :text="__('ui.no_memory_desc')" />
            @else
                <div class="stack">
                    @foreach($memories as $memory)
                        <article class="card card-pad">
                            <div class="between"><div><div class="row-title">{{ $memory->key }}</div><div class="small muted">{{ __('ui.importance') }} {{ $memory->importance }} · {{ $memory->updated_at?->diffForHumans() }}</div></div><form method="post" action="{{ route('agents.memories.destroy',[$agent,$memory]) }}" data-confirm="{{ __('ui.delete_memory_confirm') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">{{ __('ui.delete') }}</button></form></div>
                            <div style="white-space:pre-wrap;margin-top:10px">{{ $memory->value }}</div>
                            @if($memory->tags)<div style="margin-top:10px">@foreach($memory->tags as $tag)<span class="badge">{{ $tag }}</span> @endforeach</div>@endif
                        </article>
                    @endforeach
                </div>
                <x-pagination :paginator="$memories" />
            @endif
        </div>
        <aside>
            <h3 class="section-title">{{ __('ui.add_memory') }}</h3>
            <form class="stack" method="post" action="{{ route('agents.memories.store',$agent) }}">@csrf
                <div class="form-group"><label class="form-label">{{ __('ui.memory_key') }}</label><input class="field" name="key" maxlength="160" required></div>
                <div class="form-group"><label class="form-label">{{ __('ui.memory_value') }}</label><textarea class="textarea" name="value" maxlength="20000" required></textarea></div>
                <div class="form-group"><label class="form-label">{{ __('ui.tags') }}</label><input class="field" name="tags" placeholder="icp, objections, account"></div>
                <div class="form-group"><label class="form-label">{{ __('ui.importance') }}</label><input class="field" type="number" min="0" max="100" name="importance" value="50" required></div>
                <div class="help">{{ __('ui.memory_secret_warning') }}</div>
                <button class="btn btn-primary">{{ __('ui.save_memory') }}</button>
            </form>
        </aside>
    </div>
</section>
@endsection
