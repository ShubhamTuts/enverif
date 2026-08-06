<?php

declare(strict_types=1);

use App\Core\Plugins\PluginManifestValidator;

$root = str_replace('\\', '/', dirname(__DIR__));
$errors = [];
$checks = 0;

$fail = static function (string $message) use (&$errors): void {
    $errors[] = $message;
};

$rel = static function (string $path) use ($root): string {
    return ltrim(str_replace($root, '', str_replace('\\', '/', $path)), '/');
};

$required = [
    'README.md', 'LICENSE', 'SECURITY.md', 'CONTRIBUTING.md', 'GOVERNANCE.md', 'CODE_OF_CONDUCT.md',
    'composer.json', 'package.json', '.env.example', 'docker-compose.yml', 'Dockerfile', 'install.sh',
    'app/Core/Agents/AgentOrchestrator.php', 'app/Core/Agents/SystemPromptBuilder.php',
    'app/Core/Plugins/PluginRegistry.php', 'app/Core/Plugins/PluginManifestValidator.php',
    'skills/builtin/b2b-sales-agent/SKILL.md', 'skills/builtin/gtm-agents-starter/SKILL.md',
    'skills/builtin/goose-growth-starter/SKILL.md', 'docs/user-guide/memory-delegation.md',
    'VERSION', 'README-INSTALL.txt', '.htaccess', 'public/.htaccess',
    'scripts/build-release.sh', 'scripts/ci-installer-smoke.sh',
    '.github/workflows/ci.yml', '.github/workflows/release.yml',
    'docs/hosting/shared-hosting.md', 'docs/user-guide/email-automation.md', 'docs/user-guide/workflows.md',
    'docs/user-guide/models.md', 'docs/extensions/plugins.md', 'docs/extensions/skills.md', 'docs/extensions/mcp.md',
    'tests/Feature/CoreHttpSmokeTest.php', 'tests/Feature/ChatHttpTest.php', 'tests/Feature/WorkflowRuntimeTest.php', 'tests/Feature/AgentAvatarTest.php', 'tests/Feature/InstallerHttpTest.php',
    'app/Core/Models/Providers/OpenAIProvider.php', 'app/Core/Models/Providers/AnthropicProvider.php',
    'app/Core/Models/Providers/GeminiProvider.php', 'app/Core/Models/Providers/DeepSeekProvider.php',
];
foreach ($required as $file) {
    $checks++;
    if (!is_file($root.'/'.$file)) $fail("Missing required file: {$file}");
}

$checks++;
$version = trim((string) file_get_contents($root.'/VERSION'));
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) $fail('VERSION must be a semantic X.Y.Z version.');
$prd = (string) file_get_contents($root.'/docs/PRODUCT-REQUIREMENTS.md');
if (!str_contains($prd, '**Target release:** '.$version)) $fail('Product requirements target release must match VERSION.');
$readme = (string) file_get_contents($root.'/README.md');
if (!str_contains($readme, '## Enverif '.$version)) $fail('README release heading must match VERSION.');

