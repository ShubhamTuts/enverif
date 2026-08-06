<?php

namespace App\Http\Controllers;

use App\Core\Skills\SkillCreator;
use App\Core\Skills\SkillInstaller;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SkillController extends Controller
{
    private const CAPABILITIES = ['read', 'internal_write', 'external_write', 'network', 'secrets', 'destructive'];

    public function index(Request $request)
    {
        $query = Skill::where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', session('workspace_id')));
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%' . $request->input('q') . '%')
                ->orWhere('description', 'like', '%' . $request->input('q') . '%'));
        }
        return view('skills.index', ['skills' => $query->orderByDesc('built_in')->orderBy('name')->paginate(24)->withQueryString()]);
    }

    public function create()
    {
        return view('skills.form', ['skill' => new Skill]);
    }

    public function store(Request $request, SkillCreator $creator)
    {
        $creator->create((int) session('workspace_id'), $this->data($request));
        return redirect()->route('skills.index')->with('status', __('ui.saved'));
    }

    public function edit(Skill $skill)
    {
        $this->guard($skill);
        abort_if($skill->built_in, 403);
        return view('skills.form', ['skill' => $skill]);
    }

    public function update(Request $request, Skill $skill, SkillCreator $creator)
    {
        $this->guard($skill);
        abort_if($skill->built_in, 403);
        $creator->update($skill, (int) session('workspace_id'), $this->data($request));
        return redirect()->route('skills.index')->with('status', __('ui.saved'));
    }

    public function install(Request $request, SkillInstaller $installer)
    {
        $data = $request->validate(['source_url' => 'required|url|max:1000', 'ref' => 'nullable|max:120']);
        try {
            $results = $installer->install((int) session('workspace_id'), $data['source_url'], $data['ref'] ?: 'main');
            return back()->with('status', __('ui.skills_installed', ['count' => count($results)]));
        } catch (\Throwable $e) {
            return back()->withErrors(['source_url' => $e->getMessage()])->withInput();
        }
    }

    public function toggle(Skill $skill)
    {
        $this->guard($skill);
        abort_if($skill->built_in, 403);
        $skill->update(['status' => $skill->status === 'active' ? 'disabled' : 'active']);
        return back()->with('status', __('ui.saved'));
    }

    public function destroy(Skill $skill)
    {
        $this->guard($skill);
        abort_if($skill->built_in, 403);
        $skill->delete();
        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|max:80',
            'description' => 'nullable|max:300',
            'version' => ['required', 'max:30', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/'],
            'body' => 'required|string|max:50000',
            'capabilities' => 'array',
            'capabilities.*' => 'string|in:' . implode(',', self::CAPABILITIES),
        ]);
        $slug = \Illuminate\Support\Str::slug((string) $data['name']);
        if ($slug === '') {
            throw ValidationException::withMessages(['name' => __('ui.valid_skill_name')]);
        }
        $duplicate = Skill::where('workspace_id', session('workspace_id'))->where('slug', $slug);
        if ($request->route('skill')) {
            $duplicate->where('id', '!=', $request->route('skill')->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['name' => __('ui.skill_name_exists')]);
        }
        return $data;
    }

    private function guard(Skill $skill): void
    {
        abort_unless($skill->workspace_id === null || (int) $skill->workspace_id === (int) session('workspace_id'), 404);
    }
}
