@extends('layouts.app')
@section('title', ($connection->exists?__('ui.edit'):__('ui.connect')).' '.$driver->label())
@section('content')
<div class="page-head"><div><h1>{{ $connection->exists ? __('ui.edit').' '.$connection->name : __('ui.connect').' '.$driver->label() }}</h1><p>{{ __('ui.connector_form_desc') }}</p></div><a class="btn" href="{{ route('connectors.index') }}">{{ __('ui.cancel') }}</a></div>
<form method="post" action="{{ $connection->exists ? route('connectors.update',$connection) : route('connectors.store') }}" class="card card-pad">@csrf @if($connection->exists)@method('PUT')@endif<input type="hidden" name="driver" value="{{ $driver->id() }}"><div class="form-grid"><div class="form-group"><label class="form-label">{{ __('ui.name') }}</label><input class="field" name="name" value="{{ old('name',$connection->name ?: $driver->label()) }}" required></div><div class="form-group"><label class="form-label">{{ __('ui.driver') }}</label><input class="field" value="{{ $driver->id() }}" disabled></div>
@foreach(($driver->configurationSchema()['credentials'] ?? []) as $key=>$meta)<div class="form-group"><label class="form-label">{{ $meta['label'] ?? ucfirst(str_replace('_',' ',$key)) }}</label><input class="field" type="{{ ($meta['secret']??true)?'password':'text' }}" name="credentials[{{ $key }}]" autocomplete="off" @if(!$connection->exists && ($meta['required']??false)) required @endif><div class="help">{{ $connection->exists ? __('ui.credential_kept') : ($meta['help']??'') }}</div></div>@endforeach
@foreach(($driver->configurationSchema()['fields'] ?? []) as $key=>$meta)<div class="form-group"><label class="form-label">{{ $meta['label'] ?? ucfirst(str_replace('_',' ',$key)) }}</label>@if(($meta['type']??'text')==='select')<select class="select" name="configuration[{{ $key }}]">@foreach(($meta['options']??[]) as $option)<option value="{{ $option }}" @selected(old('configuration.'.$key,data_get($connection->configuration,$key,$meta['default']??''))===$option)>{{ ucfirst($option) }}</option>@endforeach</select>@else<input class="field" type="{{ $meta['type']??'text' }}" name="configuration[{{ $key }}]" value="{{ old('configuration.'.$key,data_get($connection->configuration,$key,$meta['default']??'')) }}" @if($meta['required']??false) required @endif>@endif<div class="help">{{ $meta['help']??'' }}</div></div>@endforeach
<div class="form-group full"><div class="switch-row"><div><div class="form-label">{{ __('ui.enabled') }}</div></div><label class="switch"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$connection->exists?$connection->enabled:true))><span></span></label></div></div></div><div class="between connector-form-actions form-actions">
<button class="btn btn-primary">{{ __('ui.save') }}</button>
@if($connection->exists && in_array($connection->driver,['gmail','outlook'],true))
<div class="inline actions-inline">
<a class="btn" href="{{ route('connectors.oauth.start',$connection) }}">Connect mailbox</a>
<button class="btn btn-danger" type="submit" form="oauth-disconnect-{{ $connection->id }}">Disconnect</button>
</div>
@endif
</div></form>@if($connection->exists && in_array($connection->driver,['gmail','outlook'],true))<form id="oauth-disconnect-{{ $connection->id }}" method="post" action="{{ route('connectors.oauth.disconnect',$connection) }}">@csrf</form>@endif
@endsection
