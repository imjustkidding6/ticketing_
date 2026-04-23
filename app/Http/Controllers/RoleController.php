<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const DEFAULT_ROLES = ['admin', 'manager', 'agent'];

    public function index(): View
    {
        $this->checkPermission('manage roles');

        $roles = Role::where('tenant_id', session('current_tenant_id'))
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->checkPermission('manage roles');

        $permissions = Permission::orderBy('name')->get();

        return view('roles.create', compact('permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkPermission('manage roles');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => session('current_tenant_id'),
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions(Permission::whereIn('id', $validated['permissions'])->get());
        }

        ActivityLogger::log($role, 'created', "Role '{$role->name}' created.");

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $this->checkPermission('manage roles');

        $permissions = Permission::orderBy('name')->get();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->checkPermission('manage roles');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $oldName = $role->name;
        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

        $role->update(['name' => $validated['name']]);

        $permissions = ! empty($validated['permissions'])
            ? Permission::whereIn('id', $validated['permissions'])->get()
            : [];

        $role->syncPermissions($permissions);

        $newPermissions = $role->fresh()->permissions->pluck('name')->sort()->values()->all();

        ActivityLogger::log($role, 'updated', "Role '{$role->name}' updated.", [
            'name' => ['old' => $oldName, 'new' => $role->name],
            'permissions' => ['old' => $oldPermissions, 'new' => $newPermissions],
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->checkPermission('manage roles');

        if (in_array($role->name, self::DEFAULT_ROLES)) {
            return redirect()->route('roles.index')
                ->with('error', 'Default roles cannot be deleted.');
        }

        $roleName = $role->name;
        ActivityLogger::log($role, 'deleted', "Role '{$roleName}' deleted.");

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted.');
    }
}
