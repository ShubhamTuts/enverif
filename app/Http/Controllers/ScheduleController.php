<?php

namespace App\Http\Controllers;

use App\Core\Scheduling\ScheduleManager;
use App\Models\{Agent, AgentSchedule, Workflow};
use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ScheduleController
{
    public function __construct(private readonly ScheduleManager $schedules) {}

    public function index(Request $request)
    {
        $month = $request->string('month')->toString() ?: now()->format('Y-m');
        try { $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth(); }
        catch (\Throwable) { $start = now()->startOfMonth(); }
        $all = AgentSchedule::with(['agent','workflow'])->orderBy('next_run_at')->get();
        return view('schedules.index', [
            'schedules' => AgentSchedule::with(['agent','workflow'])->latest()->paginate(20),
            'calendar' => $this->calendar($start, $all),
            'month' => $start,
            'prevMonth' => $start->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function show(AgentSchedule $schedule)
    {
        $schedule->load(['agent','workflow']);
        return view('schedules.show', ['schedule' => $schedule]);
    }

    public function create() { return view('schedules.form', $this->formData(new AgentSchedule)); }

    public function store(Request $request)
    {
        $schedule = $this->persist($request);
        return redirect()->route('schedules.show', $schedule)->with('status', __('ui.saved'));
    }

    public function edit(AgentSchedule $schedule) { return view('schedules.form', $this->formData($schedule)); }

    public function update(Request $request, AgentSchedule $schedule)
    {
        $schedule = $this->persist($request, $schedule);
        return redirect()->route('schedules.show', $schedule)->with('status', __('ui.saved'));
    }

    public function destroy(AgentSchedule $schedule)
    {
        $schedule->delete();
        return redirect()->route('schedules.index')->with('status', __('ui.deleted'));
    }

    public function toggle(AgentSchedule $schedule)
    {
        $enabled = ! $schedule->enabled;
        $changes = ['enabled' => $enabled];
        if ($enabled) {
            $changes['next_run_at'] = (new CronExpression($schedule->cron_expression))
                ->getNextRunDate('now', 0, false, $schedule->timezone);
        }
        $schedule->update($changes);
        return back()->with('status', __('ui.saved'));
    }

    private function persist(Request $request, ?AgentSchedule $schedule = null): AgentSchedule
    {
        $data = $request->validate([
            'target_type' => 'required|in:agent,workflow',
            'agent_id' => 'nullable|integer',
            'workflow_id' => 'nullable|integer',
            'name' => 'required|string|max:120',
            'cron_expression' => 'required|string|max:100',
            'timezone' => 'required|string|max:64',
            'prompt' => 'required|string|max:20000',
            'enabled' => 'nullable|boolean',
        ]);
        $data['enabled'] = $request->boolean('enabled');
        try {
            return $this->schedules->upsert((int) session('workspace_id'), $data, $schedule);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['schedule' => $e->getMessage()]);
        }
    }

    private function formData(AgentSchedule $schedule): array
    {
        return [
            'schedule' => $schedule,
            'agents' => Agent::where('status','active')->orderBy('name')->get(),
            'workflows' => Workflow::where('status','active')->orderBy('name')->get(),
        ];
    }

    private function calendar(\Carbon\Carbon $month, $schedules): array
    {
        $start = $month->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
        $days = [];
        for ($i = 0; $i < 42; $i++) {
            $day = $start->copy()->addDays($i);
            $events = $schedules->filter(fn ($schedule) => $schedule->next_run_at && $schedule->next_run_at->timezone($schedule->timezone)->isSameDay($day))->values();
            $days[] = ['date' => $day, 'inMonth' => $day->month === $month->month, 'events' => $events];
        }
        return $days;
    }
}
