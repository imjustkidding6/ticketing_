<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributorController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', HomeController::class);

Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/register/check-slug', function (\Illuminate\Http\Request $request) {
    $slug = Str::slug($request->query('slug', ''));
    $reserved = ['admin', 'www', 'mail', 'api', 'portal', 'app', 'support', 'help', 'status', 'login', 'register', 'profile', 'up', 'logout'];
    $available = $slug
        && strlen($slug) >= 3
        && ! in_array($slug, $reserved)
        && ! \App\Models\Tenant::where('slug', $slug)->exists();

    return response()->json(['available' => $available]);
})->middleware('guest')->name('register.check-slug');

Route::get('/no-tenant', function () {
    return view('tenant.no-tenant');
})->middleware(['auth'])->name('dashboard.no-tenant');

Route::middleware('auth')->group(function () {
    Route::get('/tenant/select', [TenantController::class, 'select'])->name('tenant.select');
    Route::post('/tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin auth (public)
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login']);
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::resource('distributors', DistributorController::class);
    Route::resource('plans', PlanController::class)->only(['index', 'edit', 'update']);
    Route::resource('licenses', LicenseController::class)->except(['destroy']);
    Route::post('licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');

    Route::get('tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/unsuspend', [AdminTenantController::class, 'unsuspend'])->name('tenants.unsuspend');
    Route::post('tenants/{tenant}/change-plan', [AdminTenantController::class, 'changePlan'])->name('tenants.change-plan');
    Route::post('tenants/{tenant}/update-seats', [AdminTenantController::class, 'updateSeats'])->name('tenants.update-seats');
    Route::post('tenants/{tenant}/impersonate', [AdminTenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::post('stop-impersonation', [AdminTenantController::class, 'stopImpersonation'])->name('stop-impersonation');

    // Admin User Management
    Route::resource('users', AdminUserController::class)->except(['show', 'destroy']);
    Route::post('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status')->withTrashed();
});

require __DIR__.'/auth.php';
