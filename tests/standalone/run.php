<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'app/Core/Agents/Contracts/RiskLevel.php',
    'app/Core/Agents/Contracts/CapabilityDecision.php',
    'app/Core/Agents/Security/CapabilityPolicy.php',
    'app/Core/Skills/SkillFrontmatter.php',
    'app/Core/Skills/SourceProvenanceValidator.php',
    'app/Core/Scheduling/CronExpressionLite.php',
    'app/Core/Agents/RunBounds.php',
    'app/Core/Plugins/PluginManifestValidator.php',
    'app/Core/Connectors/ConnectorConfigurationValidator.php',
    'app/Core/Skills/SkillSourceResolver.php',
    'app/Core/Agents/Memory/MemoryInput.php',
];
foreach ([
    'app/Core/Runtime/RuntimeProfileDetector.php',
    'app/Core/Runtime/InstallerBootstrapPolicy.php',
    'app/Core/Runtime/InstallationStatePolicy.php',
    'app/Core/Runtime/WebCronSignature.php',
    'app/Core/Email/MailActionPolicy.php',
    'app/Core/Workflows/WorkflowDefinitionValidator.php',
    'app/Core/Workflows/WorkflowValueResolver.php',
    'app/Core/Chat/ChatHistoryBuilder.php',
    'app/Core/Chat/ChatSelection.php',
    'app/Core/Scheduling/ScheduleTarget.php',
] as $optionalFile) {
    if (file_exists($root . '/' . $optionalFile)) require_once $root . '/' . $optionalFile;
}
foreach ($files as $file) {
    require_once $root . '/' . $file;
}

use App\Core\Agents\Contracts\CapabilityDecision;
use App\Core\Agents\Contracts\RiskLevel;
use App\Core\Agents\RunBounds;
use App\Core\Agents\Security\CapabilityPolicy;
use App\Core\Scheduling\CronExpressionLite;
use App\Core\Skills\SkillFrontmatter;
use App\Core\Skills\SourceProvenanceValidator;
use App\Core\Plugins\PluginManifestValidator;
use App\Core\Connectors\ConnectorConfigurationValidator;
use App\Core\Skills\SkillSourceResolver;
use App\Core\Agents\Memory\MemoryInput;

$tests = [];
$tests['read is allowed by default'] = function (): void {
    $policy = new CapabilityPolicy();
    assert($policy->decide(RiskLevel::Read, []) === CapabilityDecision::Allow);
};
$tests['external write asks by default'] = function (): void {
    $policy = new CapabilityPolicy();
    assert($policy->decide(RiskLevel::ExternalWrite, []) === CapabilityDecision::Ask);
};
$tests['destructive is denied by default'] = function (): void {
    $policy = new CapabilityPolicy();
    assert($policy->decide(RiskLevel::Destructive, []) === CapabilityDecision::Deny);
};
$tests['explicit deny outranks allow'] = function (): void {
    $policy = new CapabilityPolicy(allow: ['apollo.people.search', 'webhook.send'], deny: ['webhook.*']);
    assert($policy->decide(RiskLevel::ExternalWrite, ['tool' => 'webhook.send']) === CapabilityDecision::Deny);
    assert($policy->decide(RiskLevel::Read, ['tool' => 'apollo.people.search']) === CapabilityDecision::Allow);
};
$tests['skill parser rejects missing name'] = function (): void {
    $thrown = false;
    try { SkillFrontmatter::parse("---\ndescription: x\n---\nDo work"); } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown === true);
};
$tests['skill parser extracts capabilities'] = function (): void {
    $parsed = SkillFrontmatter::parse("---\nname: prospect-research\ndescription: Research a company\nversion: 1.0.0\ncapabilities: [network, read]\n---\nResearch with citations.");
    assert($parsed['name'] === 'prospect-research');
    assert($parsed['capabilities'] === ['network', 'read']);
    assert($parsed['body'] === 'Research with citations.');
};
$tests['provenance allows https github and rejects http'] = function (): void {
    assert(SourceProvenanceValidator::validate('https://github.com/acme/skill') === true);
    assert(SourceProvenanceValidator::validate('http://github.com/acme/skill') === false);
    assert(SourceProvenanceValidator::validate('https://evil.example/acme/skill') === false);
};
$tests['cron parser accepts standard five-field expressions'] = function (): void {
    $cron = CronExpressionLite::fromString('15 9 * * 1-5');
    assert($cron->expression() === '15 9 * * 1-5');
};
$tests['cron parser rejects six fields'] = function (): void {
    $thrown = false;
    try { CronExpressionLite::fromString('0 15 9 * * 1-5'); } catch (InvalidArgumentException) { $thrown = true; }
    assert($thrown === true);
};
$tests['run bounds stop at configured limits'] = function (): void {
    $bounds = new RunBounds(maxSteps: 12, maxRuntimeSeconds: 300, maxEstimatedCostUsd: 5.0);
    assert($bounds->shouldStop(12, 10, 1.0) === 'max_steps');
    assert($bounds->shouldStop(2, 301, 1.0) === 'max_runtime');
    assert($bounds->shouldStop(2, 10, 5.01) === 'max_cost');
    assert($bounds->shouldStop(2, 10, 1.0) === null);
};


