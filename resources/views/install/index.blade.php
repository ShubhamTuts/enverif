@extends('layouts.guest')
@section('title', __('ui.install'))
@section('content')
@php
    $modelCatalog = $modelCatalog ?? [];
    $installModelCatalogJson = $installModelCatalogJson ?? (json_encode(
        $modelCatalog,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
    ) ?: '{}');
@endphp
<div class="installer installer-wizard" data-installer>
    <div class="installer-head">
        <div class="auth-brand" style="margin:0">
            <img src="{{ asset('assets/enverif-mark.svg') }}" alt="Enverif">
            <div><strong>Enverif</strong><div class="small muted">{{ __('ui.tagline') }}</div></div>
        </div>
        <button class="icon-btn" data-theme-toggle type="button" aria-label="Toggle appearance">◐</button>
    </div>

    <div class="installer-hero">
        <span class="eyebrow">Production setup</span>
        <h1>Install Enverif on almost any PHP host.</h1>
        <p>Redis and SSH are optional. The installer detects your server, selects a safe runtime, configures queues and gives you the exact scheduler command for your hosting.</p>
    </div>

    @if($errors->any())
        <div class="notice bad"><ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(in_array($installationStatus ?? 'fresh', ['incomplete','stale_marker'], true))
        <div class="notice warn"><strong>Installer recovery mode.</strong> Enverif detected an incomplete or stale previous installation. You can safely rerun this wizard; completed migrations are reused and owner/workspace setup is resumed transactionally.</div>
    @endif

    <div class="installer-progress" aria-label="Installation progress">
        @foreach(['Server','Database','Runtime','Owner','AI','Review'] as $i => $label)
            <button type="button" class="installer-progress-step {{ $i===0?'active':'' }}" data-install-jump="{{ $i }}"><span>{{ $i+1 }}</span>{{ $label }}</button>
        @endforeach
    </div>

    <form method="post" action="{{ route('install.store') }}" data-install-form>
        @csrf
        <section class="installer-step active" data-install-step="0">
            <div class="card card-pad">
                <div class="section-heading"><div><span class="eyebrow">Step 1</span><h2>Server check</h2><p>Required checks must pass. Optional capabilities simply change the recommended runtime.</p></div></div>
                <div class="req-grid">
                    @foreach($requirements as $req)
                        <div class="req {{ $req['ok']?'ok':(($req['required']??true)?'fail':'optional') }}">
                            <strong>{{ $req['ok']?'✓':(($req['required']??true)?'×':'•') }} {{ $req['label'] }}</strong>
                            <span class="truncate">{{ $req['value'] }}</span>
                            @unless($req['required']??true)<small>Optional</small>@endunless
                        </div>
                    @endforeach
                </div>
                <div class="form-grid installer-app-fields">
                    <div class="form-group full"><label class="form-label">Application URL</label><input class="field" name="app_url" value="{{ old('app_url',$suggestedAppUrl) }}" required><p class="help">Subfolders are supported, for example https://example.com/tools/enverif</p></div>
                    <div class="form-group"><label class="form-label">Timezone</label><input class="field" name="timezone" value="{{ old('timezone','UTC') }}" required></div>
                    <div class="form-group"><label class="form-label">Language</label><select class="select" name="locale"><option value="en">English</option><option value="fr">Français</option><option value="nl">Nederlands</option></select></div>
                </div>
            </div>
        </section>

        <section class="installer-step" data-install-step="1">
            <div class="card card-pad"><span class="eyebrow">Step 2</span><h2>MySQL database</h2><p class="muted">Enverif validates connectivity and CREATE/ALTER privileges before changing anything.</p>
                <div class="form-grid"><div class="form-group"><label class="form-label">Host</label><input class="field" name="db_host" value="{{ old('db_host','localhost') }}" required></div><div class="form-group"><label class="form-label">Port</label><input class="field" name="db_port" value="{{ old('db_port','3306') }}" required></div><div class="form-group"><label class="form-label">Database</label><input class="field" name="db_database" value="{{ old('db_database','enverif') }}" required></div><div class="form-group"><label class="form-label">Username</label><input class="field" name="db_username" value="{{ old('db_username') }}" required></div><div class="form-group full"><label class="form-label">Password</label><input class="field" type="password" name="db_password" autocomplete="new-password"></div></div>
            </div>
        </section>

        <section class="installer-step" data-install-step="2">
            <div class="card card-pad"><span class="eyebrow">Step 3</span><h2>Runtime</h2><p class="muted">Recommended: <strong>{{ ucfirst($detectedProfile) }}</strong>. You can change this without losing product features.</p>
                <div class="runtime-options">
                    @foreach([
                        'performance'=>['Performance','Redis queue + cache · persistent workers','Best for Docker, VPS and high-volume teams'],
                        'shared'=>['Shared Hosting','MySQL queue + cache · cron tick','Hostinger, cPanel, Plesk and no-SSH hosting'],
                        'compatibility'=>['Compatibility','MySQL queue + signed Web Cron','For hosts without CLI cron access'],
                    ] as $value=>$meta)
                    <label class="runtime-option"><input type="radio" name="runtime_mode" value="{{ $value }}" {{ old('runtime_mode',$detectedProfile)===$value?'checked':'' }}><span><strong>{{ $meta[0] }}</strong><small>{{ $meta[1] }}</small><em>{{ $meta[2] }}</em></span></label>
                    @endforeach
                </div>
                <div class="form-grid" data-redis-fields><div class="form-group"><label class="form-label">Redis host</label><input class="field" name="redis_host" value="{{ old('redis_host','redis') }}"></div><div class="form-group"><label class="form-label">Redis port</label><input class="field" name="redis_port" value="{{ old('redis_port','6379') }}"></div></div>
                <div class="form-group" style="max-width:280px"><label class="form-label">Cron execution budget</label><div class="input-suffix"><input class="field" type="number" min="10" max="240" name="tick_budget" value="{{ old('tick_budget',45) }}"><span>seconds</span></div><p class="help">Keep this below your host's PHP execution limit.</p></div>
            </div>
        </section>

        <section class="installer-step" data-install-step="3">
            <div class="card card-pad"><span class="eyebrow">Step 4</span><h2>Owner workspace</h2>
                <div class="form-grid"><div class="form-group full"><label class="form-label">Workspace</label><input class="field" name="workspace_name" value="{{ old('workspace_name','My workspace') }}" required></div><div class="form-group"><label class="form-label">Your name</label><input class="field" name="admin_name" value="{{ old('admin_name') }}" required></div><div class="form-group"><label class="form-label">Email</label><input class="field" type="email" name="admin_email" value="{{ old('admin_email') }}" required></div><div class="form-group"><label class="form-label">Password</label><input class="field" type="password" name="admin_password" minlength="10" required></div><div class="form-group"><label class="form-label">Confirm password</label><input class="field" type="password" name="admin_password_confirmation" minlength="10" required></div></div>
            </div>
        </section>

        @php($selectedProvider = old('provider',''))
        @php($selectedInstallModel = old('default_model',''))
        <section class="installer-step" data-install-step="4" data-install-model-catalog="{{ $installModelCatalogJson }}">
            <div class="card card-pad"><span class="eyebrow">Step 5</span><h2>First AI model <small class="muted">optional</small></h2><p class="muted">Choose a provider and model from the supported catalog. Custom model IDs remain available for compatible provider endpoints.</p>
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Provider</label><select class="select" name="provider" data-install-provider><option value="">Skip for now</option><option value="openai" @selected($selectedProvider==='openai')>OpenAI</option><option value="anthropic" @selected($selectedProvider==='anthropic')>Anthropic Claude</option><option value="gemini" @selected($selectedProvider==='gemini')>Google Gemini</option><option value="deepseek" @selected($selectedProvider==='deepseek')>DeepSeek</option></select></div>
                    <div class="form-group"><label class="form-label">Default model</label><select class="select" name="default_model" data-install-model @disabled($selectedProvider==='')><option value="">Use provider default</option>@foreach(($modelCatalog[$selectedProvider] ?? []) as $modelId)<option value="{{ $modelId }}" @selected($selectedInstallModel===$modelId)>{{ $modelId }}</option>@endforeach<option value="__custom__" @selected($selectedInstallModel==='__custom__')>Custom model ID…</option></select></div>
                    <div class="form-group full" data-install-custom-model-wrap @if($selectedInstallModel!=='__custom__') hidden @endif><label class="form-label">Custom model ID</label><input class="field mono" name="custom_model" data-install-custom-model value="{{ old('custom_model') }}" placeholder="provider-model-id"></div>
                    <div class="form-group full"><label class="form-label">API key</label><input class="field" type="password" name="api_key" autocomplete="off"><p class="help">Credentials are encrypted after installation. Sending email remains approval-first by default.</p></div>
                </div>
            </div>
        </section>

        <section class="installer-step" data-install-step="5">
            <div class="card card-pad installer-review"><span class="eyebrow">Step 6</span><h2>Ready to install</h2><div class="review-grid"><div><strong>Routing</strong><span>Root, subdomain and nested subfolder safe</span></div><div><strong>Queue</strong><span>Durable MySQL or Redis execution</span></div><div><strong>Scheduler</strong><span>Daemon, control-panel cron or signed Web Cron</span></div><div><strong>Security</strong><span>Production debug off · encrypted credentials · protected internals</span></div></div><p class="help">After installation, Settings → System Health shows the exact cron command and verifies its heartbeat.</p></div>
        </section>

        <div class="installer-actions"><button class="btn" type="button" data-install-back hidden>← Back</button><div class="grow"></div><button class="btn btn-primary" type="button" data-install-next>Continue →</button><button class="btn btn-primary" type="submit" data-install-submit hidden>Install Enverif →</button></div>
    </form>
</div>
@endsection
