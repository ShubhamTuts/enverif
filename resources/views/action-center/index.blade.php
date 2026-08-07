@extends('layouts.app')
@section('title', 'Action Center')
@section('content')
<div class="page-head"><div><h1>Action Center</h1><p>Review actions that need a human decision before Enverif continues.</p></div><span class="badge badge-warning">{{ $pendingCount }} pending</span></div>
<div class="card">
    <div class="toolbar"><form method="get"><select class="select field-sm" name="status" data-auto-submit>@foreach(['pending','approved','denied'] as $s)<option value="{{ $s }}" @selected($status===$s)>{{ ucfirst($s) }}</option>@endforeach</select></form></div>
    @if($approvals->isEmpty())
        <x-empty title="No {{ $status }} actions" />
    @else
        <div class="table-wrap"><table class="table"><thead><tr><th>Created</th><th>Action</th><th>Risk</th><th>Summary</th><th>Status</th><th>Decision</th></tr></thead><tbody>
        @foreach($approvals as $a)
            <tr>
                <td class="small muted nowrap">{{ $a->created_at?->format('M j H:i') }}</td>
                <td class="mono small">{{ $a->action }}</td>
                <td><span class="risk-{{ $a->risk_level }}">{{ $a->risk_level }}</span></td>
                <td style="max-width:460px"><div class="row-title">{{ $a->summary }}</div>@if($a->run_id)<a class="small muted" href="{{ route('runs.show',$a->run_id) }}">Open originating run</a>@endif</td>
                <td><x-badge :status="$a->status" /></td>
                <td>
                    @if($a->status==='pending')
                        <form class="inline" method="post" action="{{ route('approvals.decide',$a) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-primary">Approve</button></form>
                        <form class="inline" method="post" action="{{ route('approvals.decide',$a) }}">@csrf<input type="hidden" name="decision" value="denied"><button class="btn btn-sm btn-danger">Deny</button></form>
                    @else
                        <span class="small muted">{{ $a->decided_at?->diffForHumans() }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody></table></div>
        <x-pagination :paginator="$approvals" />
    @endif
</div>
@endsection
