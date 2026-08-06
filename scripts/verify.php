<?php

declare(strict_types=1);

use App\Core\Plugins\PluginManifestValidator;

$root = dirname(__DIR__);
$errors = [];
$checks = 0;

$fail = static function (string $message) use (&$errors): void {
    $errors[] = $message;
};

$required = [
    'README.md', 'LICENSE', 'SECURITY.md', 'CONTRIBUTING.md', 'GOVERNANCE.md', 'CODE_OF_CONDUCT.md',
    'composer.json', 'package.json', '.env.example', 'docker-compose.yml', 'Dockerfile', 'install.sh',
    'app/Core/Agents/AgentOrchestrator.php', 'app/Core/Agents/SystemPromptBuilder.php',
    'app/Core/Plugins/PluginRegistry.php', 'app/Core/Plugins/PluginManifestValidator.php',
    'skills/builtin/b2b-sales-agent/SKILL.md', 'skills/builtin/gtm-agents-starter/SKILL.md',
    'skills/builtin/goose-growth-starter/SKILL.md', 'websites/enverif.com/index.php',
    'websites/docs.enverif.com/index.php', 'docs/user-guide/memory-delegation.md',
    'VERSION', 'README-INSTALL.txt', '.htaccess', 'public/.htaccess',
    'scripts/build-site.php', 'scripts/check-site.php', 'scripts/build-release.sh',
    '.github/workflows/ci.yml', '.github/workflows/pages.yml', '.github/workflows/release.yml',
    'docs/hosting/shared-hosting.md', 'docs/user-guide/email-automation.md', 'docs/user-guide/workflows.md',
];
foreach ($required as $file) {
    $checks++;
    if (!is_file($root.'/'.$file)) $fail("Missing required file: {$file}");
}

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
    if (($data['developer'] ?? null) !== 'Codefreex') {
        $fail('First-party plugin developer must be Codefreex: '.str_replace($root.'/', '', $path));
    }
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

$checks++;
foreach (glob($root.'/app/**/*.php') ?: [] as $unused) { /* Recursive scan below handles runtime env safety. */ }
$runtimePhp = [];
$runtimeIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($runtimeIterator as $runtimeFile) {
    if ($runtimeFile->isFile() && str_ends_with($runtimeFile->getFilename(), '.php')) $runtimePhp[] = $runtimeFile->getPathname();
}
foreach ($runtimePhp as $runtimePath) {
    $relative = str_replace($root.'/', '', str_replace('\\', '/', $runtimePath));
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

$docCopies = [
    'docs/architecture.md' => 'websites/docs.enverif.com/content/architecture.md',
    'docs/PRODUCT-REQUIREMENTS.md' => 'websites/docs.enverif.com/content/PRODUCT-REQUIREMENTS.md',
];
foreach (['getting-started', 'user-guide', 'extensions', 'operations', 'hosting', 'developers', 'contributing'] as $group) {
    foreach (glob($root."/docs/{$group}/*.md") ?: [] as $source) {
        $rel = str_replace($root.'/', '', $source);
        $docCopies[$rel] = 'websites/docs.enverif.com/content/'.substr($rel, strlen('docs/'));
    }
}
foreach ($docCopies as $source => $copy) {
    $checks++;
    if (!is_file($root.'/'.$copy)) {
        $fail("Docs site is missing content copy: {$copy}");
    } elseif (hash_file('sha256', $root.'/'.$source) !== hash_file('sha256', $root.'/'.$copy)) {
        $fail("Docs site content is stale: {$copy}");
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
