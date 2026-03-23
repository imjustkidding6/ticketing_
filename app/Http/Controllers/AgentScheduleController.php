<?php

namespace App\Http\Controllers;

use App\Models\AgentSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AgentScheduleController extends Controller
{
    /**
     * Display the current user's schedule.
     */
    public function index(): View
    {
        $schedules = AgentSchedule::where('user_id', Auth::id())
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('schedules.index', compact('schedules'));
    }

    /**
     * Display the team schedule overview.
     */
    public function team(): View
    {
        $agents = User::query()
            ->whereHas('schedules')
            ->with(['schedules' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time')])
            ->orderBy('name')
            ->get();

        return view('schedules.team', compact('agents'));
    }

    /**
     * Store a new schedule entry.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['user_id'] = Auth::id();
        $validated['is_available'] = $request->has('is_available');

        AgentSchedule::create($validated);

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule entry added.');
    }

    /**
     * Update a schedule entry.
     */
    public function update(Request $request, AgentSchedule $schedule): RedirectResponse
    {
        $validated = $request->validate([
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'is_available' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['is_available'] = $request->has('is_available');

        $schedule->update($validated);

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule entry updated.');
    }

    /**
     * Delete a schedule entry.
     */
    public function destroy(AgentSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule entry removed.');
    }
}
