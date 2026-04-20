<?php

namespace App\Http\Controllers;

use App\Models\SlaPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SlaPolicyController extends Controller
{
    /**
     * Display SLA policies grouped by client tier.
     */
    public function index(): View
    {
        $tenantId = session('current_tenant_id');
        $policies = SlaPolicy::query()->orderBy('priority')->get();

        $grouped = [];
        foreach (SlaPolicy::TIERS as $tier) {
            $grouped[$tier] = [];
            foreach (SlaPolicy::PRIORITIES as $priority) {
                $grouped[$tier][$priority] = $policies->first(
                    fn (SlaPolicy $p) => $p->client_tier === $tier && $p->priority === $priority
                );
            }
        }

        return view('sla.index', [
            'grouped' => $grouped,
            'tiers' => SlaPolicy::TIERS,
            'priorities' => SlaPolicy::PRIORITIES,
            'hasAny' => $policies->isNotEmpty(),
        ]);
    }

    /**
     * Create/Update all 4 priority rows for a single tier.
     */
    public function editTier(string $tier): View
    {
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $policies = SlaPolicy::query()
            ->where('client_tier', $tier)
            ->get()
            ->keyBy('priority');

        // Fill missing priorities with standard defaults as placeholders (not saved yet)
        $defaults = SlaPolicy::STANDARD_DEFAULTS[$tier] ?? [];
        $rows = [];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $existing = $policies->get($priority);
            $rows[$priority] = [
                'response' => $existing?->response_time_hours ?? $defaults[$priority][0] ?? null,
                'resolution' => $existing?->resolution_time_hours ?? $defaults[$priority][1] ?? null,
                'is_active' => $existing ? $existing->is_active : true,
            ];
        }

        return view('sla.edit-tier', compact('tier', 'rows'));
    }

    /**
     * Upsert all 4 priority rows for a tier.
     */
    public function updateTier(Request $request, string $tier): RedirectResponse
    {
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        $rules = [];
        foreach (SlaPolicy::PRIORITIES as $priority) {
            $rules["rows.{$priority}.response"] = ['required', 'integer', 'min:1'];
            $rules["rows.{$priority}.resolution"] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $tenantId = session('current_tenant_id');

        foreach (SlaPolicy::PRIORITIES as $priority) {
            $response = (int) $validated['rows'][$priority]['response'];
            $resolution = (int) $validated['rows'][$priority]['resolution'];
            $active = $request->boolean("rows.{$priority}.is_active", true);

            SlaPolicy::withoutGlobalScopes()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'client_tier' => $tier,
                    'priority' => $priority,
                ],
                [
                    'name' => ucfirst($tier).' - '.ucfirst($priority),
                    'response_time_hours' => $response,
                    'resolution_time_hours' => $resolution,
                    'is_active' => $active,
                ]
            );
        }

        return redirect()->route('sla.index')->with('success', ucfirst($tier).' SLA policies saved.');
    }

    /**
     * Seed industry-standard defaults for any missing (tier, priority) pairs.
     */
    public function seedDefaults(): RedirectResponse
    {
        $tenantId = session('current_tenant_id');
        $count = SlaPolicy::seedStandardDefaults($tenantId);

        return redirect()->route('sla.index')
            ->with('success', $count === 0
                ? 'Standard policies already in place.'
                : "Seeded {$count} standard SLA policies.");
    }

    /**
     * Delete all policies for a tier (safety net for cleanup).
     */
    public function destroyTier(string $tier): RedirectResponse
    {
        abort_unless(in_array($tier, SlaPolicy::TIERS, true), 404);

        SlaPolicy::where('client_tier', $tier)->delete();

        return redirect()->route('sla.index')
            ->with('success', ucfirst($tier).' SLA policies removed.');
    }
}
