<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::with(['license.plan'])
            ->withCount('users')
            ->latest()
            ->paginate(15);

        return view('admin.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['license.plan', 'license.distributor', 'users']);

        return view('admin.tenants.show', compact('tenant'));
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->suspend();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant suspended successfully.');
    }

    public function unsuspend(Tenant $tenant): RedirectResponse
    {
        $tenant->unsuspend();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant unsuspended successfully.');
    }
}