foreach (['composer.json', 'package.json'] as $jsonFile) {
    $checks++;
    try {
        json_decode((string) file_get_contents($root.'/'.$jsonFile), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $fail("Invalid JSON in {$jsonFile}: {$e->getMessage()}");
    }
}

require_once $root.'/app/Core/Plugins/PluginManifestValidator.php';
$manifestFiles = array_merge(
    glob($root.'/plugins/builtin/*/enverif.json') ?: [],
    glob($root.'/plugins/external/*/enverif.json') ?: [],
);
foreach ($manifestFiles as $path) {
    $checks++;
    try {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        PluginManifestValidator::validate($data);
    } catch (Throwable $e) {
        $fail('Invalid plugin manifest '.str_replace($root.'/', '', $path).": {$e->getMessage()}");
    }
}

foreach (glob($root.'/plugins/builtin/*/enverif.json') ?: [] as $path) {
    $checks++;
    $data = json_decode((string) file_get_contents($path), true);
    $directory = dirname($path);
    if (($data['developer'] ?? null) !== 'Codefreex') {
        $fail('First-party plugin developer must be Codefreex: '.str_replace($root.'/', '', $path));
    }
    if (($data['developer_url'] ?? null) !== 'https://codefreex.com/') {
        $fail('First-party plugin developer_url must point to Codefreex: '.str_replace($root.'/', '', $path));
    }
    $icon = (string) ($data['icon'] ?? '');
    if ($icon === '' || str_contains($icon, '..') || !is_file($directory.'/'.$icon)) {
        $fail('First-party plugin must ship its declared local icon: '.str_replace($root.'/', '', $path));
    }
}

$checks++;
$ciWorkflow = (string) file_get_contents($root.'/.github/workflows/ci.yml');
if (!str_contains($ciWorkflow, 'installer-http:') || !str_contains($ciWorkflow, './scripts/ci-installer-smoke.sh')) {
    $fail('CI must exercise the fresh HTTP installer, login and authenticated core screens.');
}

$checks++;
$rootHtaccess = (string) file_get_contents($root.'/.htaccess');
if (!str_contains($rootHtaccess, '\\.env')) $fail('Root .htaccess does not visibly protect .env.');
if (!str_contains($rootHtaccess, '(?:git|github)') && !str_contains($rootHtaccess, '\\.git')) $fail('Root .htaccess does not visibly protect .git.');
foreach (['vendor', 'storage'] as $protected) {
    if (!str_contains($rootHtaccess, $protected)) $fail("Root .htaccess does not visibly protect {$protected}.");
}

$checks++;
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
if (isset($composer['require']['ext-redis'])) {
    $fail('ext-redis must remain optional so shared-hosting installs can run without Redis.');
}

$checks++;
$envExample = (string) file_get_contents($root.'/.env.example');
foreach (['QUEUE_CONNECTION=sync', 'CACHE_STORE=file', 'SESSION_DRIVER=file'] as $expected) {
    if (!str_contains($envExample, $expected)) $fail("Fresh-install bootstrap default missing from .env.example: {$expected}");
}

$checks++;
$bootstrapApp = (string) file_get_contents($root.'/bootstrap/app.php');
if (!str_contains($bootstrapApp, 'PrepareInstallerRuntime::class')) {
    $fail('PrepareInstallerRuntime must run before the web middleware stack so fresh installs never depend on database sessions/cache/queues.');
}

$checks++;
$installerMiddleware = (string) file_get_contents($root.'/app/Http/Middleware/PrepareInstallerRuntime.php');
foreach (["'session.driver' => \$stores['session']", "'cache.default' => \$stores['cache']", "'queue.default' => \$stores['queue']", "storage_path('app/bootstrap.key')"] as $expected) {
    if (!str_contains($installerMiddleware, $expected)) $fail("Installer runtime bootstrap is missing: {$expected}");
}

$checks++;
$installerController = (string) file_get_contents($root.'/app/Http/Controllers/InstallController.php');
if (!str_contains($installerController, "'SESSION_DRIVER' => 'database'")) {
    $fail('Installer must promote sessions to the database driver after validating database configuration.');
}
if (!str_contains($installerController, "@unlink(storage_path('app/bootstrap.key'))")) {
    $fail('Installer must remove the temporary bootstrap key after APP_KEY is persisted.');
}
$installerView = (string) file_get_contents($root.'/resources/views/install/index.blade.php');
foreach ([
    "'modelCatalog' => \$modelCatalog",
    "'installModelCatalogJson' => \$installModelCatalogJson",
    'json_encode(',
] as $expected) {
    if (!str_contains($installerController, $expected)) $fail("Installer view contract is missing: {$expected}");
}
if (!str_contains($installerView, '$installModelCatalogJson')) {
    $fail('Installer view must render the serialized model catalog provided by InstallController.');
}

$checks++;
foreach (glob($root.'/app/**/*.php') ?: [] as $unused) { /* Recursive scan below handles runtime env safety. */ }
$runtimePhp = [];
$runtimeIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($runtimeIterator as $runtimeFile) {
    if ($runtimeFile->isFile() && str_ends_with($runtimeFile->getFilename(), '.php')) $runtimePhp[] = $runtimeFile->getPathname();
}
foreach ($runtimePhp as $runtimePath) {
    $relative = $rel($runtimePath);
    if ($relative === 'app/Http/Controllers/InstallController.php') continue;
    if (preg_match('/\benv\s*\(/', (string) file_get_contents($runtimePath))) {
        $fail("Runtime code must use config() instead of env() so config:cache is safe: {$relative}");
    }
}

$translations = [];
foreach (['en', 'fr', 'nl'] as $locale) {
    $checks++;
    try {
        $translations[$locale] = require $root."/lang/{$locale}/ui.php";
        if (!is_array($translations[$locale])) $fail("{$locale} translation file did not return an array.");
    } catch (Throwable $e) {
        $fail("Could not load {$locale} translation file: {$e->getMessage()}");
        $translations[$locale] = [];
    }
}
$englishKeys = array_keys($translations['en'] ?? []);
sort($englishKeys);
foreach (['fr', 'nl'] as $locale) {
    $keys = array_keys($translations[$locale] ?? []);
    sort($keys);
    $checks++;
    $missing = array_values(array_diff($englishKeys, $keys));
    $extra = array_values(array_diff($keys, $englishKeys));
    if ($missing) $fail("{$locale} is missing translation keys: ".implode(', ', $missing));
    if ($extra) $fail("{$locale} has extra translation keys: ".implode(', ', $extra));
}

$sourceFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$skipSegments = ['/.git/', '/vendor/', '/storage/framework/', '/storage/logs/'];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = str_replace('\\', '/', $file->getPathname());
    $skip = false;
    foreach ($skipSegments as $segment) if (str_contains($path, $segment)) { $skip = true; break; }
    if ($skip) continue;
    $sourceFiles[] = $path;
}