$tests['destructive explicit allow still requires approval'] = function (): void {
    $policy = new CapabilityPolicy(allow: ['connector.*'], allowDestructive: true);
    assert($policy->decide(RiskLevel::Destructive, ['tool' => 'connector.delete_everything']) === CapabilityDecision::Ask);
};
$tests['secrets explicit allow still requires approval'] = function (): void {
    $policy = new CapabilityPolicy(allow: ['secrets.*']);
    assert($policy->decide(RiskLevel::Secrets, ['tool' => 'secrets.read']) === CapabilityDecision::Ask);
};
$tests['zero cost bound means unlimited cost'] = function (): void {
    $bounds = new RunBounds(maxSteps: 12, maxRuntimeSeconds: 300, maxEstimatedCostUsd: 0.0);
    assert($bounds->shouldStop(2, 10, 999.0) === null);
};
$tests['plugin manifest validator accepts connector contract'] = function (): void {
    $manifest = PluginManifestValidator::validate([
        'schema' => 'enverif.plugin/v1',
        'name' => 'Example',
        'slug' => 'example',
        'version' => '1.0.0',
        'type' => 'connector',
        'driver' => 'Vendor\\Example\\Driver',
        'capabilities' => ['read', 'network'],
        'license' => 'MIT',
    ]);
    assert($manifest['slug'] === 'example');
};
$tests['connector configuration validator enforces required fields'] = function (): void {
    $schema = [
        'credentials' => ['api_key' => ['required' => true]],
        'fields' => ['url' => ['required' => true]],
    ];
    assert(ConnectorConfigurationValidator::missing($schema, [], []) === ['credentials.api_key', 'configuration.url']);
    assert(ConnectorConfigurationValidator::missing($schema, ['api_key' => 'secret'], ['url' => 'https://example.com']) === []);
};
$tests['connector configuration validator preserves existing required secrets'] = function (): void {
    $schema = ['credentials' => ['api_key' => ['required' => true]], 'fields' => []];
    assert(ConnectorConfigurationValidator::missing($schema, [], [], ['api_key' => 'kept']) === []);
};

$tests['skill source resolver supports trusted git hosts'] = function (): void {
    assert(SkillSourceResolver::downloadUrl('https://github.com/acme/sales-skill', 'main') === 'https://codeload.github.com/acme/sales-skill/zip/main');
    assert(SkillSourceResolver::downloadUrl('https://gitlab.com/acme/sales-skill', 'v1.0.0') === 'https://gitlab.com/acme/sales-skill/-/archive/v1.0.0/sales-skill-v1.0.0.zip');
    assert(SkillSourceResolver::downloadUrl('https://codeberg.org/acme/sales-skill', 'main') === 'https://codeberg.org/acme/sales-skill/archive/main.zip');
};
$tests['skill source resolver rejects subdirectory masquerading as repository'] = function (): void {
    $thrown=false;
    try { SkillSourceResolver::downloadUrl('https://github.com/acme/repo/tree/main/skills/x', 'main'); } catch (InvalidArgumentException) { $thrown=true; }
    assert($thrown);
};

$tests['memory input normalizes durable records'] = function (): void {
    $memory = MemoryInput::normalize('  ICP notes  ', '  Dental groups convert best.  ', ['sales', 'sales', '  icp '], 90);
    assert($memory['key'] === 'ICP notes');
    assert($memory['value'] === 'Dental groups convert best.');
    assert($memory['tags'] === ['sales', 'icp']);
    assert($memory['importance'] === 90);
};
$tests['memory input rejects empty keys and invalid importance'] = function (): void {
    $empty=false; try { MemoryInput::normalize(' ', 'x', [], 50); } catch (InvalidArgumentException) { $empty=true; }
    $range=false; try { MemoryInput::normalize('x', 'y', [], 101); } catch (InvalidArgumentException) { $range=true; }
    assert($empty && $range);
};

$tests['memory input flags likely secrets'] = function (): void {
    assert(MemoryInput::containsLikelySecret('api_key = sk-abcdefghijklmnopqrstuvwxyz1234') === true);
    assert(MemoryInput::containsLikelySecret('ICP prefers 10–50 employee dental groups') === false);
};

$tests['capability decision strictest prevents delegated privilege escalation'] = function (): void {
    assert(CapabilityDecision::strictest(CapabilityDecision::Allow, CapabilityDecision::Ask) === CapabilityDecision::Ask);
    assert(CapabilityDecision::strictest(CapabilityDecision::Allow, CapabilityDecision::Deny) === CapabilityDecision::Deny);
    assert(CapabilityDecision::strictest(CapabilityDecision::Ask, CapabilityDecision::Deny) === CapabilityDecision::Deny);
};

