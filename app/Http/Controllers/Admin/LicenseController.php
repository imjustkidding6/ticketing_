<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(): View
    {
        $licenses = License::with(['distributor', 'plan', 'tenant'])
            ->latest()
            ->paginate(15);

        return view('admin.licenses.index', compact('licenses'));
    }

    public function create(): View
    {
        $distributors = Distributor::active()->orderBy('name')->get();
        $plans = Plan::active()->orderBy('name')->get();

        return view('admin.licenses.create', compact('distributors', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'distributor_id' => ['required', 'exists:distributors,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'seats' => ['required', 'integer', 'min:1'],
            'expires_at' => ['required', 'date', 'after:today'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        $license = License::create([
            'license_key' => License::generateKey(),
            'distributor_id' => $validated['distributor_id'],
            'plan_id' => $validated['plan_id'],
            'seats' => $validated['seats'],
            'status' => License::STATUS_PENDING,
            'issued_at' => now(),
            'expires_at' => $validated['expires_at'],
            'grace_days' => $validated['grace_days'],
        ]);

        return redirect()->route('admin.licenses.show', $license)
            ->with('success', 'License created successfully. Key: '.$license->license_key);
    }

    public function show(License $license): View
    {
        $license->load(['distributor', 'plan', 'tenant']);

        return view('admin.licenses.show', compact('license'));
    }

    public function edit(License $license): View
    {
        $plans = Plan::active()->orderBy('name')->get();

        return view('admin.licenses.edit', compact('license', 'plans'));
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'seats' => ['required', 'integer', 'min:1'],
            'expires_at' => ['required', 'date'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ]);

        $tenant = $license->tenant;

        $license->update($validated);

        if ($tenant) {
            app(PlanService::class)->clearCache($tenant);
        }

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License updated successfully.');
    }

    public function revoke(License $license): RedirectResponse
    {
        $license->loadMissing('tenant');
        $tenant = $license->tenant;

        $license->revoke();

        if ($tenant) {
            app(PlanService::class)->clearCache($tenant);
        }

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License revoked successfully.');
    }
}