$usedTranslationKeys = [];
foreach ($sourceFiles as $path) {
    if (!preg_match('/\.(php|blade\.php)$/', $path)) continue;
    $text = (string) file_get_contents($path);
    if (preg_match_all('/(?:__|trans)\(\s*[\'\"]ui\.([A-Za-z0-9_.-]+)[\'\"]|@lang\(\s*[\'\"]ui\.([A-Za-z0-9_.-]+)[\'\"]/', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) $usedTranslationKeys[] = $match[1] !== '' ? $match[1] : $match[2];
    }
    if (str_ends_with($path, '.blade.php') && preg_match('/\{\{(?:(?!\}\}).)*\{\{/s', $text)) {
        $fail('Possible malformed nested Blade expression: '.str_replace($root.'/', '', $path));
    }
    if (str_ends_with($path, '.blade.php')) {
        $relative = str_replace($root.'/', '', $path);
        if (str_contains($text, '@json(')) {
            $fail('Blade templates must pre-serialize JSON instead of using the brittle @json directive: '.$relative);
        }
        $pairs = [
            'if' => ['/@if\s*\(/', '/@endif\b/'],
            'unless' => ['/@unless\s*\(/', '/@endunless\b/'],
            'foreach' => ['/@foreach\s*\(/', '/@endforeach\b/'],
            'forelse' => ['/@forelse\s*\(/', '/@endforelse\b/'],
            'for' => ['/@for\s*\(/', '/@endfor\b/'],
            'while' => ['/@while\s*\(/', '/@endwhile\b/'],
            'switch' => ['/@switch\s*\(/', '/@endswitch\b/'],
        ];
        foreach ($pairs as $directive => [$openPattern, $closePattern]) {
            $opens = preg_match_all($openPattern, $text);
            $closes = preg_match_all($closePattern, $text);
            if ($opens !== $closes) {
                $fail("Unbalanced Blade @{$directive} directives in {$relative}: {$opens} open / {$closes} close.");
            }
        }
    }
}
$checks++;
$undefined = array_values(array_diff(array_unique($usedTranslationKeys), $englishKeys));
sort($undefined);
if ($undefined) $fail('UI translation keys referenced but missing from English: '.implode(', ', $undefined));

$skillFiles = glob($root.'/skills/builtin/*/SKILL.md') ?: [];
foreach ($skillFiles as $skill) {
    $checks++;
    $text = (string) file_get_contents($skill);
    if (!preg_match('/^---\s*\R.*?\R---\s*\R/s', $text)) $fail('Skill missing frontmatter: '.str_replace($root.'/', '', $skill));
    if (stripos($text, 'api_key=') !== false || preg_match('/(?:sk-|ghp_|github_pat_)[A-Za-z0-9_-]{20,}/', $text)) {
        $fail('Possible secret in skill: '.str_replace($root.'/', '', $skill));
    }
}

