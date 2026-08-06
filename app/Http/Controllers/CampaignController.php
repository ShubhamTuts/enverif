<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Lead;
use Illuminate\Http\Request;

final class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::query()->withCount('leads');
        if ($request->filled('q')) $query->where('name', 'like', '%' . $request->input('q') . '%');
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        return view('campaigns.index', ['campaigns' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('campaigns.form', ['campaign' => new Campaign]);
    }

    public function store(Request $request)
    {
        $campaign = Campaign::create($this->data($request));
        return redirect()->route('campaigns.show', $campaign)->with('status', __('ui.saved'));
    }

    public function show(Campaign $campaign)
    {
        $campaign->load('steps');
        $assignedIds = $campaign->leads()->pluck('leads.id');
        return view('campaigns.show', [
            'campaign' => $campaign,
            'members' => $campaign->leads()->orderBy('company')->orderBy('first_name')->paginate(20),
            'availableLeads' => Lead::query()
                ->whereNotIn('id', $assignedIds)
                ->orderBy('company')
                ->orderBy('first_name')
                ->limit(200)
                ->get(['id', 'first_name', 'last_name', 'email', 'company']),
        ]);
    }

    public function edit(Campaign $campaign)
    {
        return view('campaigns.form', ['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->update($this->data($request));
        return redirect()->route('campaigns.show', $campaign)->with('status', __('ui.saved'));
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();
        return redirect()->route('campaigns.index')->with('status', __('ui.deleted'));
    }

    public function addStep(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'channel' => 'required|in:email,linkedin,call,research,webhook',
            'action' => 'required|max:80',
            'delay_minutes' => 'required|integer|min:0|max:525600',
            'content' => 'nullable|string|max:30000',
            'requires_approval' => 'nullable|boolean',
        ]);
        $data['position'] = (int) $campaign->steps()->max('position') + 1;
        $data['content'] = ['template' => $data['content'] ?? ''];
        $data['requires_approval'] = $request->boolean('requires_approval', true);
        $campaign->steps()->create($data);
        return back()->with('status', __('ui.saved'));
    }

    public function deleteStep(Campaign $campaign, CampaignStep $step)
    {
        abort_unless($step->campaign_id === $campaign->id, 404);
        $step->delete();
        $position = 1;
        foreach ($campaign->steps()->orderBy('position')->get() as $campaignStep) {
            $campaignStep->update(['position' => $position++]);
        }
        return back()->with('status', __('ui.deleted'));
    }

    public function addLeads(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'lead_ids' => 'required|array|min:1|max:200',
            'lead_ids.*' => 'integer',
        ]);
        $leadIds = Lead::query()->whereIn('id', array_values(array_unique($data['lead_ids'])))->pluck('id')->all();
        abort_unless(count($leadIds) === count(array_unique($data['lead_ids'])), 422);
        $payload = [];
        foreach ($leadIds as $leadId) {
            $payload[$leadId] = ['status' => 'queued', 'current_step' => 0, 'next_action_at' => null];
        }
        $campaign->leads()->syncWithoutDetaching($payload);
        return back()->with('status', __('ui.saved'));
    }

    public function removeLead(Campaign $campaign, Lead $lead)
    {
        $campaign->leads()->detach($lead->id);
        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'name' => 'required|max:140',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:draft,active,paused,completed',
            'settings' => 'nullable',
        ]);
    }
}