$tests['plugin manifest rejects unknown capabilities'] = function (): void {
    $thrown=false;
    try { PluginManifestValidator::validate(['schema'=>'enverif.plugin/v1','name'=>'Bad','slug'=>'bad','version'=>'1.0.0','type'=>'connector','driver'=>'Bad\\Driver','capabilities'=>['root_everything'],'license'=>'MIT']); } catch (InvalidArgumentException) { $thrown=true; }
    assert($thrown);
};





$tests['workflow value resolver expands node and input references'] = function (): void {
    assert(class_exists(\App\Core\Workflows\WorkflowValueResolver::class));
    $context=['input'=>['company'=>'Acme'],'nodes'=>['research'=>['email'=>'owner@acme.test']]];
    assert(\App\Core\Workflows\WorkflowValueResolver::resolve('Email {{nodes.research.email}} at {{input.company}}',$context)==='Email owner@acme.test at Acme');
    assert(\App\Core\Workflows\WorkflowValueResolver::resolve('{{nodes.research.email}}',$context)==='owner@acme.test');
};

$tests['workflow definitions require a trigger and valid edges'] = function (): void {
    assert(class_exists(\App\Core\Workflows\WorkflowDefinitionValidator::class));
    $valid=['nodes'=>[['id'=>'trigger','type'=>'manual','config'=>[]],['id'=>'agent','type'=>'agent','config'=>['agent_id'=>1]]],'edges'=>[['from'=>'trigger','to'=>'agent']]];
    assert(\App\Core\Workflows\WorkflowDefinitionValidator::validate($valid)['nodes'][0]['id']==='trigger');
    $bad=false; try { \App\Core\Workflows\WorkflowDefinitionValidator::validate(['nodes'=>[['id'=>'a','type'=>'agent','config'=>['agent_id'=>1]]],'edges'=>[]]); } catch (InvalidArgumentException) { $bad=true; }
    assert($bad);
};
$tests['workflow definitions reject cycles and dangling edges'] = function (): void {
    assert(class_exists(\App\Core\Workflows\WorkflowDefinitionValidator::class));
    $cycle=false; try { \App\Core\Workflows\WorkflowDefinitionValidator::validate(['nodes'=>[['id'=>'t','type'=>'manual','config'=>[]],['id'=>'a','type'=>'output','config'=>[]]],'edges'=>[['from'=>'t','to'=>'a'],['from'=>'a','to'=>'t']]]); } catch (InvalidArgumentException) { $cycle=true; }
    $dangling=false; try { \App\Core\Workflows\WorkflowDefinitionValidator::validate(['nodes'=>[['id'=>'t','type'=>'manual','config'=>[]]],'edges'=>[['from'=>'t','to'=>'missing']]]); } catch (InvalidArgumentException) { $dangling=true; }
    assert($cycle && $dangling);
};

$tests['mail send and reply are always external writes'] = function (): void {
    assert(class_exists(\App\Core\Email\MailActionPolicy::class));
    assert(\App\Core\Email\MailActionPolicy::risk('send') === RiskLevel::ExternalWrite);
    assert(\App\Core\Email\MailActionPolicy::risk('reply') === RiskLevel::ExternalWrite);
    assert(\App\Core\Email\MailActionPolicy::risk('draft') === RiskLevel::InternalWrite);
    assert(\App\Core\Email\MailActionPolicy::risk('search') === RiskLevel::Read);
};
$tests['mail action policy rejects undeclared actions'] = function (): void {
    assert(class_exists(\App\Core\Email\MailActionPolicy::class));
    $thrown=false; try { \App\Core\Email\MailActionPolicy::risk('execute_anything'); } catch (InvalidArgumentException) { $thrown=true; }
    assert($thrown);
};


$tests['fresh install bootstrap avoids database-backed framework services'] = function (): void {
    assert(class_exists(\App\Core\Runtime\InstallerBootstrapPolicy::class));
    $profile = \App\Core\Runtime\InstallerBootstrapPolicy::frameworkStores(false);
    assert($profile === ['session' => 'file', 'cache' => 'file', 'queue' => 'sync']);
    assert(\App\Core\Runtime\InstallerBootstrapPolicy::frameworkStores(true) === null);
};

$tests['fresh install bootstrap key is stable and reusable'] = function (): void {
    assert(class_exists(\App\Core\Runtime\InstallerBootstrapPolicy::class));
    $dir = sys_get_temp_dir().'/enverif-installer-bootstrap-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    $path = $dir.'/bootstrap.key';
    $first = \App\Core\Runtime\InstallerBootstrapPolicy::bootstrapKey($path, null);
    $second = \App\Core\Runtime\InstallerBootstrapPolicy::bootstrapKey($path, null);
    assert(str_starts_with($first, 'base64:'));
    assert($first === $second);
    assert(strlen(base64_decode(substr($first, 7), true)) === 32);
    unlink($path);
    rmdir($dir);
};

