<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
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

        $license->update($validated);

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License updated successfully.');
    }

    public function revoke(License $license): RedirectResponse
    {
        $license->revoke();

        if ($license->tenant_id) {
            \App\Services\ActivityLogger::log(
                $license,
                'license_revoked',
                "License {$license->license_key} revoked."
            );
        }

        return redirect()->route('admin.licenses.index')
            ->with('success', 'License revoked successfully.');
    }

    public function destroy(License $license): RedirectResponse
    {
        abort_if(
            $license->status !== License::STATUS_PENDING || $license->tenant_id !== null,
            422,
            'Only pending licenses that have not been activated can be deleted.'
        );

        $key = $license->license_key;
        $license->delete();

        return redirect()->route('admin.licenses.index')
            ->with('success', "License {$key} deleted.");
    }

    public function reactivate(Request $request, License $license): RedirectResponse
    {
        abort_if(
            $license->status === License::STATUS_PENDING,
            422,
            'Pending licenses must be activated by a tenant — they cannot be reactivated.'
        );

        $validated = $request->validate([
            'expires_at' => ['required', 'date', 'after:today'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'note'       => ['nullable', 'string', 'max:500'],
        ]);

        $previousExpiry = $license->expires_at?->toDateString();
        $previousStatus = $license->status;

        $license->reactivate(\Carbon\Carbon::parse($validated['expires_at']));

        if (! empty($validated['grace_days'])) {
            $license->update(['grace_days' => $validated['grace_days']]);
        }

        if ($license->tenant_id) {
            \App\Services\ActivityLogger::log(
                $license,
                'license_reactivated',
                sprintf(
                    'License %s reactivated (was %s, expired %s). New expiry: %s.%s',
                    $license->license_key,
                    $previousStatus,
                    $previousExpiry ?? 'unknown',
                    $license->expires_at->toDateString(),
                    ! empty($validated['note']) ? ' Note: ' . $validated['note'] : ''
                ),
                [
                    'previous_status'      => $previousStatus,
                    'previous_expires_at'  => $previousExpiry,
                    'new_expires_at'       => $license->expires_at->toDateString(),
                ]
            );
        }

        return redirect()->route('admin.licenses.show', $license)
            ->with('success', 'License reactivated. Expires '.$license->expires_at->format('F j, Y').'.');
    }
}
