<?php

namespace App\Http\Controllers;

use App\Core\Agents\Memory\MemoryInput;
use App\Models\Agent;
use App\Models\AgentMemory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AgentMemoryController extends Controller
{
    public function store(Request $request, Agent $agent)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:160',
            'value' => 'required|string|max:20000',
            'tags' => 'nullable|string|max:1000',
            'importance' => 'required|integer|min:0|max:100',
        ]);
        try {
            $data = MemoryInput::normalize(
                $validated['key'],
                $validated['value'],
                preg_split('/[,\n]+/', (string) ($validated['tags'] ?? '')) ?: [],
                (int) $validated['importance'],
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['key' => $e->getMessage()]);
        }
        if (MemoryInput::containsLikelySecret($data['key'] . "\n" . $data['value'])) {
            throw ValidationException::withMessages(['value' => __('ui.memory_secret_rejected')]);
        }
        AgentMemory::updateOrCreate(
            ['workspace_id' => $agent->workspace_id, 'agent_id' => $agent->id, 'key' => $data['key']],
            ['value' => $data['value'], 'tags' => $data['tags'], 'importance' => $data['importance'], 'source_run_id' => null],
        );
        return back()->with('status', __('ui.saved'));
    }

    public function destroy(Agent $agent, AgentMemory $memory)
    {
        abort_unless((int) $memory->agent_id === (int) $agent->id && (int) $memory->workspace_id === (int) $agent->workspace_id, 404);
        $memory->delete();
        return back()->with('status', __('ui.deleted'));
    }
}
