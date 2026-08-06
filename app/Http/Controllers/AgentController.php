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
        $data = $this->validateData($request);
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

    private function validateData(Request $request): array
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
        ]);

        $selected = (string) ($data['model'] ?? '');
        if ($selected === '__custom__') {
            $selected = trim((string) ($data['custom_model'] ?? ''));
            if ($selected === '') {
                throw ValidationException::withMessages(['custom_model' => 'Enter the custom model ID.']);
            }
        } elseif ($selected !== '' && !empty($data['model_connection_id'])) {
            $connection = ModelConnection::find((int) $data['model_connection_id']);
            if ($connection && !in_array($selected, $this->providers->get($connection->provider)->models(), true)) {
                throw ValidationException::withMessages(['model' => 'Choose a model from the selected provider or select Custom model ID.']);
            }
        }

        $data['model'] = $selected !== '' ? $selected : null;
        unset($data['custom_model'], $data['avatar']);
        return $data;
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
