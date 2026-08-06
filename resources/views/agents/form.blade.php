@extends('layouts.app')
@section('title', $agent->exists ? __('ui.edit').' '.$agent->name : __('ui.new_agent'))
@section('content')
@php
    $agentModelCatalogJson = json_encode($modelCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
@endphp
@php($selectedConnectionId = (string) old('model_connection_id',$agent->model_connection_id))
@php($selectedConnection = $models->first(fn($m)=>(string)$m->id===$selectedConnectionId))
@php($knownAgentModels = $selectedConnection ? ($modelCatalog[$selectedConnection->provider] ?? []) : [])
@php($agentModelValue = old('model',$agent->model ?: ''))
@php($customAgentModel = $agentModelValue !== '' && !in_array($agentModelValue,$knownAgentModels,true))
<div class="page-head"><div><h1>{{ $agent->exists ? __('ui.edit').' '.$agent->name : __('ui.new_agent') }}</h1><p>{{ __('ui.agent_form_desc') }}</p></div><a class="btn" href="{{ $agent->exists ? route('agents.show',$agent) : route('agents.index') }}">{{ __('ui.cancel') }}</a></div>
<form method="post" action="{{ $agent->exists ? route('agents.update',$agent) : route('agents.store') }}" class="grid grid-3" enctype="multipart/form-data" data-agent-model-catalog="{{ $agentModelCatalogJson }}">@csrf @if($agent->exists) @method('PUT') @endif
<section class="card card-pad span-2"><div class="form-grid">
<div class="form-group"><label class="form-label">{{ __('ui.name') }}</label><input class="field" name="name" value="{{ old('name',$agent->name) }}" required maxlength="120"></div><div class="form-group"><label class="form-label">Agent avatar</label><input class="field" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><div class="help">Optional JPG/PNG/WebP, max 2 MB. Used in chat and agent cards.</div>@if($agent->exists)<img class="agent-avatar-preview" src="{{ route('agents.avatar',$agent) }}" alt="{{ $agent->name }}">@endif</div>
<div class="form-group"><label class="form-label">{{ __('ui.status') }}</label><select class="select" name="status"><option value="active" @selected(old('status',$agent->status?:'active')==='active')>{{ __('ui.active') }}</option><option value="paused" @selected(old('status',$agent->status)==='paused')>{{ __('ui.paused') }}</option></select></div>
<div class="form-group full"><label class="form-label">{{ __('ui.description') }}</label><input class="field" name="description" value="{{ old('description',$agent->description) }}" maxlength="500"></div>
<div class="form-group full"><label class="form-label">{{ __('ui.instructions') }}</label><textarea class="textarea" name="instructions" style="min-height:260px" required>{{ old('instructions',$agent->instructions) }}</textarea><div class="help">{{ __('ui.agent_instruction_help') }}</div></div>
</div></section>
<aside class="card card-pad"><h3 class="section-title">{{ __('ui.model') }}</h3><div class="stack">
<div class="form-group"><label class="form-label">{{ __('ui.models') }}</label><select class="select" name="model_connection_id" data-agent-model-connection><option value="">—</option>@foreach($models as $m)<option value="{{ $m->id }}" data-provider="{{ $m->provider }}" @selected($selectedConnectionId===(string)$m->id)>{{ $m->name }} · {{ $m->provider }} · {{ $m->default_model ?: __('ui.provider_default') }}</option>@endforeach</select></div>
<div class="form-group"><label class="form-label">Default effort</label><select class="select" name="default_effort"><option value="fast" @selected(old('default_effort',$agent->default_effort?:'standard')==='fast')>Fast</option><option value="standard" @selected(old('default_effort',$agent->default_effort?:'standard')==='standard')>Standard</option><option value="deep" @selected(old('default_effort',$agent->default_effort?:'standard')==='deep')>Deep</option></select></div><div class="form-group"><label class="form-label">{{ __('ui.model_override') }}</label><select class="select mono" name="model" data-agent-model-select @disabled(!$selectedConnection)><option value="">Use connection default</option>@foreach($knownAgentModels as $id)<option value="{{ $id }}" @selected(!$customAgentModel && $agentModelValue===$id)>{{ $id }}</option>@endforeach<option value="__custom__" @selected($customAgentModel)>Custom model ID…</option></select><div class="help">Leave this on the connection default unless this agent needs a different model.</div></div>
<div class="form-group" data-agent-custom-model-wrap @if(!$customAgentModel) hidden @endif><label class="form-label">Custom model ID</label><input class="field mono" name="custom_model" data-agent-custom-model value="{{ old('custom_model',$customAgentModel?$agentModelValue:'') }}" placeholder="provider-model-id"></div></div>
<div class="divider"></div><h3 class="section-title">{{ __('ui.limits') }}</h3><div class="stack">
<div class="form-group"><label class="form-label">{{ __('ui.max_steps') }}</label><input class="field" type="number" min="1" max="200" name="max_steps" value="{{ old('max_steps',$agent->max_steps ?: 40) }}" required></div>
<div class="form-group"><label class="form-label">{{ __('ui.max_runtime') }}</label><input class="field" type="number" min="30" max="7200" name="max_runtime_seconds" value="{{ old('max_runtime_seconds',$agent->max_runtime_seconds ?: 900) }}" required></div>
<div class="form-group"><label class="form-label">{{ __('ui.max_cost') }}</label><input class="field" type="number" step="0.01" min="0" max="1000" name="max_cost_usd" value="{{ old('max_cost_usd',$agent->max_cost_usd ?: 0) }}" required></div></div></aside>
<section class="card card-pad span-2"><h3 class="section-title">{{ __('ui.skills_connectors') }}</h3><div class="form-grid">
<div class="form-group"><label class="form-label">{{ __('ui.skills') }}</label><div class="stack">@forelse($skills as $s)<label class="inline"><input type="checkbox" name="skills[]" value="{{ $s->id }}" @checked(in_array($s->id,old('skills',$agent->exists?$agent->skills()->pluck('skills.id')->all():[])))><span><strong>{{ $s->name }}</strong><span class="small muted"> · {{ $s->version }}</span></span></label>@empty<span class="muted">{{ __('ui.no_skills') }}</span>@endforelse</div></div>
<div class="form-group"><label class="form-label">{{ __('ui.connectors') }}</label><div class="stack">@forelse($connectors as $c)<label class="inline"><input type="checkbox" name="connectors[]" value="{{ $c->id }}" @checked(in_array($c->id,old('connectors',$agent->exists?$agent->connectors()->pluck('connector_connections.id')->all():[])))><span><strong>{{ $c->name }}</strong><span class="small muted"> · {{ $c->driver }}</span></span></label>@empty<span class="muted">{{ __('ui.no_connectors') }}</span>@endforelse</div></div>
</div></section>
<aside class="card card-pad"><h3 class="section-title">{{ __('ui.capability_policy') }}</h3>
<div class="switch-row"><div><div class="form-label">{{ __('ui.allow_external') }}</div><div class="help">{{ __('ui.research_external_help') }}</div></div><label class="switch"><input type="checkbox" name="allow_external_writes" value="1" @checked(old('allow_external_writes',data_get($agent->policy,'allow_external_writes',false)))><span></span></label></div>
<div class="switch-row"><div><div class="form-label">{{ __('ui.allow_destructive') }}</div><div class="help">{{ __('ui.destructive_help') }}</div></div><label class="switch"><input type="checkbox" name="allow_destructive" value="1" @checked(old('allow_destructive',data_get($agent->policy,'allow_destructive',false)))><span></span></label></div>
<div class="form-group"><label class="form-label">{{ __('ui.allow_tools') }}</label><textarea class="textarea mono" name="allow_tools" style="min-height:80px">{{ old('allow_tools',implode("\n",data_get($agent->policy,'allow',[]))) }}</textarea></div>
<div class="form-group"><label class="form-label">{{ __('ui.deny_tools') }}</label><textarea class="textarea mono" name="deny_tools" style="min-height:80px">{{ old('deny_tools',implode("\n",data_get($agent->policy,'deny',[]))) }}</textarea></div>
</aside>
@php
    $creative = (array) data_get($agent->settings, 'creative', []);
    $creativeEnabled = (bool) old('creative_enabled', data_get($creative, 'enabled', data_get($creative, 'image_generation', false)));
    $imageModelOptions = $imageModelOptions ?? [];
@endphp
<section class="card card-pad span-2" data-creative-panel>
    <div class="between" style="align-items:flex-start;gap:16px;margin-bottom:12px">
        <div>
            <h3 class="section-title" style="margin:0">Creative & social publishing</h3>
            <p class="muted" style="margin:6px 0 0;font-size:12px">Turn on for agents that draft/schedule social posts (Buffer) or reply in Slack. External sends still require approval unless autonomous writes are enabled.</p>
        </div>
        <label class="switch"><input type="checkbox" name="creative_enabled" value="1" data-creative-toggle @checked($creativeEnabled)><span></span></label>
    </div>
    <div class="form-grid" data-creative-fields @if(! $creativeEnabled) hidden @endif>
        <div class="form-group"><label class="form-label">Brand name</label><input class="field" name="creative_brand_name" value="{{ old('creative_brand_name', data_get($creative, 'brand_name')) }}" maxlength="120"></div>
        <div class="form-group"><label class="form-label">Brand logo URL</label><input class="field" name="creative_logo_url" value="{{ old('creative_logo_url', data_get($creative, 'logo_url')) }}" placeholder="https://…" maxlength="500"></div>
        <div class="form-group full"><label class="form-label">Brand voice</label><textarea class="textarea" name="creative_brand_voice" style="min-height:90px" maxlength="2000">{{ old('creative_brand_voice', data_get($creative, 'brand_voice')) }}</textarea></div>
        <div class="form-group full"><label class="form-label">Sample posts / style references</label><textarea class="textarea" name="creative_sample_posts" style="min-height:110px" maxlength="5000" placeholder="Paste 2–5 example posts the agent should emulate">{{ old('creative_sample_posts', data_get($creative, 'sample_posts')) }}</textarea></div>
        <div class="form-group"><label class="form-label">Default Buffer channel ID</label><input class="field mono" name="creative_buffer_channel_id" value="{{ old('creative_buffer_channel_id', data_get($creative, 'default_buffer_channel_id')) }}" maxlength="120"></div>
        <div class="form-group"><label class="form-label">Default Slack channel</label><input class="field mono" name="creative_slack_channel" value="{{ old('creative_slack_channel', data_get($creative, 'default_slack_channel')) }}" placeholder="C0123… or #marketing" maxlength="120"></div>
        <div class="form-group full" data-creative-image-block>
            <label class="form-label">Image generation model</label>
            @if(count($imageModelOptions))
                <select class="select" name="creative_image_model_key">
                    <option value="">No image model</option>
                    @foreach($imageModelOptions as $option)
                        @php($key = $option['connection_id'].'|'.$option['model'])
                        <option value="{{ $key }}" @selected(old('creative_image_model_key', data_get($creative, 'image_connection_id').'|'.data_get($creative, 'image_model')) === $key)>
                            {{ $option['connection'] }} · {{ $option['provider'] }} · {{ $option['model'] }}
                        </option>
                    @endforeach
                </select>
                <div class="help">Pulled from enabled OpenAI / Gemini connections that expose image models.</div>
            @else
                <div class="notice" style="margin:0">
                    Connect an <strong>OpenAI</strong> or <strong>Gemini</strong> model under <a href="{{ route('models.create') }}">AI Models</a> to choose image generation models here.
                </div>
            @endif
        </div>
    </div>
</section>
<div class="span-2"><button class="btn btn-primary" type="submit">{{ __('ui.save') }}</button></div>
</form>
<script>
(() => {
  const toggle = document.querySelector('[data-creative-toggle]');
  const fields = document.querySelector('[data-creative-fields]');
  if (!toggle || !fields) return;
  const sync = () => { fields.hidden = !toggle.checked; };
  toggle.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
