<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Agents\AgentOrchestrator;
use App\Core\Models\ProviderManager;
use App\Core\Runtime\WebQueueKick;
use App\Models\{Agent, ConnectorConnection, ModelConnection, Skill};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class AgentController extends Controller
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function index(Request $request)
    {
        $query = Agent::query()->withCount(['runs', 'schedules']);
        if ($request->filled('q')) $query->where('name', 'like', '%' . $request->string('q') . '%');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return view('agents.index', ['agents' => $query->latest()->paginate(12)->withQueryString()]);
    }

    public function create()
    {
        return view('agents.form', $this->formData(new Agent));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->slug($data['name']);
        $data['policy'] = $this->policy($request);
        [$skillIds, $connectorIds] = $this->relationIds($request, $data);
        $agent = Agent::create($data);
        $this->saveAvatar($request, $agent);
        $agent->skills()->sync($skillIds);
        $agent->connectors()->sync($connectorIds);
        return redirect()->route('agents.show', $agent)->with('status', __('ui.saved'));
    }

    public function show(Agent $agent)
    {
        $agent->load(['skills', 'connectors', 'modelConnection']);
        return view('agents.show', [
            'agent' => $agent,
            'runs' => $agent->runs()->latest()->paginate(15, ['*'], 'run_page'),
            'memories' => $agent->memories()->orderByDesc('importance')->orderByDesc('updated_at')->paginate(10, ['*'], 'memory_page'),
        ]);
    }

    public function edit(Agent $agent)
    {
        return view('agents.form', $this->formData($agent));
    }

    public function update(Request $request, Agent $agent)
    {
        $data = $this->validateData($request, $agent);
        $data['policy'] = $this->policy($request);
        [$skillIds, $connectorIds] = $this->relationIds($request, $data);
        $agent->update($data);
        $this->saveAvatar($request, $agent);
        $agent->skills()->sync($skillIds);
        $agent->connectors()->sync($connectorIds);
        return redirect()->route('agents.show', $agent)->with('status', __('ui.saved'));
    }

    public function destroy(Agent $agent)
    {
        $avatarPath = $agent->avatar_path;
        $agent->delete();
        if ($avatarPath) Storage::disk('local')->delete($avatarPath);
        return redirect()->route('agents.index')->with('status', __('ui.deleted'));
    }

    public function run(Request $request, Agent $agent, AgentOrchestrator $orchestrator, WebQueueKick $queueKick)
    {
        if ($agent->status !== 'active') {
            throw ValidationException::withMessages(['agent' => 'This agent is paused. Activate it before running.']);
        }
        if (!$agent->model_connection_id || !ModelConnection::whereKey((int) $agent->model_connection_id)->where('enabled', true)->exists()) {
            throw ValidationException::withMessages(['agent' => 'Configure an enabled AI model connection before running this agent.']);
        }
        $data = $request->validate(['prompt' => 'required|string|max:20000']);
        $run = $orchestrator->start($agent, $data['prompt']);
        $queueKick->afterResponse();
        return redirect()->route('runs.show', $run);
    }


    public function avatar(Agent $agent)
    {
        if ($agent->avatar_path && Storage::disk('local')->exists($agent->avatar_path)) {
            return Storage::disk('local')->response($agent->avatar_path, null, [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Type' => Storage::disk('local')->mimeType($agent->avatar_path) ?: 'image/png',
            ]);
        }
        return redirect(asset('assets/enverif-mark.svg'));
    }

    private function saveAvatar(Request $request, Agent $agent): void
    {
        if (!$request->hasFile('avatar')) return;
        $file = $request->file('avatar');
        if (!$file || !$file->isValid()) return;
        if ($agent->avatar_path) Storage::disk('local')->delete($agent->avatar_path);
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = $file->storeAs('agents/'.$agent->workspace_id.'/'.$agent->id, 'avatar-'.Str::uuid().'.'.$extension, 'local');
        if (!$path) throw ValidationException::withMessages(['avatar' => 'The agent avatar could not be stored. Check storage permissions.']);
        $agent->update(['avatar_path' => $path]);
    }

    private function validateData(Request $request, ?Agent $existing = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'instructions' => 'required|string|max:20000',
            'status' => 'required|in:active,paused',
            'model_connection_id' => 'nullable|integer',
            'model' => 'nullable|string|max:120',
            'default_effort' => 'required|in:fast,standard,deep',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'custom_model' => 'nullable|string|max:120',
            'max_steps' => 'required|integer|min:1|max:200',
            'max_runtime_seconds' => 'required|integer|min:30|max:7200',
            'max_cost_usd' => 'required|numeric|min:0|max:1000',
            'creative_brand_name' => 'nullable|string|max:120',
            'creative_brand_voice' => 'nullable|string|max:2000',
            'creative_logo_url' => 'nullable|string|max:500',
            'creative_sample_posts' => 'nullable|string|max:5000',
            'creative_buffer_channel_id' => 'nullable|string|max:120',
            'creative_slack_channel' => 'nullable|string|max:120',
            'creative_image_model_key' => 'nullable|string|max:180',
        ]);

        $selected = (string) ($data['model'] ?? '');
        if ($selected === '__custom__') {
            $selected = trim((string) ($data['custom_model'] ?? ''));
            if ($selected === '') {
                throw ValidationException::withMessages(['custom_model' => 'Enter the custom model ID.']);
            }
        } elseif ($selected !== '' && !empty($data['model_connection_id'])) {
            $connection = ModelConnection::find((int) $data['model_connection_id']);
            $known = $connection ? ($this->providers->catalog()[$connection->provider] ?? []) : [];
            if ($connection && !in_array($selected, $known, true)) {
                throw ValidationException::withMessages(['model' => 'Choose a model from the selected provider or select Custom model ID.']);
            }
        }

        $data['model'] = $selected !== '' ? $selected : null;
        unset(
            $data['custom_model'],
            $data['avatar'],
            $data['creative_brand_name'],
            $data['creative_brand_voice'],
            $data['creative_logo_url'],
            $data['creative_sample_posts'],
            $data['creative_buffer_channel_id'],
            $data['creative_slack_channel'],
            $data['creative_image_model_key'],
        );
        $data['settings'] = $this->settings($request, $existing);

        return $data;
    }

    private function settings(Request $request, ?Agent $existing = null): array
    {
        $current = (array) ($existing?->settings ?? []);
        $creativeEnabled = $request->boolean('creative_enabled') || $request->boolean('creative_image_generation');
        $imageKey = trim((string) $request->input('creative_image_model_key', ''));
        $imageConnectionId = null;
        $imageModel = '';
        // Prefer ":" — Blade treats "|" inside @directives as a filter and 500s the agent form.
        // Accept legacy "|" keys from earlier 1.3.8 builds.
        if ($imageKey !== '' && (str_contains($imageKey, ':') || str_contains($imageKey, '|'))) {
            $separator = str_contains($imageKey, ':') ? ':' : '|';
            [$imageConnectionId, $imageModel] = array_pad(explode($separator, $imageKey, 2), 2, '');
            $imageConnectionId = (int) $imageConnectionId;
            $imageModel = trim((string) $imageModel);
            $allowed = collect($this->imageModelOptions())->contains(
                fn (array $row): bool => (int) $row['connection_id'] === $imageConnectionId && $row['model'] === $imageModel
            );
            if (! $allowed) {
                $imageConnectionId = null;
                $imageModel = '';
            }
        }
        $creative = [
            'enabled' => $creativeEnabled,
            // BC: older builds treated image_generation as the creative/social toggle.
            'image_generation' => $creativeEnabled,
            'image_connection_id' => $imageConnectionId,
            'image_model' => $imageModel,
            'brand_name' => trim((string) $request->input('creative_brand_name', '')),
            'brand_voice' => trim((string) $request->input('creative_brand_voice', '')),
            'logo_url' => trim((string) $request->input('creative_logo_url', '')),
            'sample_posts' => trim((string) $request->input('creative_sample_posts', '')),
            'default_buffer_channel_id' => trim((string) $request->input('creative_buffer_channel_id', '')),
            'default_slack_channel' => trim((string) $request->input('creative_slack_channel', '')),
        ];
        $current['creative'] = $creative;

        return $current;
    }

    /** @return list<array{connection_id:int,connection:string,provider:string,model:string}> */
    private function imageModelOptions(): array
    {
        $registry = app(\App\Core\Models\ModelRegistry::class);
        $out = [];
        foreach (ModelConnection::where('enabled', true)->whereIn('provider', ['openai', 'gemini'])->orderBy('name')->get(['id', 'name', 'provider']) as $connection) {
            foreach ($registry->imageGenerationIds((string) $connection->provider) as $modelId) {
                $out[] = [
                    'connection_id' => (int) $connection->id,
                    'connection' => (string) $connection->name,
                    'provider' => (string) $connection->provider,
                    'model' => $modelId,
                ];
            }
        }

        return $out;
    }

    private function policy(Request $request): array
    {
        return [
            'allow' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $request->input('allow_tools', '')) ?: []))),
            'deny' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $request->input('deny_tools', '')) ?: []))),
            'allow_external_writes' => $request->boolean('allow_external_writes'),
            'allow_destructive' => $request->boolean('allow_destructive'),
        ];
    }

    private function formData(Agent $agent): array
    {
        return [
            'agent' => $agent,
            'models' => ModelConnection::where('enabled', true)->get(),
            'modelCatalog' => $this->providers->catalog(),
            'imageModelOptions' => $this->imageModelOptions(),
            'skills' => Skill::where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', session('workspace_id')))->where('status', 'active')->get(),
            'connectors' => ConnectorConnection::where('enabled', true)->get(),
        ];
    }

    private function relationIds(Request $request, array $data): array
    {
        $skillIds = array_values(array_unique(array_map('intval', (array) $request->input('skills', []))));
        $connectorIds = array_values(array_unique(array_map('intval', (array) $request->input('connectors', []))));
        $validSkills = Skill::whereIn('id', $skillIds)->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', session('workspace_id')))->where('status', 'active')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $validConnectors = ConnectorConnection::whereIn('id', $connectorIds)->where('enabled', true)->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (count($validSkills) !== count($skillIds) || count($validConnectors) !== count($connectorIds)) {
            throw ValidationException::withMessages(['capabilities' => 'One or more selected skills or connectors are unavailable in this workspace.']);
        }
        if (!empty($data['model_connection_id']) && !ModelConnection::whereKey((int) $data['model_connection_id'])->where('enabled', true)->exists()) {
            throw ValidationException::withMessages(['model_connection_id' => 'The selected model connection is unavailable in this workspace.']);
        }
        return [$validSkills, $validConnectors];
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'agent';
        $slug = $base;
        $i = 2;
        while (Agent::where('slug', $slug)->exists()) $slug = $base . '-' . $i++;
        return $slug;
    }
}