$forbiddenPaths = ['docs/superpowers'];
foreach ($forbiddenPaths as $path) {
    $checks++;
    if (file_exists($root.'/'.$path)) $fail("Internal planning path must not ship: {$path}");
}

$forbiddenText = ['Pasted text(', 'APPROVE SPEC', 'developer prompt', 'internal planning document'];
foreach ($sourceFiles as $path) {
    if (str_contains($path, '/scripts/verify.php')) continue;
    $size = filesize($path);
    if ($size === false || $size > 2_000_000) continue;
    $text = @file_get_contents($path);
    if ($text === false || str_contains($text, "\0")) continue;
    foreach ($forbiddenText as $needle) {
        if (stripos($text, $needle) !== false) $fail('Internal/development text found in '.str_replace($root.'/', '', $path).": {$needle}");
    }
}
$checks++;

if (is_file($root.'/resources/js/app.js') && is_file($root.'/public/assets/app.js')) {
    $checks++;
    if (hash_file('sha256', $root.'/resources/js/app.js') !== hash_file('sha256', $root.'/public/assets/app.js')) {
        $fail('resources/js/app.js and public/assets/app.js are not synchronized.');
    }
}
if (is_file($root.'/resources/css/app.css') && is_file($root.'/public/assets/app.css')) {
    $checks++;
    if (hash_file('sha256', $root.'/resources/css/app.css') !== hash_file('sha256', $root.'/public/assets/app.css')) {
        $fail('resources/css/app.css and public/assets/app.css are not synchronized.');
    }
}


$checks++;
$layout = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');
if (str_contains($layout, '<small>by Codefreex</small>') || str_contains($layout, 'Docs · by Codefreex') || str_contains($layout, 'BY CODEFREEX') || str_contains($layout, 'Enverif by Codefreex')) {
    $fail('Product brand lockups must display Enverif without a Codefreex sublabel.');
}
foreach (['assets/app.css', 'assets/app.js'] as $asset) {
    if (!str_contains($layout, "asset('{$asset}') }}?v=")) $fail("Application asset is missing release cache-busting: {$asset}");
}
$guestLayout = (string) file_get_contents($root.'/resources/views/layouts/guest.blade.php');
foreach (['assets/enverif-mark.svg', 'assets/app.css', 'assets/app.js'] as $asset) {
    if (!str_contains($guestLayout, "asset('{$asset}') }}?v=")) $fail("Guest/installer asset is missing release cache-busting: {$asset}");
}

$checks++;
$appCss = (string) file_get_contents($root.'/resources/css/app.css');
if (str_contains($appCss, '.agentic-main{margin-left:260px')) $fail('Desktop agentic shell still double-offsets the fixed sidebar.');
if (!str_contains($appCss, '.agentic-main{margin-left:0')) $fail('Desktop agentic shell must neutralize main-content sidebar margin.');

$checks++;
$chatJs = (string) file_get_contents($root.'/resources/js/app.js');
$chatController = (string) file_get_contents($root.'/app/Http/Controllers/ChatController.php');
foreach (["event.preventDefault()", "Accept: 'application/json'", 'history.replaceState', 'data.transcript_html'] as $needle) {
    if (!str_contains($chatJs, $needle)) $fail("Chat async transport missing: {$needle}");
}
if (str_contains($chatJs, 'window.location.assign(data.redirect_url)')) $fail('Chat submit must not navigate through a send endpoint.');
foreach (['expectsJson()', "'thread_url'", "'send_url'", "'status_url'", "'transcript_html'"] as $needle) {
    if (!str_contains($chatController, $needle)) $fail("Chat JSON response contract missing: {$needle}");
}
$routes = (string) file_get_contents($root.'/routes/web.php');
if (str_contains($routes, '/chats/send/')) $fail('Legacy /chats/send endpoint must not be exposed.');