$tests['existing application key wins over installer bootstrap key'] = function (): void {
    assert(class_exists(\App\Core\Runtime\InstallerBootstrapPolicy::class));
    $dir = sys_get_temp_dir().'/enverif-installer-bootstrap-existing-'.bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    $path = $dir.'/bootstrap.key';
    $existing = 'base64:'.base64_encode(str_repeat('x', 32));
    assert(\App\Core\Runtime\InstallerBootstrapPolicy::bootstrapKey($path, $existing) === $existing);
    assert(!file_exists($path));
    rmdir($dir);
};

$tests['runtime profile prefers Redis performance workers'] = function (): void {
    assert(class_exists(\App\Core\Runtime\RuntimeProfileDetector::class));
    assert(\App\Core\Runtime\RuntimeProfileDetector::select(true, true, true) === 'performance');
};
$tests['runtime profile falls back to database cron without Redis'] = function (): void {
    assert(class_exists(\App\Core\Runtime\RuntimeProfileDetector::class));
    assert(\App\Core\Runtime\RuntimeProfileDetector::select(false, true, false) === 'shared');
    assert(\App\Core\Runtime\RuntimeProfileDetector::select(false, false, false) === 'compatibility');
};
$tests['web cron signatures reject stale and tampered requests'] = function (): void {
    assert(class_exists(\App\Core\Runtime\WebCronSignature::class));
    $secret = 'test-secret-with-enough-entropy-1234567890';
    $ts = 1722860000;
    $nonce = 'nonce-1234567890';
    $sig = \App\Core\Runtime\WebCronSignature::sign($secret, $ts, $nonce);
    assert(\App\Core\Runtime\WebCronSignature::verify($secret, $ts, $nonce, $sig, $ts + 30, 120) === true);
    assert(\App\Core\Runtime\WebCronSignature::verify($secret, $ts, $nonce, $sig . 'x', $ts + 30, 120) === false);
    assert(\App\Core\Runtime\WebCronSignature::verify($secret, $ts, $nonce, $sig, $ts + 500, 120) === false);
};


$tests['web cron stable tokens are derived and constant-time verifiable'] = function (): void {
    assert(class_exists(\App\Core\Runtime\WebCronSignature::class));
    $secret = 'another-long-web-cron-secret-1234567890';
    $token = \App\Core\Runtime\WebCronSignature::stableToken($secret);
    assert(strlen($token) === 64);
    assert(\App\Core\Runtime\WebCronSignature::verifyStable($secret, $token) === true);
    assert(\App\Core\Runtime\WebCronSignature::verifyStable($secret, $token . 'x') === false);
    assert(\App\Core\Runtime\WebCronSignature::verifyStable('', $token) === false);
};



$tests['chat history builder keeps bounded alternating context'] = function (): void {
    assert(class_exists(\App\Core\Chat\ChatHistoryBuilder::class));
    $history=\App\Core\Chat\ChatHistoryBuilder::fromTranscript([
        ['role'=>'user','content'=>'first'],
        ['role'=>'assistant','content'=>'one'],
        ['role'=>'system','content'=>'ignore me'],
        ['role'=>'user','content'=>'second'],
    ],3);
    assert($history === [
        ['role'=>'user','content'=>'first'],
        ['role'=>'assistant','content'=>'one'],
        ['role'=>'user','content'=>'second'],
    ]);
};
$tests['chat context selection normalizes tagged capabilities'] = function (): void {
    assert(class_exists(\App\Core\Chat\ChatSelection::class));
    $selection=\App\Core\Chat\ChatSelection::normalize([3,3,'4','bad'],[8,'8'],[5],25);
    assert($selection['connector_ids'] === [3,4]);
    assert($selection['skill_ids'] === [8]);
    assert($selection['workflow_ids'] === [5]);
    assert($selection['agent_id'] === 25);
};


$tests['authenticated login lands in the agentic chat workspace'] = function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AuthController.php');
    assert(str_contains($source, "route('chat.index')"));
    assert(!str_contains($source, "route('dashboard')"));
};

$tests['schedule targets exactly one agent or workflow'] = function (): void {
    assert(class_exists(\App\Core\Scheduling\ScheduleTarget::class));
    assert(\App\Core\Scheduling\ScheduleTarget::type(10, null) === 'agent');
    assert(\App\Core\Scheduling\ScheduleTarget::type(null, 8) === 'workflow');
    $both=false; try { \App\Core\Scheduling\ScheduleTarget::type(1, 2); } catch (InvalidArgumentException) { $both=true; }
    $none=false; try { \App\Core\Scheduling\ScheduleTarget::type(null, null); } catch (InvalidArgumentException) { $none=true; }
    assert($both && $none);
};



