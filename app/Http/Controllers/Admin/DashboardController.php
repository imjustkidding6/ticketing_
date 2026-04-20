<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->whereNull('suspended_at')->count(),
            'suspended_tenants' => Tenant::whereNotNull('suspended_at')->count(),
            'licenses' => License::count(),
            'active_licenses' => License::active()->count(),
            'pending_licenses' => License::pending()->count(),
            'distributors' => Distributor::count(),
            'plans' => Plan::count(),
            'total_tickets' => Ticket::withoutGlobalScopes()->count(),
            'tickets_this_month' => Ticket::withoutGlobalScopes()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $expiringLicenses = License::active()
            ->with(['plan', 'tenant'])
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->limit(10)
            ->get();

        $expiredLicenses = License::active()
            ->with(['plan', 'tenant'])
            ->where('expires_at', '<', now())
            ->orderByDesc('expires_at')
            ->limit(5)
            ->get();

        $planDistribution = Plan::withCount(['licenses' => function ($query) {
            $query->active()->whereNotNull('tenant_id');
        }])
            ->get()
            ->map(fn (Plan $plan) => [
                'name' => $plan->name,
                'count' => $plan->licenses_count,
            ]);

        $topTenants = Tenant::with(['license.plan'])
            ->withCount('users')
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->orderByDesc('users_count')
            ->limit(5)
            ->get()
            ->map(function (Tenant $tenant) {
                $tenant->ticket_count = Ticket::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->count();

                return $tenant;
            });

        $recentTenants = Tenant::with(['license.plan'])
            ->withCount('users')
            ->latest()
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'expiringLicenses',
            'expiredLicenses',
            'planDistribution',
            'topTenants',
            'recentTenants',
        ));
    }
}