$checks++;
if (!is_file($root.'/app/Core/Runtime/WebQueueKick.php')) $fail('Shared-host interactive WebQueueKick is missing.');
$queueKickTargets = [
    'app/Http/Controllers/ChatController.php',
    'app/Http/Controllers/AgentController.php',
    'app/Http/Controllers/WorkflowController.php',
    'app/Http/Controllers/WorkflowRunController.php',
    'app/Http/Controllers/ApprovalController.php',
    'app/Http/Controllers/WorkflowWebhookController.php',
];
foreach ($queueKickTargets as $target) {
    $checks++;
    if (!str_contains((string) file_get_contents($root.'/'.$target), '$queueKick->afterResponse()')) {
        $fail("Interactive shared-hosting queue kick is not wired in {$target}");
    }
}

$checks++;
$workflowForm = (string) file_get_contents($root.'/resources/views/workflows/form.blade.php');
if (!str_contains($workflowForm, '$workflowResourcesJson') || str_contains($workflowForm, '@json([')) {
    $fail('Workflow form must pre-serialize builder resources without inline Blade arrays.');
}

$checks++;
foreach (['gmail','outlook','smtp','apollo','apify','google-search-console','google-analytics','google-maps','calendly','automation-webhook','slack','buffer','openai','anthropic','gemini','deepseek'] as $slug) {
    if (!is_file($root.'/public/assets/integrations/'.$slug.'.svg')) $fail("Missing bundled integration icon: {$slug}.svg");
}

$checks++;
foreach (['deepseek-v4-flash','claude-sonnet-5','gemini-3.6-flash','gpt-5'] as $needle) {
    $providersBlob = (string) file_get_contents($root.'/app/Core/Models/Providers/DeepSeekProvider.php')
        .(string) file_get_contents($root.'/app/Core/Models/Providers/AnthropicProvider.php')
        .(string) file_get_contents($root.'/app/Core/Models/Providers/GeminiProvider.php')
        .(string) file_get_contents($root.'/app/Core/Models/Providers/OpenAIProvider.php');
    if (!str_contains($providersBlob, $needle)) $fail("Model catalog missing current API id: {$needle}");
}

$checks++;
$chatComposer = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
if (!str_contains($chatComposer, 'composer-attach') || !str_contains($chatComposer, 'm21.44 11.05')) {
    $fail('Chat composer must render a paperclip SVG for file uploads.');
}
if (str_contains($chatComposer, '⌁')) {
    $fail('Chat composer must not use the broken attach glyph.');
}

$checks++;
$viewsReferenced = [];
foreach ($runtimePhp as $runtimePath) {
    $text = (string) file_get_contents($runtimePath);
    if (preg_match_all('/\bview\(\s*[\'"]([^\'"]+)[\'"]/', $text, $matches)) {
        foreach ($matches[1] as $viewName) $viewsReferenced[$viewName] = true;
    }
}
foreach (array_keys($viewsReferenced) as $viewName) {
    $viewPath = $root.'/resources/views/'.str_replace('.', '/', $viewName).'.blade.php';
    if (!is_file($viewPath)) $fail("Controller references missing Blade view: {$viewName}");
}


