<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributorController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
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

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('tenants/{tenant}/unsuspend', [TenantController::class, 'unsuspend'])->name('tenants.unsuspend');
});

require __DIR__.'/auth.php';
