<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TenantUrlHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $tenants = $user->tenants()
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->get();

        if ($tenants->count() === 1) {
            $tenant = $tenants->first();
            $user->setCurrentTenant($tenant);

            return redirect()->to(
                app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard')
            );
        }

        if ($tenants->count() > 1) {
            return redirect()->route('tenant.select');
        }

        return redirect()->route('dashboard.no-tenant');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