$criticalViewContracts = [
    ['app/Http/Controllers/InstallController.php', ['requirements','detectedProfile','redisDetected','suggestedAppUrl','modelCatalog','installModelCatalogJson','installationStatus']],
    ['app/Http/Controllers/AgentController.php', ['agents','agent','runs','memories','models','modelCatalog','skills','connectors']],
    ['app/Http/Controllers/ApprovalController.php', ['approvals']],
    ['app/Http/Controllers/AuditController.php', ['events']],
    ['app/Http/Controllers/CampaignController.php', ['campaigns','campaign','members','availableLeads']],
    ['app/Http/Controllers/ChatController.php', ['thread','threads','agents','modelConnections','modelCatalog','connectors','skills','workflows','leads','campaigns','selectedAgentId','selectedConnectionId','selectedModel','selectedEffort','chatQuery','showArchived']],
    ['app/Http/Controllers/ConnectorController.php', ['connections','catalog','connection','driver']],
    ['app/Http/Controllers/DashboardController.php', ['metrics','runs','schedules','hotLeads']],
    ['app/Http/Controllers/LeadController.php', ['leads','lead','activities']],
    ['app/Http/Controllers/McpServerController.php', ['servers','server']],
    ['app/Http/Controllers/ModelConnectionController.php', ['connections','catalog','connection','provider']],
    ['app/Http/Controllers/RunController.php', ['run']],
    ['app/Http/Controllers/ScheduleController.php', ['schedules','calendar','month','prevMonth','nextMonth','schedule','agents','workflows']],
    ['app/Http/Controllers/SettingsController.php', ['workspace','health','models','emailConnections','integrationCount','cronCommand','webCronUrl','isCompatibilityMode']],
    ['app/Http/Controllers/SkillController.php', ['skills','skill']],
    ['app/Http/Controllers/WorkflowController.php', ['workflows','workflow','runs','runtimeErrors','agents','connectors','skills','campaigns','connectorCatalog']],
    ['app/Http/Controllers/WorkflowRunController.php', ['run']],
];
foreach ($criticalViewContracts as [$controllerPath, $variables]) {
    $controllerText = (string) file_get_contents($root.'/'.$controllerPath);
    foreach ($variables as $variable) {
        $checks++;
        if (!preg_match('/[\'\"]'.preg_quote($variable, '/').'[\'\"]\s*=>/', $controllerText)) {
            $fail("Controller/view contract is missing {$variable} in {$controllerPath}");
        }
    }
}

// Marketing/docs static sites are optional for the application repository.
$checks++;
if (getenv('ENVERIF_VERIFY_SITES') === '1' && is_dir($root.'/websites/docs.enverif.com/content')) {
    $docCopies = [
        'docs/architecture.md' => 'websites/docs.enverif.com/content/architecture.md',
        'docs/PRODUCT-REQUIREMENTS.md' => 'websites/docs.enverif.com/content/PRODUCT-REQUIREMENTS.md',
    ];
    foreach (['getting-started', 'user-guide', 'extensions', 'operations', 'hosting', 'developers', 'contributing'] as $group) {
        foreach (glob($root."/docs/{$group}/*.md") ?: [] as $source) {
            $sourceRel = $rel($source);
            $docCopies[$sourceRel] = 'websites/docs.enverif.com/content/'.substr($sourceRel, strlen('docs/'));
        }
    }
    foreach ($docCopies as $source => $copy) {
        $checks++;
        if (!is_file($root.'/'.$copy)) {
            $fail("Docs site is missing content copy: {$copy}");
        } elseif (is_file($root.'/'.$source) && hash_file('sha256', $root.'/'.$source) !== hash_file('sha256', $root.'/'.$copy)) {
            $fail("Docs site content is stale: {$copy}");
        }
    }
}

$php = PHP_BINARY;
$linted = 0;
foreach ($sourceFiles as $path) {
    if (!str_ends_with($path, '.php')) continue;
    $cmd = escapeshellarg($php).' -l '.escapeshellarg($path).' 2>&1';
    exec($cmd, $output, $code);
    $checks++;
    $linted++;
    if ($code !== 0) $fail('PHP syntax failed for '.str_replace($root.'/', '', $path).': '.implode(' ', $output));
    $output = [];
}

$node = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    foreach (['resources/js/app.js', 'public/assets/app.js'] as $file) {
        if (!is_file($root.'/'.$file)) continue;
        $checks++;
        exec(escapeshellarg($node).' --check '.escapeshellarg($root.'/'.$file).' 2>&1', $output, $code);
        if ($code !== 0) $fail("JavaScript syntax failed for {$file}: ".implode("\n", $output));
        $output = [];
    }
}

$checks++;
if (!str_contains((string) file_get_contents($root.'/README.md'), 'github.com/ShubhamTuts/enverif')) {
    $fail('README does not point to the intended Enverif GitHub repository path.');
}

if ($errors) {
    fwrite(STDERR, "Verification failed ({$checks} checks; {$linted} PHP files linted):\n- ".implode("\n- ", array_values(array_unique($errors)))."\n");
    exit(1);
}

echo "Enverif release verification passed ({$checks} checks; {$linted} PHP files linted; ".count($englishKeys)." UI keys verified).\n";
