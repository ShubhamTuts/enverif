<?php

namespace App\Http\Controllers;

use App\Core\Models\ProviderManager;
use App\Core\Security\OutboundEndpointPolicy;
use App\Models\ModelConnection;
use App\Support\EncryptedCredentials;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ModelConnectionController extends Controller
{
    public function index(ProviderManager $providers)
    {
        return view('models.index', [
            'connections' => ModelConnection::latest()->paginate(20),
            'catalog' => $providers->catalog(),
        ]);
    }

    public function create(Request $request, ProviderManager $providers)
    {
        $provider = (string) $request->query('provider', 'openai');
        return view('models.form', [
            'connection' => new ModelConnection(['provider' => $provider]),
            'provider' => $providers->get($provider),
            'catalog' => $providers->catalog(),
        ]);
    }

    public function store(Request $request, ProviderManager $providers, OutboundEndpointPolicy $endpoints)
    {
        ModelConnection::create($this->data($request, $providers, $endpoints));
        return redirect()->route('models.index')->with('status', __('ui.saved'));
    }

    public function edit(ModelConnection $model, ProviderManager $providers)
    {
        return view('models.form', [
            'connection' => $model,
            'provider' => $providers->get($model->provider),
            'catalog' => $providers->catalog(),
        ]);
    }

    public function update(Request $request, ModelConnection $model, ProviderManager $providers, OutboundEndpointPolicy $endpoints)
    {
        $model->update($this->data($request, $providers, $endpoints, $model));
        return redirect()->route('models.index')->with('status', __('ui.saved'));
    }

    public function test(ModelConnection $model, ProviderManager $providers, OutboundEndpointPolicy $endpoints)
    {
        if (trim((string) $model->base_url) !== '') $endpoints->assertAllowed((string) $model->base_url);
        $provider = $providers->get($model->provider);
        $result = method_exists($provider, 'testWithMessage')
            ? $provider->testWithMessage($model)
            : ['ok' => $provider->test($model), 'message' => ''];

        $ok = (bool) ($result['ok'] ?? false);
        $message = trim((string) ($result['message'] ?? ''));
        if ($ok) {
            $model->update(['last_tested_at' => now(), 'last_test_status' => 'ok']);
            return back()->with('status', __('ui.connection_ok'));
        }

        $model->update(['last_tested_at' => now(), 'last_test_status' => 'failed']);
        if ($message === '' || str_contains(strtolower($message), 'connection test failed')) $message = __('ui.connection_failed');
        return back()->with('error', $message);
    }

    public function destroy(ModelConnection $model)
    {
        if ($model->agents()->exists()) abort(409, 'Move or detach agents from this model connection before deleting it.');
        $model->delete();
        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $request, ProviderManager $providers, OutboundEndpointPolicy $endpoints, ?ModelConnection $existing = null): array
    {
        $data = $request->validate([
            'name' => 'required|max:120',
            'provider' => 'required|in:openai,anthropic,gemini,deepseek',
            'api_key' => ($existing ? 'nullable' : 'required') . '|max:500',
            'default_model' => 'nullable|max:120',
            'custom_model' => 'nullable|max:120',
            'base_url' => 'nullable|url|max:500',
            'input_price_per_million' => 'nullable|numeric|min:0|max:1000',
            'output_price_per_million' => 'nullable|numeric|min:0|max:1000',
            'enabled' => 'nullable|boolean',
        ]);

        $baseUrl = trim((string) ($data['base_url'] ?? ''));
        if ($baseUrl !== '') $endpoints->assertAllowed($baseUrl);
        if ($existing && trim((string) $existing->base_url) !== $baseUrl && empty($data['api_key'])) {
            throw ValidationException::withMessages([
                'api_key' => 'Re-enter the API key when changing the endpoint so an existing secret cannot be silently forwarded to a new host.',
            ]);
        }

        $provider = $providers->get($data['provider']);
        $selectedModel = (string) ($data['default_model'] ?? '');
        if ($selectedModel === '__custom__') {
            $selectedModel = trim((string) ($data['custom_model'] ?? ''));
            if ($selectedModel === '') throw ValidationException::withMessages(['custom_model' => 'Enter the custom model ID.']);
        } elseif ($selectedModel === '') {
            $selectedModel = $provider->models()[0] ?? null;
        } elseif (!in_array($selectedModel, $provider->models(), true)) {
            throw ValidationException::withMessages(['default_model' => 'Choose a model from the provider list or select Custom model ID.']);
        }

        $credentials = [];
        if ($existing) {
            try {
                $credentials = $existing->decryptedCredentials();
            } catch (\Throwable $e) {
                if (! EncryptedCredentials::isDecryptFailure($e)) throw $e;
                if (empty($data['api_key'])) throw ValidationException::withMessages(['api_key' => EncryptedCredentials::DECRYPT_MESSAGE]);
                $credentials = [];
            }
        }
        if (!empty($data['api_key'])) $credentials['api_key'] = $data['api_key'];
        $data['pricing'] = [
            'input_per_million' => (float) ($data['input_price_per_million'] ?? 0),
            'output_per_million' => (float) ($data['output_price_per_million'] ?? 0),
        ];
        unset($data['api_key'], $data['input_price_per_million'], $data['output_price_per_million'], $data['custom_model']);
        $data['base_url'] = $baseUrl !== '' ? $baseUrl : null;
        $data['default_model'] = $selectedModel;
        $data['credentials'] = $credentials;
        $data['enabled'] = $request->boolean('enabled', true);
        return $data;
    }
}
