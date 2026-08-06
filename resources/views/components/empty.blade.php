@props(['title'=>'Nothing here yet','text'=>'','action'=>null,'actionLabel'=>null,'href'=>null])
@php $target=$href ?: ((is_string($action) && str_starts_with($action,'http')) ? $action : null); $label=$actionLabel ?: (($target && $action && $action!==$target) ? $action : null); @endphp
<div class="empty"><div class="empty-icon">◇</div><h3>{{ $title }}</h3>@if($text)<p>{{ $text }}</p>@endif @if($target)<a class="btn btn-primary" href="{{ $target }}">{{ $label ?: __('ui.create') }}</a>@endif</div>
