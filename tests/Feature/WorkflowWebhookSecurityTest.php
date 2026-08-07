<?php

namespace Tests\Feature;

use App\Models\{User,Workflow,Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Hash};
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkflowWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_webhook_reaches_controller_without_browser_csrf_and_replay_is_rejected(): void
    {
        [$workspace] = $this->installedWorkspace();
        app(WorkspaceContext::class)->set($workspace->id);
        $secret = Str::random(48);
        $workflow = Workflow::create([
            'name' => 'Signed webhook',
            'description' => null,
            'status' => 'active',
            'definition' => ['nodes'=>[['id'=>'trigger','type'=>'webhook','config'=>[]]],'edges'=>[]],
            'settings' => ['webhook_security'=>'signed','allow_external_writes'=>false,'allow_destructive'=>false],
            'webhook_secret' => $secret,
            'version' => 1,
        ]);
        app(WorkspaceContext::class)->clear();

        $body = json_encode(['lead'=>'Acme'], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $eventId = (string) Str::uuid();
        $signature = 'v1='.hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.$body, $secret);
        $headers = [
            'CONTENT_TYPE'=>'application/json',
            'HTTP_X_ENVERIF_TIMESTAMP'=>$timestamp,
            'HTTP_X_ENVERIF_EVENT_ID'=>$eventId,
            'HTTP_X_ENVERIF_SIGNATURE'=>$signature,
        ];

        $first = $this->call('POST', route('workflows.webhook',['workflow'=>$workflow->id,'secret'=>$secret]), [], [], [], $headers, $body);
        $first->assertStatus(202)->assertJson(['ok'=>true]);

        $replay = $this->call('POST', route('workflows.webhook',['workflow'=>$workflow->id,'secret'=>$secret]), [], [], [], $headers, $body);
        $replay->assertStatus(409);
    }

    public function test_signed_webhook_rejects_missing_signature_but_legacy_workflow_remains_compatible(): void
    {
        [$workspace] = $this->installedWorkspace();
        app(WorkspaceContext::class)->set($workspace->id);
        $signedSecret = Str::random(48);
        $signed = Workflow::create([
            'name'=>'Signed','status'=>'active','definition'=>['nodes'=>[['id'=>'t','type'=>'webhook','config'=>[]]],'edges'=>[]],
            'settings'=>['webhook_security'=>'signed'],'webhook_secret'=>$signedSecret,'version'=>1,
        ]);
        $legacySecret = Str::random(48);
        $legacy = Workflow::create([
            'name'=>'Legacy','status'=>'active','definition'=>['nodes'=>[['id'=>'t','type'=>'webhook','config'=>[]]],'edges'=>[]],
            'settings'=>[],'webhook_secret'=>$legacySecret,'version'=>1,
        ]);
        app(WorkspaceContext::class)->clear();

        $this->postJson(route('workflows.webhook',['workflow'=>$signed->id,'secret'=>$signedSecret]), ['ok'=>true])->assertStatus(401);
        $this->postJson(route('workflows.webhook',['workflow'=>$legacy->id,'secret'=>$legacySecret]), ['ok'=>true])->assertStatus(202)->assertHeader('X-Enverif-Webhook-Security','legacy');
    }

    /** @return array{Workspace,User} */
    private function installedWorkspace(): array
    {
        Cache::flush();
        $workspace = Workspace::create(['name'=>'Webhook','slug'=>'webhook','timezone'=>'UTC','locale'=>'en']);
        $user = User::create(['name'=>'Owner','email'=>'webhook-owner@example.test','password'=>Hash::make('password')]);
        $user->workspaces()->attach($workspace->id,['role'=>'owner']);
        return [$workspace,$user];
    }
}
