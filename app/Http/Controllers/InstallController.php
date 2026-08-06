<?php

namespace App\Http\Controllers;

use App\Core\Models\ProviderManager;
use App\Core\Runtime\{InstallationState, RuntimeProfileDetector};
use App\Models\{ModelConnection, User, Workspace};
use App\Support\EnvWriter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, DB, Hash, Schema};
use Illuminate\Support\Str;
use PDO;

final class InstallController extends Controller
{
    public function index(Request $request, ProviderManager $providers, InstallationState $installationState)
    {
        if ($installationState->isInstalled()) return redirect()->route('login');

        $redis = $this->redisProbe((string) env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379));
        $detected = RuntimeProfileDetector::select($redis, true, $redis);
        $path = preg_replace('#/install/?$#', '', $request->getRequestUri()) ?: '';
        $path = strtok($path, '?') ?: '';
        $suggestedUrl = rtrim($request->getSchemeAndHttpHost() . $path, '/');

        $modelCatalog = $providers->catalog();
        $installModelCatalogJson = json_encode(
            $modelCatalog,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';

        return view('install.index', [
            'requirements' => $this->requirements(),
            'detectedProfile' => $detected,
            'redisDetected' => $redis,
            'suggestedAppUrl' => $suggestedUrl ?: $request->getSchemeAndHttpHost(),
            'modelCatalog' => $modelCatalog,
            'installModelCatalogJson' => $installModelCatalogJson,
            'installationStatus' => $installationState->snapshot()['status'],
        ]);
    }

