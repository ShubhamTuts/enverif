<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Connectors\ConnectorConfigurationValidator;
use App\Core\Connectors\ConnectorManager;
use App\Core\Plugins\PluginDependencyInspector;
use App\Models\ConnectorConnection;
use App\Support\EncryptedCredentials;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ConnectorController extends Controller
{
    public function index(ConnectorManager $manager)
    {
        return view('connectors.index', [
            'connections' => ConnectorConnection::latest()->paginate(20),
            'catalog' => $manager->catalog(),
        ]);
    }

    public function create(Request $request, ConnectorManager $manager)
    {
        $driver = (string) $request->query('driver', 'apify');
        return view('connectors.form', [
            'connection' => new ConnectorConnection(['driver' => $driver]),
            'driver' => $manager->get($driver),
        ]);
    }

    public function store(Request $request, ConnectorManager $manager)
    {
        ConnectorConnection::create($this->data($request, $manager));
        return redirect()->route('connectors.index')->with('status', __('ui.saved'));
    }

    public function edit(ConnectorConnection $connector, ConnectorManager $manager)
    {
        return view('connectors.form', [
            'connection' => $connector,
            'driver' => $manager->get($connector->driver),
        ]);
    }

    public function update(Request $request, ConnectorConnection $connector, ConnectorManager $manager)
    {
        $connector->update($this->data($request, $manager, $connector));
        return redirect()->route('connectors.index')->with('status', __('ui.saved'));
    }

    public function test(ConnectorConnection $connector, ConnectorManager $manager)
    {
        try {
            $ok = $manager->get($connector->driver)->test($connector);
        } catch (\Throwable $e) {
            $connector->update(['last_tested_at' => now(), 'last_test_status' => 'failed']);
            $message = EncryptedCredentials::isDecryptFailure($e)
                ? EncryptedCredentials::CONNECTOR_DECRYPT_MESSAGE
                : (trim($e->getMessage()) ?: __('ui.connection_failed'));
            return back()->with('error', $message);
        }
        $connector->update(['last_tested_at' => now(), 'last_test_status' => $ok ? 'ok' : 'failed']);
        return back()->with($ok ? 'status' : 'error', $ok ? __('ui.connection_ok') : __('ui.connection_failed'));
    }

    public function toggle(Request $request, ConnectorConnection $connector, AuditLogger $audit)
    {
        $enabled = ! (bool) $connector->enabled;
        $connector->update(['enabled' => $enabled]);
        $audit->record(
            (int) $connector->workspace_id,
            $enabled ? 'connector.enabled' : 'connector.disabled',
            ConnectorConnection::class,
            $connector->id,
            ['driver' => $connector->driver, 'name' => $connector->name],
            null,
            $request->user()?->id,
        );

        return back()->with('status', $enabled ? 'Connection enabled.' : 'Connection disabled.');
    }

    public function disconnect(Request $request, ConnectorConnection $connector, AuditLogger $audit)
    {
        $connector->update([
            'credentials' => [],
            'enabled' => false,
            'last_test_status' => 'disconnected',
            'last_tested_at' => now(),
        ]);
        $audit->record(
            (int) $connector->workspace_id,
            'connector.disconnected',
            ConnectorConnection::class,
            $connector->id,
            ['driver' => $connector->driver, 'name' => $connector->name],
            null,
            $request->user()?->id,
        );

        return back()->with('status', 'Connection credentials removed. Configuration and history were preserved.');
    }

    public function destroy(Request $request, ConnectorConnection $connector, PluginDependencyInspector $dependencies, AuditLogger $audit)
    {
        $summary = $dependencies->forConnection($connector);
        if ((int) ($summary['blocking_count'] ?? 0) > 0) {
            $message = 'Detach this connection from its agents and workflows before deleting it.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'dependencies' => $summary], 409);
            }
            abort(409, $message);
        }

        $workspaceId = (int) $connector->workspace_id;
        $id = (int) $connector->id;
        $driver = (string) $connector->driver;
        $name = (string) $connector->name;
        $connector->delete();
        $audit->record(
            $workspaceId,
            'connector.deleted',
            ConnectorConnection::class,
            $id,
            ['driver' => $driver, 'name' => $name],
            null,
            $request->user()?->id,
        );

        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $request, ConnectorManager $manager, ?ConnectorConnection $existing = null): array
    {
        $request->validate([
            'name' => 'required|max:120',
            'driver' => 'required|max:50',
            'credentials' => 'array',
            'configuration' => 'array',
            'enabled' => 'nullable|boolean',
        ]);

        $driver = $manager->get((string) $request->input('driver'));
        $submittedCredentials = (array) $request->input('credentials', []);
        $configuration = (array) $request->input('configuration', []);
        $existingCredentials = [];
        if ($existing) {
            try {
                $existingCredentials = $existing->decryptedCredentials();
            } catch (\Throwable $e) {
                if (! EncryptedCredentials::isDecryptFailure($e)) throw $e;
                $existingCredentials = [];
            }
        }
        $schema = $driver->configurationSchema();

        $missing = ConnectorConfigurationValidator::missing(
            $schema,
            $submittedCredentials,
            $configuration,
            $existingCredentials,
        );
        if ($missing !== []) {
            $messages = [];
            foreach ($missing as $path) {
                [, $key] = array_pad(explode('.', $path, 2), 2, $path);
                $metaGroup = str_starts_with($path, 'credentials.') ? 'credentials' : 'fields';
                $meta = (array) data_get($schema, $metaGroup . '.' . $key, []);
                $label = (string) ($meta['label'] ?? ucfirst(str_replace('_', ' ', $key)));
                $messages[$path] = __('ui.field_required', ['field' => $label]);
            }
            throw ValidationException::withMessages($messages);
        }

        $credentials = array_filter(
            $submittedCredentials,
            static fn ($value): bool => $value !== null && (!is_string($value) || trim($value) !== ''),
        );
        if ($existing) $credentials = array_merge($existingCredentials, $credentials);

        return [
            'name' => (string) $request->input('name'),
            'driver' => $driver->id(),
            'credentials' => $credentials,
            'configuration' => $configuration,
            'enabled' => $request->boolean('enabled', true),
        ];
    }
}