$tests['installation state requires schema and owner membership, not a stale marker'] = function (): void {
    assert(class_exists(\App\Core\Runtime\InstallationStatePolicy::class));
    assert(\App\Core\Runtime\InstallationStatePolicy::classify(false, false, false) === 'fresh');
    assert(\App\Core\Runtime\InstallationStatePolicy::classify(true, false, false) === 'stale_marker');
    assert(\App\Core\Runtime\InstallationStatePolicy::classify(false, true, false) === 'incomplete');
    assert(\App\Core\Runtime\InstallationStatePolicy::classify(true, true, true) === 'installed');
    assert(\App\Core\Runtime\InstallationStatePolicy::classify(false, true, true) === 'installed');
};

$tests['workspace user relationships explicitly use migrated pivot table'] = function (): void {
    $user = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/User.php');
    $workspace = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/Workspace.php');
    assert(str_contains($user, "belongsToMany(Workspace::class,'workspace_user')"));
    assert(str_contains($workspace, "belongsToMany(User::class,'workspace_user')"));
};

$tests['installer model choice is a provider driven select instead of a free text field'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/install/index.blade.php');
    assert(str_contains($view, 'data-install-provider'));
    assert(str_contains($view, 'data-install-model'));
    assert(str_contains($view, '<select class="select" name="default_model"'));
    assert(!str_contains($view, '<input class="field" name="default_model"'));
};

$tests['model connection form uses an explicit model dropdown with custom option'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/models/form.blade.php');
    assert(str_contains($view, '<select class="select mono" name="default_model"'));
    assert(str_contains($view, 'value="__custom__"'));
};



$tests['agent editor uses connection-aware model override dropdown'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/agents/form.blade.php');
    assert(str_contains($view, 'data-agent-model-connection'));
    assert(str_contains($view, '<select class="select mono" name="model"'));
    assert(str_contains($view, 'value="__custom__"'));
};


$tests['workspace scope does not collide with Laravel UUID route binding'] = function (): void {
    $concern = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/Concerns/BelongsToWorkspace.php');
    $agentRun = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/AgentRun.php');
    $workflowRun = (string) file_get_contents(dirname(__DIR__, 2).'/app/Models/WorkflowRun.php');
    assert(!str_contains($concern, 'function resolveRouteBindingQuery'));
    assert(str_contains($agentRun, 'use HasUuids, BelongsToWorkspace;'));
    assert(str_contains($workflowRun, 'use HasUuids, BelongsToWorkspace;'));
};

$tests['version 1.2 migration adds persistent chat execution fields'] = function (): void {
    $migration = (string) file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_06_000900_upgrade_chat_agents_for_1_2.php');
    foreach (['default_model_connection_id', 'default_model', 'default_effort', 'chat_attachments', 'avatar_path', "'mode'", 'retry_of'] as $needle) {
        assert(str_contains($migration, $needle));
    }
};

$tests['shared hosting root route resolves directly to Laravel front controller'] = function (): void {
    $rootHtaccess = (string) file_get_contents(dirname(__DIR__, 2).'/.htaccess');
    $publicHtaccess = (string) file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');
    assert(str_contains($rootHtaccess, 'RewriteRule ^$ public/index.php [L]'));
    assert(str_contains($publicHtaccess, 'DirectoryIndex index.php'));
};

$tests['installer writes release version dynamically instead of stale literal'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InstallController.php');
    assert(str_contains($controller, "base_path('VERSION')"));
    assert(!str_contains($controller, "'version' => '1.1.2'"));
};

$tests['chat composer exposes persistent defaults one shot overrides mentions and uploads'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/chat/index.blade.php');
    foreach (['persist_defaults', 'attachment', 'data-context-menu', 'default_effort', 'model_connection_id'] as $needle) {
        assert(str_contains($view, $needle));
    }
    assert(str_contains($view, '<input type="hidden" name="persist_defaults" value="0">'));
    assert(str_contains($view, '<input type="checkbox" name="persist_defaults" value="1" checked>'));
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ChatController.php');
    foreach (['persist_defaults', 'ChatAttachment', "'kind' => 'final'", 'mentionSnapshots', 'resolveModelSelection'] as $needle) {
        assert(str_contains($controller, $needle));
    }
};

$tests['agent editor supports private avatar and default effort'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AgentController.php');
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/agents/form.blade.php');
    assert(str_contains($controller, "'avatar'"));
    assert(str_contains($controller, "Storage::disk('local')"));
    assert(str_contains($view, 'enctype="multipart/form-data"'));
    assert(str_contains($view, 'name="default_effort"'));
};

$tests['workflow UI and runtime expose dry runs retry and resume'] = function (): void {
    $engine = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Workflows/WorkflowEngine.php');
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/WorkflowRunController.php');
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    assert(str_contains($engine, "'dry_run'"));
    assert(str_contains($controller, 'function retry'));
    assert(str_contains($controller, 'function resume'));
    assert(str_contains($routes, "workflows/{workflow}/test"));
};