    public function store(Request $request, EnvWriter $env, ProviderManager $providers, InstallationState $installationState)
    {
        if ($installationState->isInstalled()) abort(409, 'Enverif is already installed.');
        $installationState->clearStaleMarker();

        $data = $request->validate([
            'app_url' => 'required|url|max:255',
            'timezone' => 'required|max:64',
            'locale' => 'required|in:en,fr,nl',
            'db_host' => 'required|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|max:64',
            'db_username' => 'required|max:128',
            'db_password' => 'nullable|max:255',
            'runtime_mode' => 'required|in:performance,shared,compatibility',
            'redis_host' => 'nullable|max:255',
            'redis_port' => 'nullable|integer|min:1|max:65535',
            'tick_budget' => 'nullable|integer|min:10|max:240',
            'admin_name' => 'required|max:120',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|min:10|confirmed',
            'workspace_name' => 'required|max:120',
            'provider' => 'nullable|in:openai,anthropic,gemini,deepseek',
            'api_key' => 'nullable|max:500',
            'default_model' => 'nullable|max:120',
            'custom_model' => 'nullable|max:120',
        ]);

        $fatalRequirements = array_filter($this->requirements(), fn (array $row): bool => ($row['required'] ?? true) && !$row['ok']);
        if ($fatalRequirements !== []) {
            return back()->withErrors(['requirements' => 'Resolve the required server checks before installing.'])->withInput();
        }
        if (!in_array($data['timezone'], timezone_identifiers_list(), true)) {
            return back()->withErrors(['timezone' => 'Choose a valid IANA timezone.'])->withInput();
        }

        $selectedModel = null;
        if (!empty($data['provider'])) {
            $provider = $providers->get($data['provider']);
            $selectedModel = (string) ($data['default_model'] ?? '');
            if ($selectedModel === '__custom__') {
                $selectedModel = trim((string) ($data['custom_model'] ?? ''));
                if ($selectedModel === '') {
                    return back()->withErrors(['custom_model' => 'Enter the custom model ID.'])->withInput();
                }
            } elseif ($selectedModel === '') {
                $selectedModel = $provider->models()[0] ?? null;
            } elseif (!in_array($selectedModel, $provider->models(), true)) {
                return back()->withErrors(['default_model' => 'Choose a model from the provider list or select Custom model ID.'])->withInput();
            }
        }

        try {
            $pdo = new PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4",
                $data['db_username'],
                $data['db_password'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $pdo->query('SELECT 1');
            $probe = 'enverif_install_probe_' . strtolower(Str::random(8));
            $pdo->exec("CREATE TABLE `{$probe}` (`id` INT NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("DROP TABLE `{$probe}`");
        } catch (\Throwable $e) {
            return back()->withErrors(['database' => 'MySQL connection/permissions check failed: ' . $e->getMessage()])->withInput();
        }

        $runtime = RuntimeProfileDetector::configuration($data['runtime_mode']);
        if ($runtime['requires_redis']) {
            $redisHost = trim((string) ($data['redis_host'] ?? ''));
            $redisPort = (int) ($data['redis_port'] ?? 6379);
            if (!$this->redisProbe($redisHost, $redisPort)) {
                return back()->withErrors(['redis' => 'Performance Mode requires a reachable Redis server. Choose Shared Hosting Mode if Redis is unavailable.'])->withInput();
            }
        }

        $key = config('app.key') ?: 'base64:' . base64_encode(random_bytes(32));
        $appUrl = rtrim($data['app_url'], '/');
        $basePath = parse_url($appUrl, PHP_URL_PATH) ?: '/';
        $basePath = '/' . trim((string) $basePath, '/');
        if ($basePath === '/') $basePath = '/';
        $webCronSecret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $tickBudget = (int) ($data['tick_budget'] ?? 45);

        $values = [
            'APP_NAME' => 'Enverif',
            'APP_ENV' => 'production',
            'APP_KEY' => $key,
            'APP_DEBUG' => false,
            'APP_URL' => $appUrl,
            'APP_TIMEZONE' => $data['timezone'],
            'APP_LOCALE' => $data['locale'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'SESSION_DRIVER' => 'database',
            'SESSION_PATH' => $basePath,
            'CACHE_STORE' => $runtime['cache'],
            'QUEUE_CONNECTION' => $runtime['queue'],
            'ENVERIF_RUNTIME_MODE' => $data['runtime_mode'],
            'ENVERIF_TICK_BUDGET' => $tickBudget,
            'ENVERIF_WEB_CRON_ENABLED' => $data['runtime_mode'] === RuntimeProfileDetector::COMPATIBILITY,
            'ENVERIF_WEB_CRON_SECRET' => $webCronSecret,
            'REDIS_HOST' => $data['redis_host'] ?: '127.0.0.1',
            'REDIS_PORT' => $data['redis_port'] ?: 6379,
        ];
        $env->write(base_path('.env'), $values);

        config([
            'app.key' => $key,
            'app.url' => $appUrl,
            'app.timezone' => $data['timezone'],
            'app.locale' => $data['locale'],
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'] ?? '',
            'database.redis.default.host' => $data['redis_host'] ?: '127.0.0.1',
            'database.redis.default.port' => $data['redis_port'] ?: 6379,
            'cache.default' => $runtime['cache'],
            'queue.default' => $runtime['queue'],
            'session.path' => $basePath,
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        try {
            Artisan::call('config:clear');
            Artisan::call('migrate', ['--force' => true]);

            foreach (['users', 'workspaces', 'workspace_user', 'sessions', 'cache', 'jobs'] as $requiredTable) {
                if (!Schema::hasTable($requiredTable)) {
                    throw new \RuntimeException("Required database table was not created: {$requiredTable}");
                }
            }

            DB::transaction(function () use ($data, $selectedModel): void {
                $workspace = Workspace::where('name', $data['workspace_name'])->oldest('id')->first();
                if ($workspace) {
                    $workspace->update([
                        'timezone' => $data['timezone'],
                        'locale' => $data['locale'],
                        'settings' => ['runtime_mode' => $data['runtime_mode']],
                    ]);
                } else {
                    $workspace = Workspace::create([
                        'name' => $data['workspace_name'],
                        'slug' => Str::slug($data['workspace_name']) . '-' . Str::lower(Str::random(5)),
                        'timezone' => $data['timezone'],
                        'locale' => $data['locale'],
                        'settings' => ['runtime_mode' => $data['runtime_mode']],
                    ]);
                }

                $user = User::where('email', $data['admin_email'])->first();
                if ($user) {
                    $user->update([
                        'name' => $data['admin_name'],
                        'password' => Hash::make($data['admin_password']),
                        'locale' => $data['locale'],
                    ]);
                } else {
                    $user = User::create([
                        'name' => $data['admin_name'],
                        'email' => $data['admin_email'],
                        'password' => Hash::make($data['admin_password']),
                        'locale' => $data['locale'],
                    ]);
                }

                DB::table('workspace_user')->updateOrInsert(
                    ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                    ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()]
                );

                (new DatabaseSeeder)->run();

                if (!empty($data['provider']) && !empty($data['api_key'])) {
                    ModelConnection::updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'provider' => $data['provider'],
                            'name' => ucfirst($data['provider']) . ' primary',
                        ],
                        [
                            'credentials' => ['api_key' => $data['api_key']],
                            'default_model' => $selectedModel,
                            'enabled' => true,
                        ]
                    );
                }
            }, 3);
        } catch (\Throwable $e) {
            $installationState->removeMarker();
            report($e);
            return back()->withErrors(['install' => 'Installation could not complete: ' . $e->getMessage()])->withInput();
        }

        $installationState->refresh();
        if (!$installationState->isInstalled()) {
            return back()->withErrors(['install' => 'Installation finished database setup but owner membership validation failed. Check the workspace_user table and retry.'])->withInput();
        }

        try {
            $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown';
            $installationState->writeMarker($data['runtime_mode'], $version);
        } catch (\Throwable $e) {
            report($e);
        }
        @unlink(storage_path('app/bootstrap.key'));
        try { Artisan::call('optimize:clear'); } catch (\Throwable $e) { report($e); }

        return redirect()->route('login')->with('status', 'Enverif is installed. Sign in with your admin account.');
    }

    /** @return list<array{label:string,ok:bool,value:string,required:bool}> */
    private function requirements(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $memory = (string) ini_get('memory_limit');
        $maxExecution = (int) ini_get('max_execution_time');

        return [
            ['label' => 'PHP 8.3+', 'ok' => PHP_VERSION_ID >= 80300, 'value' => PHP_VERSION, 'required' => true],
            ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'Available' : 'Missing', 'required' => true],
            ['label' => 'OpenSSL', 'ok' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'Available' : 'Missing', 'required' => true],
            ['label' => 'Mbstring', 'ok' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? 'Available' : 'Missing', 'required' => true],
            ['label' => 'cURL', 'ok' => extension_loaded('curl'), 'value' => extension_loaded('curl') ? 'Available' : 'Missing', 'required' => true],
            ['label' => 'ZIP', 'ok' => class_exists(\ZipArchive::class), 'value' => class_exists(\ZipArchive::class) ? 'Available' : 'Missing', 'required' => true],
            ['label' => 'Redis', 'ok' => class_exists(\Redis::class), 'value' => class_exists(\Redis::class) ? 'Available for Performance Mode' : 'Optional — database mode will be used', 'required' => false],
            ['label' => 'Storage writable', 'ok' => is_writable(storage_path()), 'value' => storage_path(), 'required' => true],
            ['label' => 'Bootstrap cache writable', 'ok' => is_writable(base_path('bootstrap/cache')), 'value' => base_path('bootstrap/cache'), 'required' => true],
            ['label' => 'Memory limit', 'ok' => true, 'value' => $memory ?: 'Host default', 'required' => false],
            ['label' => 'Execution time', 'ok' => true, 'value' => $maxExecution === 0 ? 'Unlimited' : $maxExecution . ' seconds', 'required' => false],
            ['label' => 'Shell access', 'ok' => !in_array('proc_open', $disabled, true), 'value' => in_array('proc_open', $disabled, true) ? 'Restricted — no-SSH mode supported' : 'Available', 'required' => false],
        ];
    }

    private function redisProbe(string $host, int $port): bool
    {
        if (!class_exists(\Redis::class) || $host === '' || $port <= 0) return false;
        try {
            $redis = new \Redis();
            if (!$redis->connect($host, $port, 0.5)) return false;
            $pong = $redis->ping();
            $redis->close();
            return $pong !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
