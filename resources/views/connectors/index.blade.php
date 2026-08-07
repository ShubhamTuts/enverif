@extends('layouts.app')
@section('title', __('ui.plugins'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ __('ui.plugins') }}</h1>
        <p>{{ __('ui.connectors_desc') }}</p>
    </div>
</div>

<div class="grid grid-3 integration-grid" style="margin-bottom:16px">
    @foreach($catalog as $id => $item)
        <article class="card integration-card">
            <div class="integration-icon integration-logo">
                <img src="{{ $item['icon'] }}" alt="{{ $item['label'] }} icon" loading="lazy">
            </div>
            <div class="integration-copy">
                <div class="small muted">{{ $item['category'] }}</div>
                <h3><a href="{{ route('connectors.create', ['driver' => $id]) }}">{{ $item['label'] }}</a></h3>
                <p>{{ count($item['actions']) }} agent actions · {{ collect($item['actions'])->pluck('risk')->unique()->implode(', ') }}</p>
                @php($semanticCapabilities=collect($item['actions'])->flatMap(fn($action)=>$action['capabilities']??[])->unique()->values())
                @if($semanticCapabilities->isNotEmpty())<div class="small muted" title="Discovered capabilities">{{ $semanticCapabilities->take(4)->implode(' · ') }}@if($semanticCapabilities->count()>4) · +{{ $semanticCapabilities->count()-4 }}@endif</div>@endif
                <div class="small muted integration-developer">
                    by
                    @if($item['developer_url'])
                        <a class="developer-link" href="{{ $item['developer_url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['developer'] }}</a>
                    @else
                        {{ $item['developer'] }}
                    @endif
                    @if($item['version']) · v{{ $item['version'] }} @endif
                </div>
                <div class="inline" style="margin-top:8px">
                    <a class="integration-connect-link" href="{{ route('connectors.create', ['driver' => $id]) }}">+ {{ __('ui.new_connection') }}</a>
                    @if(!empty($item['removable']))
                        <form method="post" action="{{ route('plugins.destroy', $item['slug']) }}" data-destructive-form data-dependencies-url="{{ route('plugins.dependencies',$item['slug']) }}" data-confirm-title="Uninstall {{ $item['label'] }}?" data-confirm-message="This removes the external plugin package. Historical run and audit data will be preserved.">
                            @csrf @method('delete')
                            <button class="btn btn-sm btn-danger" type="submit">Uninstall</button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @endforeach
</div>

<section class="card">
    <div class="card-head"><h2>{{ __('ui.connected') }}</h2></div>
    @if($connections->isEmpty())
        <x-empty title="{{ __('ui.no_connections') }}" />
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.driver') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.last_tested') }}</th><th>{{ __('ui.actions') }}</th></tr></thead>
                <tbody>
                    @foreach($connections as $connection)
                        <tr>
                            <td class="row-title">{{ $connection->name }}</td>
                            <td><span class="integration-mini"><img src="{{ $catalog[$connection->driver]['icon'] ?? asset('assets/enverif-mark.svg') }}" alt="">{{ $catalog[$connection->driver]['label'] ?? $connection->driver }}</span></td>
                            <td><x-badge :status="$connection->enabled ? ($connection->last_test_status ?: 'active') : ($connection->last_test_status==='disconnected'?'disconnected':'paused')" /></td>
                            <td class="small muted">{{ $connection->last_tested_at?->diffForHumans() ?: '—' }}</td>
                            <td>
                                <div class="inline">
                                    <form method="post" action="{{ route('connectors.test', $connection) }}">@csrf<button class="btn btn-sm">{{ __('ui.test') }}</button></form>
                                    <a class="btn btn-sm" href="{{ route('connectors.edit', $connection) }}">{{ __('ui.edit') }}</a>
                                    <form method="post" action="{{ route('connectors.toggle',$connection) }}">@csrf<button class="btn btn-sm" type="submit">{{ $connection->enabled?'Disable':'Enable' }}</button></form>
                                    <form method="post" action="{{ route('connectors.disconnect',$connection) }}" data-destructive-form data-confirm-title="Disconnect {{ $connection->name }}?" data-confirm-message="Stored credentials will be removed. Configuration and historical activity will remain.">@csrf<button class="btn btn-sm" type="submit">Disconnect</button></form>
                                    <form method="post" action="{{ route('connectors.destroy',$connection) }}" data-destructive-form data-confirm-title="Delete {{ $connection->name }}?" data-confirm-message="Deletion is blocked while agents or workflows still depend on this connection.">@csrf @method('delete')<button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$connections" />
    @endif
</section>
@endsection