$tests['external plugin local icons are discoverable before connector drivers are instantiated'] = function (): void {
    $registry = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Plugins/PluginRegistry.php');
    assert(str_contains($registry, 'ensureManifestMetadata'));
    $assetStart = strpos($registry, 'public function assetPath');
    assert($assetStart !== false);
    $assetBody = substr($registry, $assetStart, 500);
    assert(str_contains($assetBody, '$this->ensureManifestMetadata()'));
};

$tests['first party plugin manifests expose Codefreex identity and icon metadata'] = function (): void {
    foreach (glob(dirname(__DIR__, 2).'/plugins/builtin/*/enverif.json') ?: [] as $path) {
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        assert(($manifest['developer'] ?? '') === 'Codefreex');
        assert(($manifest['developer_url'] ?? '') === 'https://codefreex.com/');
        assert(trim((string) ($manifest['icon'] ?? '')) !== '');
    }
};

$tests['release source reports semantic version from VERSION file'] = function (): void {
    $version = trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));
    assert(preg_match('/^\d+\.\d+\.\d+$/', $version) === 1);
    assert($version === '1.3.5');
};


$tests['workflow runs snapshot definition and settings for durable execution'] = function (): void {
    $engine = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Workflows/WorkflowEngine.php');
    assert(str_contains($engine, "'workflow_definition'"));
    assert(str_contains($engine, "'workflow_settings'"));
    assert(str_contains($engine, 'definitionForRun'));
};

$tests['failed workflow runs require retry instead of a no-op resume'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/WorkflowRunController.php');
    assert(str_contains($controller, "['completed','failed','cancelled']"));
};

$tests['deleting an agent also removes its private avatar'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AgentController.php');
    $destroyStart = strpos($controller, 'public function destroy');
    assert($destroyStart !== false);
    $destroyBody = substr($controller, $destroyStart, 500);
    assert(str_contains($destroyBody, "Storage::disk('local')->delete"));
};


$tests['agent runs snapshot mutable agent configuration at start'] = function (): void {
    $orchestrator = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Agents/AgentOrchestrator.php');
    $prompt = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Agents/SystemPromptBuilder.php');
    $tools = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Agents/Tools/ToolRegistry.php');
    assert(str_contains($orchestrator, "'agent_snapshot'"));
    assert(str_contains($orchestrator, 'data_get($run->context, \'agent_snapshot.policy\''));
    assert(str_contains($prompt, "agent_snapshot.instructions"));
    assert(str_contains($tools, "agent_snapshot.connectors"));
};

$tests['desktop agentic shell does not double offset fixed sidebar'] = function (): void {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    assert(str_contains($css, '.agentic-main{margin-left:0'));
    assert(!str_contains($css, '.agentic-main{margin-left:260px'));
};

$tests['application assets are cache busted by release version'] = function (): void {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');
    assert(str_contains($layout, 'assetVersion'));
    assert(str_contains($layout, "asset('assets/app.css') }}?v="));
    assert(str_contains($layout, "asset('assets/app.js') }}?v="));
};

$tests['brand header is Enverif only'] = function (): void {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');
    assert(!str_contains($layout, '<small>by Codefreex</small>'));
};

$tests['workflow builder serializes resources without inline Blade array directive'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/workflows/form.blade.php');
    assert(!str_contains($view, '@json(['));
    assert(str_contains($view, '$workflowResourcesJson'));
    assert(str_contains($view, 'data-resources="{{ $workflowResourcesJson }}"'));
};

$tests['chat submission stays in place and upgrades the new thread URL without a send-page navigation'] = function (): void {
    $root = dirname(__DIR__, 2);
    $js = (string) file_get_contents($root.'/resources/js/app.js');
    $controller = (string) file_get_contents($root.'/app/Http/Controllers/ChatController.php');
    $routes = (string) file_get_contents($root.'/routes/web.php');
    assert(str_contains($js, "event.preventDefault()"));
    assert(str_contains($js, "Accept: 'application/json'"));
    assert(str_contains($js, 'history.replaceState'));
    assert(!str_contains($js, 'window.location.assign(data.redirect_url)'));
    assert(str_contains($controller, "'thread_url'"));
    assert(str_contains($controller, "'send_url'"));
    assert(str_contains($controller, "'status_url'"));
    assert(str_contains($controller, "'transcript_html'"));
    assert(str_contains($controller, 'expectsJson()'));
    assert(!str_contains($routes, '/chats/send/'));
};

$tests['built in connector catalog uses local versioned icon assets'] = function (): void {
    $presentation = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Plugins/PluginPresentation.php');
    assert(!str_contains($presentation, 'google.com/s2/favicons'));
    assert(str_contains($presentation, "assets/integrations/"));
    foreach (['gmail','outlook','smtp','apollo','apify','google-search-console','google-analytics','google-maps','calendly','automation-webhook'] as $slug) {
        assert(is_file(dirname(__DIR__, 2).'/public/assets/integrations/'.$slug.'.svg'));
    }
};

