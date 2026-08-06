@extends('layouts.app')
@section('title', __('ui.models'))
@section('content')
<div class="page-head"><div><h1>{{ __('ui.models') }}</h1><p>{{ __('ui.models_desc') }}</p></div></div>
@php
$providerIcons = [
    'openai' => ['openai.webp', 'openai.svg'],
    'anthropic' => ['anthropic.png', 'anthropic.svg'],
    'gemini' => ['gemini.png', 'gemini.svg'],
    'deepseek' => ['deepseek.png', 'deepseek.svg'],
];
$providerNames = [
    'openai' => 'OpenAI',
    'anthropic' => 'Claude',
    'gemini' => 'Gemini',
    'deepseek' => 'DeepSeek',
];
$resolveIcon = static function (array $candidates): string {
    foreach ($candidates as $file) {
        if (is_file(public_path('assets/integrations/'.$file))) {
            return asset('assets/integrations/'.$file);
        }
    }
    return asset('assets/enverif-mark.svg');
};
@endphp
<div class="grid grid-4 integration-grid" style="margin-bottom:16px">
@foreach($catalog as $provider=>$modelList)
<a class="card integration-card" href="{{ route('models.create',['provider'=>$provider]) }}">
    <div class="integration-icon integration-logo provider-icon-wrap">
        @if(isset($providerIcons[$provider]))
            <img src="{{ $resolveIcon($providerIcons[$provider]) }}" alt="{{ $providerNames[$provider] ?? ucfirst($provider) }}">
        @else
            <span class="provider-fallback">{{ strtoupper(substr($provider,0,2)) }}</span>
        @endif
    </div>
    <div class="integration-copy">
        <h3>{{ $providerNames[$provider] ?? ucfirst($provider) }}</h3>
        <p>{{ count($modelList) }} suggested models · custom IDs supported.</p>
        <div class="integration-connect-link">+ {{ __('ui.new_connection') }}</div>
    </div>
</a>
@endforeach
</div>
<section class="card"><div class="card-head"><h2>{{ __('ui.connections') }}</h2></div>@if($connections->isEmpty())<x-empty title="{{ __('ui.no_models') }}" />@else<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.provider') }}</th><th>{{ __('ui.model') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.actions') }}</th></tr></thead><tbody>@foreach($connections as $m)<tr><td class="row-title">{{ $m->name }}</td><td>{{ ucfirst($m->provider) }}</td><td class="mono small">{{ $m->default_model }}</td><td><x-badge :status="$m->enabled?($m->last_test_status?:'active'):'paused'" /></td><td><div class="inline actions-inline"><form method="post" action="{{ route('models.test',$m) }}">@csrf<button class="btn btn-sm">{{ __('ui.test') }}</button></form><a class="btn btn-sm" href="{{ route('models.edit',$m) }}">{{ __('ui.edit') }}</a></div></td></tr>@endforeach</tbody></table></div><x-pagination :paginator="$connections" />@endif</section>
@endsection
