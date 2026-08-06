<?php

namespace App\Http\Controllers;

use App\Core\Models\ProviderManager;
use App\Models\ModelConnection;
use Illuminate\Http\Request;

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

    public function store(Request $request, ProviderManager $providers)
    {
        ModelConnection::create($this->data($request, $providers));
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

    public function update(Request $request, ModelConnection $model, ProviderManager $providers)
    {
        $model->update($this->data($request, $providers, $model));
        return redirect()->route('models.index')->with('status', __('ui.saved'));
    }

    public function test(ModelConnection $model, ProviderManager $providers)
    {
        $ok = $providers->get($model->provider)->test($model);
        $model->update(['last_tested_at' => now(), 'last_test_status' => $ok ? 'ok' : 'failed']);
        return back()->with($ok ? 'status' : 'error', $ok ? __('ui.connection_ok') : __('ui.connection_failed'));
    }

    public function destroy(ModelConnection $model)
    {
        $model->delete();
        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $request, ProviderManager $providers, ?ModelConnection $existing = null): array
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

        $provider = $providers->get($data['provider']);
        $selectedModel = (string) ($data['default_model'] ?? '');
        if ($selectedModel === '__custom__') {
            $selectedModel = trim((string) ($data['custom_model'] ?? ''));
            if ($selectedModel === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['custom_model' => 'Enter the custom model ID.']);
            }
        } elseif ($selectedModel === '') {
            $selectedModel = $provider->models()[0] ?? null;
        } elseif (!in_array($selectedModel, $provider->models(), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['default_model' => 'Choose a model from the provider list or select Custom model ID.']);
        }
        $credentials = $existing ? (array) ($existing->credentials ?? []) : [];
        if (!empty($data['api_key'])) {
            $credentials['api_key'] = $data['api_key'];
        }
        $data['pricing'] = [
            'input_per_million' => (float) ($data['input_price_per_million'] ?? 0),
            'output_per_million' => (float) ($data['output_price_per_million'] ?? 0),
        ];
        unset($data['api_key'], $data['input_price_per_million'], $data['output_price_per_million'], $data['custom_model']);
        $data['default_model'] = $selectedModel;
        $data['credentials'] = $credentials;
        $data['enabled'] = $request->boolean('enabled', true);
        return $data;
    }
}
