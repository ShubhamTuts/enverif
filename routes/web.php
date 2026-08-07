<?php

use App\Http\Controllers\{ActionCenterController,AgentController,AgentMemoryController,ApprovalController,AuditController,AuthController,CampaignController,ChatActivityController,ChatAttachmentController,ChatController,ChatStatusController,ChatStopController,ConnectorController,ConnectorOAuthController,DashboardController,InstallController,LeadController,McpServerController,ModelConnectionController,PluginAssetController,PluginController,RunController,RuntimeFeedController,ScheduleController,SettingsController,SkillController,SystemCronController,WorkflowController,WorkflowRunController,WorkflowWebhookController};
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::middleware('installed')->group(function () {
    Route::get('/plugin-assets/{plugin}/{file}', PluginAssetController::class)->where(['plugin'=>'[a-z0-9-]+','file'=>'[A-Za-z0-9._-]+'])->name('plugins.asset');
    Route::post('/hooks/workflows/{workflow}/{secret}', WorkflowWebhookController::class)->middleware('throttle:60,1')->name('workflows.webhook');
    Route::get('/system/cron', SystemCronController::class)->middleware('throttle:12,1')->name('system.cron');
    Route::get('/system/web-cron/{token}', SystemCronController::class)->where('token', '[a-f0-9]{64}')->middleware('throttle:6,1')->name('system.web-cron');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');

    Route::middleware(['auth', 'workspace'])->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/overview', DashboardController::class)->name('dashboard');
        Route::get('/chats/{thread}', [ChatController::class, 'show'])->name('chat.show');
        Route::post('/chats/{thread?}', [ChatController::class, 'send'])->name('chat.send');
        Route::get('/chats/{thread}/status', ChatStatusController::class)->name('chat.status');
        Route::get('/chats/{thread}/activity', ChatActivityController::class)->name('chat.activity');
        Route::get('/runtime/feed', RuntimeFeedController::class)->name('runtime.feed');
        Route::post('/chats/{thread}/stop', ChatStopController::class)->name('chat.stop');
        Route::put('/chats/{thread}/rename', [ChatController::class, 'rename'])->name('chat.rename');
        Route::post('/chats/{thread}/archive', [ChatController::class, 'archive'])->name('chat.archive');
        Route::get('/chat-attachments/{attachment}', [ChatAttachmentController::class, 'show'])->name('chat.attachments.show');
        Route::delete('/chats/{thread}', [ChatController::class, 'destroy'])->name('chat.destroy');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
        Route::post('/workspace/switch', [SettingsController::class, 'switchWorkspace'])->name('workspace.switch');
        Route::post('/locale', [SettingsController::class, 'locale'])->name('locale.update');

        Route::get('/agents/{agent}/avatar', [AgentController::class, 'avatar'])->name('agents.avatar');
        Route::post('/agents/{agent}/run', [AgentController::class, 'run'])->name('agents.run');
        Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
        Route::get('/runs/{run}/status', [RunController::class, 'status'])->name('runs.status');
        Route::post('/runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
        Route::post('/runs/{run}/retry', [RunController::class, 'retry'])->name('runs.retry');

        Route::middleware('workspace.capability:manage-agents')->group(function () {
            Route::resource('agents', AgentController::class)->except(['show']);
            Route::get('/agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
            Route::post('/agents/{agent}/memories', [AgentMemoryController::class, 'store'])->name('agents.memories.store');
            Route::delete('/agents/{agent}/memories/{memory}', [AgentMemoryController::class, 'destroy'])->name('agents.memories.destroy');
        });

        Route::get('/leads/export.csv', [LeadController::class, 'export'])->name('leads.export');
        Route::resource('leads', LeadController::class);

        Route::middleware('workspace.capability:manage-automation')->group(function () {
            Route::resource('schedules', ScheduleController::class);
            Route::post('/schedules/{schedule}/toggle', [ScheduleController::class, 'toggle'])->name('schedules.toggle');

            Route::resource('campaigns', CampaignController::class);
            Route::post('/campaigns/{campaign}/steps', [CampaignController::class, 'addStep'])->name('campaigns.steps.store');
            Route::delete('/campaigns/{campaign}/steps/{step}', [CampaignController::class, 'deleteStep'])->name('campaigns.steps.destroy');
            Route::post('/campaigns/{campaign}/leads', [CampaignController::class, 'addLeads'])->name('campaigns.leads.store');
            Route::delete('/campaigns/{campaign}/leads/{lead}', [CampaignController::class, 'removeLead'])->name('campaigns.leads.destroy');

            Route::resource('workflows', WorkflowController::class);
            Route::post('/workflows/{workflow}/run', [WorkflowController::class, 'run'])->name('workflows.run');
            Route::post('/workflows/{workflow}/test', [WorkflowController::class, 'test'])->name('workflows.test');
            Route::post('/workflows/{workflow}/webhook/regenerate', [WorkflowController::class, 'regenerateWebhook'])->name('workflows.webhook.regenerate');
            Route::get('/workflow-runs/{workflowRun}', [WorkflowRunController::class, 'show'])->name('workflow-runs.show');
            Route::get('/workflow-runs/{workflowRun}/status', [WorkflowRunController::class, 'status'])->name('workflow-runs.status');
            Route::post('/workflow-runs/{workflowRun}/cancel', [WorkflowRunController::class, 'cancel'])->name('workflow-runs.cancel');
            Route::post('/workflow-runs/{workflowRun}/retry', [WorkflowRunController::class, 'retry'])->name('workflow-runs.retry');
            Route::post('/workflow-runs/{workflowRun}/resume', [WorkflowRunController::class, 'resume'])->name('workflow-runs.resume');
        });

        Route::middleware('workspace.capability:manage-integrations')->group(function () {
            Route::post('/skills/install', [SkillController::class, 'install'])->name('skills.install');
            Route::resource('skills', SkillController::class)->except('show');
            Route::post('/skills/{skill}/toggle', [SkillController::class, 'toggle'])->name('skills.toggle');

            Route::resource('connectors', ConnectorController::class)->except('show');
            Route::post('/connectors/{connector}/test', [ConnectorController::class, 'test'])->name('connectors.test');
            Route::post('/connectors/{connector}/toggle', [ConnectorController::class, 'toggle'])->name('connectors.toggle');
            Route::post('/connectors/{connector}/disconnect', [ConnectorController::class, 'disconnect'])->name('connectors.disconnect');
            Route::get('/connectors/{connector}/oauth/start', [ConnectorOAuthController::class, 'start'])->name('connectors.oauth.start');
            Route::post('/connectors/{connector}/oauth/disconnect', [ConnectorOAuthController::class, 'disconnect'])->name('connectors.oauth.disconnect');
            Route::get('/connectors/oauth/{driver}/callback', [ConnectorOAuthController::class, 'callback'])->name('connectors.oauth.callback');
            Route::get('/plugins/{plugin}/dependencies', [PluginController::class, 'dependencies'])->where('plugin','[a-z0-9-]+')->name('plugins.dependencies');
            Route::delete('/plugins/{plugin}', [PluginController::class, 'destroy'])->where('plugin','[a-z0-9-]+')->name('plugins.destroy');

            Route::resource('models', ModelConnectionController::class)->except('show')->parameters(['models'=>'model']);
            Route::post('/models/{model}/test', [ModelConnectionController::class, 'test'])->name('models.test');

            Route::resource('mcp', McpServerController::class)->except('show')->parameters(['mcp'=>'mcp']);
            Route::post('/mcp/{mcp}/test', [McpServerController::class, 'test'])->name('mcp.test');
        });

        Route::get('/approvals', [ApprovalController::class, 'index'])->middleware('workspace.capability:decide-approvals')->name('approvals.index');
        Route::get('/action-center', ActionCenterController::class)->middleware('workspace.capability:decide-approvals')->name('action-center.index');
        Route::post('/approvals/{approval}', [ApprovalController::class, 'decide'])->middleware('workspace.capability:decide-approvals')->name('approvals.decide');

        Route::middleware('workspace.capability:view-audit')->group(function () {
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
            Route::get('/audit/export.json', [AuditController::class, 'export'])->name('audit.export');
        });

        Route::get('/settings', [SettingsController::class, 'edit'])->middleware('workspace.capability:manage-workspace')->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->middleware('workspace.capability:manage-workspace')->name('settings.update');
        Route::put('/settings/password', [SettingsController::class, 'password'])->name('settings.password');
    });
});