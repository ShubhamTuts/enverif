@props(['value'=>null,'status'=>null])
@php $display=$status ?? $value ?? 'unknown';$v=strtolower((string)$display);$cls=in_array($v,['active','completed','approved','ok','qualified','won','replied','meeting'])?'good':(in_array($v,['failed','denied','cancelled','lost','disqualified','destructive','quarantined'])?'bad':(in_array($v,['pending','queued','running','awaiting_approval','waiting_child','paused','external_write','secrets'])?'warn':'info')); @endphp
<span class="badge {{ $cls }}">{{ str_replace('_',' ',$display) }}</span>
