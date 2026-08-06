@extends('layouts.app')
@section('title', $campaign->name)
@section('content')
<div class="page-head">
    <div>
        <div class="inline"><h1>{{ $campaign->name }}</h1><x-badge :status="$campaign->status" /></div>
        <p>{{ $campaign->description }}</p>
    </div>
    <a class="btn" href="{{ route('campaigns.edit',$campaign) }}">{{ __('ui.edit') }}</a>
</div>

<div class="grid grid-3">
    <section class="card span-2">
        <div class="card-head"><h2>{{ __('ui.sequence') }}</h2><span class="small muted">{{ $campaign->steps->count() }} {{ __('ui.steps') }}</span></div>
        <div class="card-pad">
            @forelse($campaign->steps as $step)
                <div class="run-step">
                    <div class="step-num">{{ $step->position }}</div>
                    <div>
                        <div class="row-title">{{ ucfirst($step->channel) }} · {{ $step->action }}</div>
                        <div class="small muted">{{ __('ui.wait_minutes', ['minutes' => $step->delay_minutes]) }} · {{ $step->requires_approval ? __('ui.requires_approval') : __('ui.policy_controlled') }}</div>
                        @if(data_get($step->content,'template'))<div class="code" style="margin-top:8px">{{ data_get($step->content,'template') }}</div>@endif
                    </div>
                    <form method="post" action="{{ route('campaigns.steps.destroy',[$campaign,$step]) }}" data-confirm="{{ __('ui.delete_step_confirm') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" aria-label="{{ __('ui.delete') }}">×</button></form>
                </div>
            @empty
                <div class="muted">{{ __('ui.no_sequence') }}</div>
            @endforelse
        </div>
    </section>

    <aside class="card card-pad">
        <h3 class="section-title">{{ __('ui.add_step') }}</h3>
        <form class="stack" method="post" action="{{ route('campaigns.steps.store',$campaign) }}">
            @csrf
            <div class="form-group"><label class="form-label">{{ __('ui.channel') }}</label><select class="select" name="channel">@foreach(['email','linkedin','call','research','webhook'] as $ch)<option value="{{ $ch }}">{{ ucfirst($ch) }}</option>@endforeach</select></div>
            <div class="form-group"><label class="form-label">{{ __('ui.action_label') }}</label><input class="field" name="action" placeholder="prepare_email" required></div>
            <div class="form-group"><label class="form-label">{{ __('ui.delay') }} ({{ __('ui.minutes') }})</label><input class="field" type="number" min="0" name="delay_minutes" value="0" required></div>
            <div class="form-group"><label class="form-label">{{ __('ui.template') }}</label><textarea class="textarea" name="content" style="min-height:100px"></textarea></div>
            <label class="inline"><input type="checkbox" name="requires_approval" value="1" checked><span>{{ __('ui.requires_approval') }}</span></label>
            <button class="btn btn-primary">{{ __('ui.add_step') }}</button>
        </form>
    </aside>
</div>

<section class="card" style="margin-top:16px">
    <div class="card-head">
        <div><h2>{{ __('ui.leads') }}</h2><div class="small muted">{{ __('ui.campaign_members') }}</div></div>
        @if($availableLeads->isNotEmpty())
            <form class="inline" method="post" action="{{ route('campaigns.leads.store',$campaign) }}">@csrf
                <select class="select" name="lead_ids[]" multiple size="1" aria-label="{{ __('ui.select_leads') }}" required style="min-width:240px">
                    @foreach($availableLeads as $availableLead)
                        <option value="{{ $availableLead->id }}">{{ $availableLead->name ?: $availableLead->email }}{{ $availableLead->company ? ' · '.$availableLead->company : '' }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">{{ __('ui.add_leads') }}</button>
            </form>
        @endif
    </div>
    @if($members->isEmpty())
        <div class="card-pad muted">{{ __('ui.no_campaign_leads') }}</div>
    @else
        <div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.lead') }}</th><th>{{ __('ui.company') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.current_step') }}</th><th></th></tr></thead><tbody>
        @foreach($members as $lead)
            <tr>
                <td><a class="row-title" href="{{ route('leads.show',$lead) }}">{{ $lead->name ?: $lead->email }}</a></td>
                <td>{{ $lead->company }}</td>
                <td><x-badge :status="$lead->pivot->status" /></td>
                <td>{{ $lead->pivot->current_step ?? 0 }}</td>
                <td class="actions"><form method="post" action="{{ route('campaigns.leads.destroy',[$campaign,$lead]) }}" data-confirm="{{ __('ui.remove_lead_confirm') }}">@csrf @method('DELETE')<button class="btn btn-sm">{{ __('ui.remove') }}</button></form></td>
            </tr>
        @endforeach
        </tbody></table></div>
        <x-pagination :paginator="$members" />
    @endif
</section>
@endsection
