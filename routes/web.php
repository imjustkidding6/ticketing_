<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BugReportController as AdminBugReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DistributorController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SystemAnnouncementController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\TenantFeedbackController as AdminTenantFeedbackController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', HomeController::class);

Route::get('/health', HealthCheckController::class)->name('health');

// Inbound GitHub webhooks for the AI Programmer loop (signature-authenticated).
Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])->name('webhooks.github');

Route::get('/register/check-slug', function (Request $request) {
    $slug = Str::slug($request->query('slug', ''));
    $reserved = ['admin', 'www', 'mail', 'api', 'portal', 'app', 'support', 'help', 'status', 'login', 'register', 'profile', 'up', 'logout'];
    $available = $slug
        && strlen($slug) >= 3
        && ! in_array($slug, $reserved)
        && ! Tenant::where('slug', $slug)->exists();

    return response()->json(['available' => $available]);
})->middleware('guest')->name('register.check-slug');

Route::get('/no-tenant', function () {
    return view('tenant.no-tenant');
})->middleware(['auth'])->name('dashboard.no-tenant');

Route::get('/{slug}/license-expired', function (string $slug) {
    $tenant = Tenant::where('slug', $slug)->firstOrFail();
    abort_unless(auth()->user()?->belongsToTenant($tenant), 403);

    return view('tenant.license-expired', [
        'tenant' => $tenant,
        'license' => $tenant->license,
    ]);
})->where('slug', '[a-z0-9][a-z0-9\-]*[a-z0-9]')
    ->middleware(['auth'])
    ->name('license.expired');

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
    Route::resource('licenses', LicenseController::class);
    Route::post('licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('licenses.revoke');
    Route::post('licenses/{license}/reactivate', [LicenseController::class, 'reactivate'])->name('licenses.reactivate');

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

    // System Announcements (broadcast to every user in every tenant)
    Route::get('announcements', [SystemAnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('announcements/create', [SystemAnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('announcements', [SystemAnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [SystemAnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // System-wide Admin Settings
    Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    // Tenant Feedback
    Route::get('feedback', [AdminTenantFeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/{feedback}', [AdminTenantFeedbackController::class, 'show'])->name('feedback.show');
    Route::patch('feedback/{feedback}', [AdminTenantFeedbackController::class, 'update'])->name('feedback.update');
    Route::delete('feedback/{feedback}', [AdminTenantFeedbackController::class, 'destroy'])->name('feedback.destroy');

    // AI Bug Reports → AI Programmer
    Route::get('bugs', [AdminBugReportController::class, 'index'])->name('bugs.index');
    Route::get('bugs/{bug}', [AdminBugReportController::class, 'show'])->name('bugs.show');
    Route::post('bugs/{bug}/fix', [AdminBugReportController::class, 'fix'])->name('bugs.fix');
    Route::post('bugs/{bug}/status', [AdminBugReportController::class, 'updateStatus'])->name('bugs.status');
});

require __DIR__.'/auth.php';
