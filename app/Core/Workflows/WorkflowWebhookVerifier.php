<?php

namespace App\Core\Workflows;

use App\Models\Workflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class WorkflowWebhookVerifier
{
    public function verify(Request $request, Workflow $workflow, string $urlSecret): string
    {
        $secret = (string) $workflow->webhook_secret;
        if ($secret === '' || !hash_equals($secret, $urlSecret)) abort(404);
        if ($workflow->status !== 'active') abort(404);

        $mode = (string) data_get($workflow->settings, 'webhook_security', 'legacy');
        if ($mode !== 'signed') return 'legacy';

        $timestamp = trim((string) $request->header('X-Enverif-Timestamp', ''));
        $eventId = trim((string) $request->header('X-Enverif-Event-Id', ''));
        $provided = trim((string) $request->header('X-Enverif-Signature', ''));
        if ($timestamp === '' || $eventId === '' || $provided === '') abort(401, 'Signed webhook headers are required.');
        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) abort(401, 'Webhook timestamp is outside the allowed window.');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,191}$/', $eventId)) abort(401, 'Invalid webhook event ID.');

        $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.$request->getContent(), $secret);
        if (!hash_equals($expected, $provided)) abort(401, 'Invalid webhook signature.');

        $replayKey = 'enverif:webhook:'.(int) $workflow->workspace_id.':'.$workflow->id.':'.hash('sha256', $eventId);
        if (!Cache::add($replayKey, true, now()->addMinutes(10))) abort(409, 'Webhook event was already processed.');

        return 'signed';
    }
}
