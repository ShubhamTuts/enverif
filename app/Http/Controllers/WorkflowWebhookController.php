<?php

namespace App\Http\Controllers;

use App\Core\Workflows\WorkflowEngine;
use App\Core\Runtime\WebQueueKick;
use App\Models\Workflow;
use Illuminate\Http\Request;

final class WorkflowWebhookController extends Controller
{
    public function __invoke(Request $request,Workflow $workflow,string $secret,WorkflowEngine $engine,WebQueueKick $queueKick)
    {
        $workflow=Workflow::withoutGlobalScopes()->findOrFail($workflow->id);abort_unless($workflow->status==='active'&&$workflow->webhook_secret&&hash_equals((string)$workflow->webhook_secret,$secret),404);
        $payload=$request->all();if($payload===[])$payload=['raw'=>$request->getContent()];$run=$engine->start($workflow,'webhook',$payload);$queueKick->afterResponse();return response()->json(['ok'=>true,'run_id'=>$run->id],202);
    }
}
