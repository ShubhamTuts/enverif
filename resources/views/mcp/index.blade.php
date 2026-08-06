@extends('layouts.app')
@section('title', __('ui.mcp'))
@section('content')
<div class="page-head"><div><h1>{{ __('ui.mcp') }}</h1><p>{{ __('ui.mcp_desc') }}</p></div><a class="btn btn-primary" href="{{ route('mcp.create') }}">+ {{ __('ui.mcp_server') }}</a></div>
<div class="notice"><div><strong>{{ __('ui.protocol_compat') }}</strong><div class="small muted">{{ __('ui.protocol_help') }}</div></div></div>
<div class="card">@if($servers->isEmpty())<x-empty title="No {{ __('ui.mcp_server') }}s connected." />@else<div class="table-wrap"><table class="table"><thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.endpoint') }}</th><th>{{ __('ui.protocol') }}</th><th>{{ __('ui.status') }}</th><th>{{ __('ui.actions') }}</th></tr></thead><tbody>@foreach($servers as $s)<tr><td class="row-title">{{ $s->name }}</td><td class="mono small truncate" style="max-width:360px">{{ $s->endpoint }}</td><td class="mono small">{{ data_get($s->configuration,'protocol_version','2025-11-25') }}</td><td><x-badge :status="$s->enabled?'active':'paused'" /></td><td><div class="inline"><form method="post" action="{{ route('mcp.test',$s) }}">@csrf<button class="btn btn-sm">{{ __('ui.test') }}</button></form><a class="btn btn-sm" href="{{ route('mcp.edit',$s) }}">{{ __('ui.edit') }}</a></div></td></tr>@endforeach</tbody></table></div><x-pagination :paginator="$servers" />@endif</div>
@endsection
