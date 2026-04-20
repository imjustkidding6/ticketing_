<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\AgentPerformanceService;
use App\Services\TenantRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    public function __construct(
        private TenantRoleService $roleService,
        private AgentPerformanceService $performanceService,
    ) {}

    /**
     * Display a listing of tenant users.
     */
    public function index(Request $request): View
    {
        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        $users = $tenant->users()
            ->with(['roles', 'departments'])
            ->withCount([
                'createdTickets' => fn ($q) => $q->where('tickets.is_merged', false),
                'tickets as assigned_tickets_count' => fn ($q) => $q->where('tickets.is_merged', false),
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role, fn ($query, $role) => $query->role($role))
            ->when($request->department_id, fn ($query, $id) => $query->whereHas('departments', fn ($q) => $q->where('departments.id', $id)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $departments = Department::where('tenant_id', $tenant->id)->where('is_active', true)->ordered()->get();

        $totalSeats = $tenant->license?->seats ?? 0;
        $usedSeats = $tenant->users()->count();

        return view('members.index', compact('users', 'tenant', 'roles', 'departments', 'totalSeats', 'usedSeats'));
    }

    /**
     * Show the form for adding a new user.
     */
    public function create(): View
    {
        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        $canAdd = $tenant->canAddUsers();
        $availableSlots = $tenant->availableUserSlots();
        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $departments = Department::where('tenant_id', $tenant->id)->where('is_active', true)->ordered()->get();

        return view('members.create', compact('tenant', 'canAdd', 'availableSlots', 'roles', 'departments'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->checkPermission('manage users');

        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        if (! $tenant->canAddUsers()) {
            return redirect()->route('members.index')
                ->with('error', 'No available seats. Please upgrade your plan to add more members.');
        }

        $tenantRoles = Role::where('tenant_id', $tenant->id)->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($tenantRoles)],
            'support_tier' => ['nullable', 'integer', 'in:1,2,3'],
            'is_available' => ['nullable', 'boolean'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ], [
            'email.email' => 'Please enter a valid email address.',
            'role.in' => 'Please select a valid role.',
        ]);

        DB::transaction(function () use ($validated, $tenant) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Support agent fields for any non-admin role
            if ($validated['role'] !== 'admin') {
                $tierMap = [1 => 'tier_1', 2 => 'tier_2', 3 => 'tier_3'];
                $user->update([
                    'support_tier' => isset($validated['support_tier']) ? ($tierMap[$validated['support_tier']] ?? null) : null,
                    'is_available' => $validated['is_available'] ?? true,
                ]);
            }

            // Sync departments
            if (! empty($validated['department_ids'])) {
                $user->departments()->sync($validated['department_ids']);
            }

            // Add user to tenant
            $tenant->addUser($user, $validated['role'] === 'admin' ? 'admin' : 'member');

            // Assign Spatie role
            $this->roleService->syncRole($user, $validated['role'], $tenant);
        });

        return redirect()->route('members.index')
            ->with('success', "{$validated['name']} has been added successfully.");
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $member): View
    {
        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        abort_unless($tenant->hasUser($member), 404);

        $member->load(['roles', 'departments']);

        $stats = [
            'created' => $member->createdTickets()->notMerged()->count(),
            'assigned' => $member->tickets()->notMerged()->count(),
            'closed' => $member->tickets()->notMerged()->where('status', 'closed')->count(),
        ];

        $recentCreated = $member->createdTickets()
            ->notMerged()
            ->with('client')
            ->latest()
            ->limit(5)
            ->get();

        $assignedTickets = $member->tickets()
            ->notMerged()
            ->with(['client', 'category', 'creator'])
            ->latest()
            ->limit(10)
            ->get();

        $pivotRole = $member->pivot?->role ?? $member->roleInTenant($tenant);

        // Performance metrics
        $perfFrom = $request->input('perf_from', now()->subDays(30)->toDateString());
        $perfTo = $request->input('perf_to', now()->toDateString());
        $performance = $this->performanceService->getAgentPerformanceReport($member, $perfFrom, $perfTo);

        return view('members.show', compact('member', 'tenant', 'stats', 'recentCreated', 'assignedTickets', 'pivotRole', 'performance', 'perfFrom', 'perfTo'));
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $member): View
    {
        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        abort_unless($tenant->hasUser($member), 404);

        $member->load(['roles', 'departments']);

        $roles = Role::where('tenant_id', $tenant->id)->pluck('name');
        $departments = Department::where('tenant_id', $tenant->id)->where('is_active', true)->ordered()->get();
        $currentRole = $member->roles->first()?->name;
        $pivotRole = $member->roleInTenant($tenant);

        return view('members.edit', compact('member', 'tenant', 'roles', 'departments', 'currentRole', 'pivotRole'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $member): RedirectResponse
    {
        $this->checkPermission('manage users');

        $tenant = Auth::user()->currentTenant();
        $this->roleService->setTenantContext($tenant);

        abort_unless($tenant->hasUser($member), 404);

        $pivotRole = $member->roleInTenant($tenant);
        if ($pivotRole === 'owner' && $member->id !== Auth::id()) {
            return back()->with('error', 'Owner cannot be modified.');
        }

        $tenantRoles = Role::where('tenant_id', $tenant->id)->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')->ignore($member->id)->whereNull('deleted_at')],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($tenantRoles)],
            'support_tier' => ['nullable', 'integer', 'in:1,2,3'],
            'is_available' => ['nullable', 'boolean'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['exists:departments,id'],
        ]);

        DB::transaction(function () use ($validated, $member, $tenant) {
            $member->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'])) {
                $member->update(['password' => Hash::make($validated['password'])]);
            }

            // Support agent fields for any non-admin role
            if ($validated['role'] !== 'admin') {
                $tierMap = [1 => 'tier_1', 2 => 'tier_2', 3 => 'tier_3'];
                $member->update([
                    'support_tier' => isset($validated['support_tier']) ? ($tierMap[$validated['support_tier']] ?? null) : null,
                    'is_available' => $validated['is_available'] ?? true,
                ]);
            } else {
                $member->update([
                    'support_tier' => null,
                    'is_available' => true,
                ]);
            }

            // Sync departments
            $member->departments()->sync($validated['department_ids'] ?? []);

            // Update pivot role
            $pivotRole = $validated['role'] === 'admin' ? 'admin' : 'member';
            $tenant->users()->updateExistingPivot($member->id, ['role' => $pivotRole]);

            // Sync Spatie role
            $this->roleService->syncRole($member, $validated['role'], $tenant);
        });

        return redirect()->route('members.index')
            ->with('success', "{$member->name} has been updated successfully.");
    }

    /**
     * Remove a user from the tenant.
     */
    public function destroy(User $member): RedirectResponse
    {
        $this->checkPermission('manage users');

        $tenant = Auth::user()->currentTenant();

        abort_unless($tenant->hasUser($member), 404);

        if ($member->id === Auth::id()) {
            return back()->with('error', 'You cannot remove yourself.');
        }

        if ($member->roleInTenant($tenant) === 'owner') {
            return back()->with('error', 'The owner cannot be removed.');
        }

        $tenant->removeUser($member);
        $member->delete();

        return redirect()->route('members.index')
            ->with('success', "{$member->name} has been deleted.");
    }

    /**
     * Toggle user active/inactive status (soft delete).
     */
    public function toggleStatus(User $member): RedirectResponse
    {
        $tenant = Auth::user()->currentTenant();

        abort_unless($tenant->hasUser($member), 404);

        if ($member->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        if ($member->roleInTenant($tenant) === 'owner') {
            return back()->with('error', 'The owner cannot be deactivated.');
        }

        if ($member->trashed()) {
            $member->restore();
            $message = "{$member->name} has been activated.";
        } else {
            $member->delete();
            $message = "{$member->name} has been deactivated.";
        }

        return back()->with('success', $message);
    }
}
