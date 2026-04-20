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
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    /**
     * Default weekly schedule applied when a user has no saved rows.
     * Mon-Fri 09:00-17:00 available, weekend unavailable.
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    private function defaultWeek(): array
    {
        return [
            0 => ['start' => '09:00', 'end' => '17:00', 'available' => false],
            1 => ['start' => '09:00', 'end' => '17:00', 'available' => true],
            2 => ['start' => '09:00', 'end' => '17:00', 'available' => true],
            3 => ['start' => '09:00', 'end' => '17:00', 'available' => true],
            4 => ['start' => '09:00', 'end' => '17:00', 'available' => true],
            5 => ['start' => '09:00', 'end' => '17:00', 'available' => true],
            6 => ['start' => '09:00', 'end' => '17:00', 'available' => false],
        ];
    }

    /**
     * Display schedule for the current user (or another user for admins/managers).
     */
    public function index(Request $request): View
    {
        $target = $this->resolveTargetUser($request);

        $rows = AgentSchedule::where('user_id', $target->id)
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        $week = $this->defaultWeek();
        foreach ($week as $day => $defaults) {
            $existing = $rows->get($day);
            if ($existing) {
                $week[$day] = [
                    'start' => substr($existing->start_time, 0, 5),
                    'end' => substr($existing->end_time, 0, 5),
                    'available' => (bool) $existing->is_available,
                ];
            }
        }

        $manageableAgents = $this->canManageOthers()
            ? User::query()
                ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('schedules.index', [
            'target' => $target,
            'week' => $week,
            'days' => self::DAYS,
            'manageableAgents' => $manageableAgents,
            'canManageOthers' => $this->canManageOthers(),
        ]);
    }

    /**
     * Display team-wide schedule overview.
     */
    public function team(): View
    {
        $agents = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->whereHas('schedules')
            ->with(['schedules' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time')])
            ->orderBy('name')
            ->get();

        return view('schedules.team', compact('agents'));
    }

    /**
     * Upsert all 7 days of the week for the target user.
     */
    public function save(Request $request): RedirectResponse
    {
        $target = $this->resolveTargetUser($request);

        $rules = [];
        foreach (array_keys(self::DAYS) as $day) {
            $rules["week.{$day}.start"] = ['required', 'date_format:H:i'];
            $rules["week.{$day}.end"] = ['required', 'date_format:H:i', 'after:week.'.$day.'.start'];
        }
        $validated = $request->validate($rules);

        foreach (array_keys(self::DAYS) as $day) {
            AgentSchedule::updateOrCreate(
                ['user_id' => $target->id, 'day_of_week' => $day],
                [
                    'start_time' => $validated['week'][$day]['start'],
                    'end_time' => $validated['week'][$day]['end'],
                    'is_available' => $request->boolean("week.{$day}.available"),
                ]
            );
        }

        return redirect()->route('schedules.index', $target->id !== Auth::id() ? ['user_id' => $target->id] : [])
            ->with('success', $target->id === Auth::id()
                ? 'Your schedule has been updated.'
                : "Schedule for {$target->name} has been updated.");
    }

    /**
     * Resolve which user's schedule to view/edit.
     * Non-admins/managers can only touch their own; admins/managers can pass ?user_id=X.
     */
    private function resolveTargetUser(Request $request): User
    {
        $requestedId = $request->input('user_id');

        if (! $requestedId || (int) $requestedId === Auth::id()) {
            return Auth::user();
        }

        abort_unless($this->canManageOthers(), 403);

        return User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenant_id', session('current_tenant_id')))
            ->findOrFail($requestedId);
    }

    private function canManageOthers(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->isAdmin() || $user->can('manage schedules');
    }
}
