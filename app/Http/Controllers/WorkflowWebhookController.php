<?php

namespace App\Http\Controllers;

use App\Core\Runtime\WebQueueKick;
use App\Core\Workflows\{WorkflowEngine,WorkflowWebhookVerifier};
use App\Models\Workflow;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;

final class WorkflowWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $workflow,
        string $secret,
        WorkflowEngine $engine,
        WorkflowWebhookVerifier $verifier,
        WebQueueKick $queueKick,
        WorkspaceContext $workspace,
    ) {
        // Webhooks have no authenticated browser workspace. Resolve the owning workflow
        // explicitly, verify the request, then establish tenant context before runtime use.
        $target = Workflow::withoutGlobalScopes()->findOrFail((int) $workflow);
        $mode = $verifier->verify($request, $target, $secret);

        $run = $workspace->run((int) $target->workspace_id, function () use ($request, $target, $engine) {
            $payload = $request->all();
            if ($payload === []) $payload = ['raw' => $request->getContent()];
            return $engine->start($target, 'webhook', $payload);
        });
        $queueKick->afterResponse();

        return response()->json(['ok'=>true,'run_id'=>$run->id],202)
            ->header('X-Enverif-Webhook-Security', $mode);
    }
}
