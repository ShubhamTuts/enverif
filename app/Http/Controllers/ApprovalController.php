<?php

namespace App\Http\Controllers;

use App\Core\Runtime\WebQueueKick;
use App\Jobs\{ContinueAgentRunJob,ContinueWorkflowRunJob};
use App\Models\Approval;
use Illuminate\Http\Request;

final class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $query=Approval::latest();if($request->filled('status'))$query->where('status',$request->input('status'));
        return view('approvals.index',['approvals'=>$query->paginate(25)->withQueryString()]);
    }
    public function decide(Request $request,Approval $approval,WebQueueKick $queueKick)
    {
        abort_unless($approval->status==='pending',409,'This approval has already been decided.');
        $data=$request->validate(['decision'=>'required|in:approved,denied','note'=>'nullable|string|max:1000']);
        $approval->update(['status'=>$data['decision'],'decision_note'=>$data['note']??null,'decided_by'=>$request->user()->id,'decided_at'=>now()]);
        if($approval->run_id)ContinueAgentRunJob::dispatch($approval->run_id);
        if($approval->workflow_run_id)ContinueWorkflowRunJob::dispatch($approval->workflow_run_id);
        $queueKick->afterResponse();
        return back()->with('status',__('ui.saved'));
    }
}
