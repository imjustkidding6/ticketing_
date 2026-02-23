<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'tenants' => Tenant::count(),
                'licenses' => License::count(),
                'active_licenses' => License::active()->count(),
                'distributors' => Distributor::count(),
                'plans' => Plan::count(),
            ],
        ]);
    }
}
