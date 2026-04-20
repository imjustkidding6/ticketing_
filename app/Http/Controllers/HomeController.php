<?php

namespace App\Http\Controllers;

use App\Services\TenantUrlHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $tenant = $user->ensureCurrentTenant();

        if (! $tenant) {
            return redirect()->route('dashboard.no-tenant');
        }

        return redirect()->to(
            app(TenantUrlHelper::class)->tenantUrl($tenant, '/dashboard')
        );
    }
}