$tests['plugin developer attribution is a real hyperlink'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/connectors/index.blade.php');
    assert(str_contains($view, 'class="developer-link" href="{{ $item[\'developer_url\'] }}"'));
    assert(!str_contains($view, 'data-href='));
};


$tests['all Blade templates avoid the brittle inline json directive'] = function (): void {
    $root = dirname(__DIR__, 2).'/resources/views';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
        $text = (string) file_get_contents($file->getPathname());
        assert(!str_contains($text, '@json('));
    }
};

$tests['chat agent selection carries model connection model and effort defaults'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/chat/index.blade.php');
    $js = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');
    foreach (['data-model-connection', 'data-model=', 'data-effort'] as $needle) assert(str_contains($view, $needle));
    foreach (['preferredConnection', 'preferredModel', 'preferredEffort'] as $needle) assert(str_contains($js, $needle));
};

$tests['chat validates model connection and auto-bootstraps the default agent'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ChatController.php');
    assert(str_contains($controller, 'Connect an AI model (OpenAI, Claude, Gemini or DeepSeek) under AI Models before sending.'));
    assert(str_contains($controller, 'Enverif Assistant') || str_contains($controller, 'auto-bootstrap') || str_contains($controller, 'Auto-resolve'));
    assert(str_contains($controller, 'Connect an AI model first, then Enverif will start automatically.'));
};

$tests['assistant chat responses render safe markdown with final provenance'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/chat/_transcript.blade.php');
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ChatController.php');
    assert(str_contains($view, 'Str::markdown'));
    assert(str_contains($view, "'allow_unsafe_links' => false"));
    assert(str_contains($view, "data_get(\$message->meta, 'agent_name')"));
    assert(str_contains($controller, "'agent_name' => \$agent->name"));
};

$tests['product brand lockups show Enverif without a Codefreex sublabel'] = function (): void {
    foreach ([
        dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php',
        dirname(__DIR__, 2).'/websites/enverif.com/index.php',
        dirname(__DIR__, 2).'/websites/docs.enverif.com/index.php',
    ] as $path) {
        $text = (string) file_get_contents($path);
        assert(!str_contains($text, '<small>by Codefreex</small>'));
        assert(!str_contains($text, 'Docs · by Codefreex'));
    }
};


$tests['manual agent runs reject paused agents and agents without enabled models'] = function (): void {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AgentController.php');
    assert(str_contains($controller, 'This agent is paused. Activate it before running.'));
    assert(str_contains($controller, 'Configure an enabled AI model connection before running this agent.'));
};

$tests['workflow runtime validation checks agent and skill executor model readiness'] = function (): void {
    $validator = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Workflows/WorkflowRuntimeValidator.php');
    $engine = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Workflows/WorkflowEngine.php');
    assert(str_contains($validator, 'model connection is missing or disabled'));
    assert(str_contains($validator, "whereHas('modelConnection'"));
    assert(str_contains($validator, 'skill nodes require at least one active agent with an enabled model connection'));
    assert(str_contains($engine, "whereHas('modelConnection'"));
};


$tests['condition workflow nodes require explicit true and false branches'] = function (): void {
    $validator = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Workflows/WorkflowDefinitionValidator.php');
    assert(str_contains($validator, 'Condition node'));
    assert(str_contains($validator, "'true'"));
    assert(str_contains($validator, "'false'"));
};



$tests['shared hosting interactive actions schedule a bounded post-response queue kick'] = function (): void {
    $root = dirname(__DIR__, 2);
    $kick = (string) file_get_contents($root.'/app/Core/Runtime/WebQueueKick.php');
    $chat = (string) file_get_contents($root.'/app/Http/Controllers/ChatController.php');
    $agents = (string) file_get_contents($root.'/app/Http/Controllers/AgentController.php');
    $workflows = (string) file_get_contents($root.'/app/Http/Controllers/WorkflowController.php');
    assert(str_contains($kick, 'app()->terminating'));
    assert(str_contains($kick, 'RuntimeProfileDetector::SHARED'));
    assert(str_contains($kick, 'RuntimeProfileDetector::COMPATIBILITY'));
    assert(str_contains($kick, 'TickRunner::class'));
    assert(str_contains($chat, '$queueKick->afterResponse()'));
    assert(str_contains($agents, '$queueKick->afterResponse()'));
    assert(str_contains($workflows, '$queueKick->afterResponse()'));
};



$tests['guest installer and login assets are release cache busted'] = function (): void {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/guest.blade.php');
    assert(str_contains($layout, "asset('assets/app.css') }}?v="));
    assert(str_contains($layout, "asset('assets/app.js') }}?v="));
    assert(str_contains($layout, "asset('assets/enverif-mark.svg') }}?v="));
};


