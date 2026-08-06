<?php

namespace App\Http\Controllers;

use App\Core\Workflows\WorkflowEngine;
use App\Core\Runtime\WebQueueKick;
use App\Jobs\ContinueWorkflowRunJob;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;

final class WorkflowRunController extends Controller
{
    public function show(WorkflowRun $workflowRun){return view('workflow-runs.show',['run'=>$workflowRun->load(['workflow','steps'])]);}
    public function status(WorkflowRun $workflowRun){$workflowRun->refresh();return response()->json(['id'=>$workflowRun->id,'status'=>$workflowRun->status,'mode'=>$workflowRun->mode,'error'=>$workflowRun->error,'output'=>$workflowRun->output,'steps'=>$workflowRun->steps()->get(['id','node_id','node_type','status','output','error'])]);}
    public function cancel(WorkflowRun $workflowRun){if(!in_array($workflowRun->status,['completed','failed','cancelled'],true))$workflowRun->update(['status'=>'cancelled','cancelled_at'=>now(),'finished_at'=>now()]);return back()->with('status','Workflow run cancelled.');}
    public function retry(WorkflowRun $workflowRun,WorkflowEngine $engine,WebQueueKick $queueKick){$new=$engine->start($workflowRun->workflow,$workflowRun->trigger,(array)$workflowRun->input,'execute',$workflowRun->id);$queueKick->afterResponse();return redirect()->route('workflow-runs.show',$new)->with('status','Workflow retry started as a new immutable run.');}
    public function resume(WorkflowRun $workflowRun,WebQueueKick $queueKick){abort_if(in_array($workflowRun->status,['completed','failed','cancelled'],true),409,'This workflow run is terminal. Use Retry to create a new immutable run.');ContinueWorkflowRunJob::dispatch($workflowRun->id);$queueKick->afterResponse();return back()->with('status','Workflow run was re-queued.');}
}
