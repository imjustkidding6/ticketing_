<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributorController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', HealthCheckController::class)->name('health');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'tenant'])->name('dashboard');

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

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('distributors', DistributorController::class);
    Route::resource('plans', PlanController::class)->except(['destroy', 'show']);
    Route::resource('licenses', LicenseController::class)->except(['destroy']);
    Route::post('licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');

    Route::get('tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/unsuspend', [AdminTenantController::class, 'unsuspend'])->name('tenants.unsuspend');
    Route::post('tenants/{tenant}/change-plan', [AdminTenantController::class, 'changePlan'])->name('tenants.change-plan');
});

require __DIR__.'/auth.php';