$tests['installer view has a defensive model catalog fallback for partial shared hosting updates'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/install/index.blade.php');
    assert(str_contains($view, '$installModelCatalogJson ??'));
    assert(str_contains($view, '$modelCatalog ?? []'));
};

$tests['installer always supplies serialized model catalog to the Blade view'] = function (): void {
    $root = dirname(__DIR__, 2);
    $controller = (string) file_get_contents($root.'/app/Http/Controllers/InstallController.php');
    $view = (string) file_get_contents($root.'/resources/views/install/index.blade.php');
    assert(str_contains($view, '$installModelCatalogJson'));
    assert(str_contains($controller, "'installModelCatalogJson' =>"));
    assert(str_contains($controller, 'json_encode('));
    assert(str_contains($controller, '$modelCatalog'));
};

$tests['strict function name mapper sanitizes dotted tool names for DeepSeek'] = function (): void {
    $path = dirname(__DIR__, 2).'/app/Core/Models/StrictFunctionNameMapper.php';
    assert(is_file($path));
    require_once $path;
    $mapper = new \App\Core\Models\StrictFunctionNameMapper;
    $tools = $mapper->sanitizeTools([
        ['name' => 'memory.search', 'description' => 'x'],
        ['name' => 'connector.12.send', 'description' => 'y'],
        ['name' => 'mcp.3.foo.bar', 'description' => 'z'],
    ]);
    foreach ($tools as $tool) {
        assert(preg_match('/^[a-zA-Z0-9_-]+$/', $tool['name']) === 1, 'sanitized name must match DeepSeek pattern: '.$tool['name']);
    }
    assert($mapper->original($tools[0]['name']) === 'memory.search');
    assert($mapper->original($tools[1]['name']) === 'connector.12.send');
    assert($mapper->original($tools[2]['name']) === 'mcp.3.foo.bar');
    $openai = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Models/Providers/OpenAIProvider.php');
    $anthropic = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Models/Providers/AnthropicProvider.php');
    assert(str_contains($openai, 'StrictFunctionNameMapper'));
    assert(str_contains($anthropic, 'StrictFunctionNameMapper'));
};

$tests['tool schema normalizer encodes empty properties as JSON objects'] = function (): void {
    $path = dirname(__DIR__, 2).'/app/Core/Models/ToolSchemaNormalizer.php';
    assert(is_file($path));
    require_once $path;
    $schema = \App\Core\Models\ToolSchemaNormalizer::parameters(['type' => 'object', 'properties' => []]);
    $json = json_encode(['parameters' => $schema], JSON_UNESCAPED_SLASHES);
    assert(is_string($json));
    assert(str_contains($json, '"properties":{}'), 'empty properties must encode as {} not []: '.$json);
    assert(! str_contains($json, '"properties":[]'), 'must not encode properties as []');
    $deepseek = (string) file_get_contents(dirname(__DIR__, 2).'/app/Core/Models/Providers/DeepSeekProvider.php');
    assert(str_contains($deepseek, 'ToolSchemaNormalizer'));
    assert(is_file(dirname(__DIR__, 2).'/plugins/builtin/slack/enverif.json'));
    assert(is_file(dirname(__DIR__, 2).'/plugins/builtin/buffer/enverif.json'));
    assert(is_file(dirname(__DIR__, 2).'/app/Core/Models/ModelRegistry.php'));
    $chat = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/chat/index.blade.php');
    assert(str_contains($chat, 'composer-advanced'));
};

$tests['credential decrypt failures surface actionable recovery copy'] = function (): void {
    $root = dirname(__DIR__, 2);
    $orchestrator = (string) file_get_contents($root.'/app/Core/Agents/AgentOrchestrator.php');
    $support = (string) file_get_contents($root.'/app/Support/EncryptedCredentials.php');
    $deepseek = (string) file_get_contents($root.'/app/Core/Models/Providers/DeepSeekProvider.php');
    assert(str_contains($support, 'API key cannot be decrypted'));
    assert(str_contains($support, 'APP_KEY mismatch'));
    assert(str_contains($orchestrator, 'EncryptedCredentials::isDecryptFailure'));
    assert(str_contains($orchestrator, 'credential_decrypt_error'));
    assert(str_contains($deepseek, 'StrictFunctionNameMapper'));
    assert(str_contains($deepseek, 'sanitizeTools'));
    assert(str_contains($deepseek, 'mapper->original'));
};

$tests['chat transcript highlights provider and credential failures'] = function (): void {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/chat/_transcript.blade.php');
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    assert(str_contains($view, 'is-error'));
    assert(str_contains($view, 'message-error'));
    assert(str_contains($view, 'error-chip'));
    assert(str_contains($css, '.message-content.message-error'));
};

$passed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: {$name}\n";
        $passed++;
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL: {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}
echo "{$passed}/" . count($tests) . " standalone tests passed\n";
