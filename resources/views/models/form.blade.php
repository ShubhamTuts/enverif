@extends('layouts.app')
@section('title', ($connection->exists?__('ui.edit'):__('ui.connect')).' '.ucfirst($provider->id()))
@section('content')
@php
    $modelCatalogJson = json_encode($catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
@endphp
@php($selectedProvider = old('provider',$connection->provider ?: $provider->id()))
@php($selectedModelValue = old('default_model',$connection->default_model ?: ($catalog[$selectedProvider][0] ?? '')))
@php($knownSelectedModels = $catalog[$selectedProvider] ?? [])
@php($customSelectedModel = $selectedModelValue !== '' && !in_array($selectedModelValue,$knownSelectedModels,true))
<div class="page-head"><div><h1>{{ $connection->exists ? __('ui.edit').' '.$connection->name : __('ui.connect').' AI model' }}</h1><p>{{ __('ui.model_form_desc') }}</p></div><a class="btn" href="{{ route('models.index') }}">{{ __('ui.cancel') }}</a></div>
<form method="post" action="{{ $connection->exists ? route('models.update',$connection) : route('models.store') }}" class="card card-pad" data-model-catalog="{{ $modelCatalogJson }}">@csrf @if($connection->exists)@method('PUT')@endif
<div class="form-grid">
<div class="form-group"><label class="form-label">{{ __('ui.name') }}</label><input class="field" name="name" value="{{ old('name',$connection->name ?: ucfirst($selectedProvider ?: 'OpenAI')) }}" required></div>
<div class="form-group"><label class="form-label">{{ __('ui.provider') }}</label><select class="select" name="provider" data-model-provider required>@foreach($catalog as $providerId=>$models)<option value="{{ $providerId }}" @selected($selectedProvider===$providerId)>{{ $providerId==='anthropic'?'Anthropic Claude':($providerId==='gemini'?'Google Gemini':ucfirst($providerId)) }}</option>@endforeach</select></div>
<div class="form-group"><label class="form-label">{{ __('ui.api_key') }}</label><input class="field" type="password" name="api_key" autocomplete="off" @if(!$connection->exists)required @endif><div class="help">{{ $connection->exists ? __('ui.credential_kept_or_reenter') : '' }}</div></div>
<div class="form-group"><label class="form-label">{{ __('ui.model') }}</label><select class="select mono" name="default_model" data-model-select>@foreach($knownSelectedModels as $id)<option value="{{ $id }}" @selected(!$customSelectedModel && $selectedModelValue===$id)>{{ $id }}</option>@endforeach<option value="__custom__" @selected($customSelectedModel)>Custom model ID…</option></select></div>
<div class="form-group full" data-model-custom-wrap @if(!$customSelectedModel) hidden @endif><label class="form-label">Custom model ID</label><input class="field mono" name="custom_model" data-model-custom value="{{ old('custom_model',$customSelectedModel?$selectedModelValue:'') }}" placeholder="provider-model-id"></div>
<div class="form-group full"><label class="form-label">{{ __('ui.base_url') }}</label><input class="field mono" type="url" name="base_url" value="{{ old('base_url',$connection->base_url) }}" placeholder="{{ __('ui.provider_default') }}"><div class="help">{{ __('ui.base_url_help') }}</div></div>
<div class="form-group"><label class="form-label">{{ __('ui.input_price') }}</label><input class="field" type="number" step="0.0001" min="0" name="input_price_per_million" value="{{ old('input_price_per_million',data_get($connection->pricing,'input_per_million',0)) }}"><div class="help">{{ __('ui.price_help') }}</div></div>
<div class="form-group"><label class="form-label">{{ __('ui.output_price') }}</label><input class="field" type="number" step="0.0001" min="0" name="output_price_per_million" value="{{ old('output_price_per_million',data_get($connection->pricing,'output_per_million',0)) }}"></div>
<div class="form-group full"><div class="switch-row"><div><div class="form-label">{{ __('ui.enabled') }}</div></div><label class="switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$connection->exists?$connection->enabled:true))><span></span></label></div></div>
</div><div class="models-form-actions form-actions"><button class="btn btn-primary">{{ __('ui.save') }}</button>@if($connection->exists)<a class="btn" href="{{ route('models.index') }}">{{ __('ui.cancel') }}</a>@endif</div></form>
@endsection
