<?php

use App\Http\Controllers\Admin\AdminAiCopilotController;
use App\Http\Controllers\Admin\AiAdminManagerController;
use App\Http\Controllers\Admin\AiChatbotController;
use App\Http\Controllers\Admin\AiDashboardController;
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
use App\Http\Controllers\App\AiAssistantController as AppAiAssistantController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portal\AiAssistantController as PortalAiAssistantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TutorialController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', HomeController::class);

Route::get('/health', HealthCheckController::class)->name('health');

// Portal AI Chat Routes (Public)
Route::post('/portal/ai/start', [PortalAiAssistantController::class, 'startConversation'])->name('portal.ai.start');
Route::post('/portal/ai/message', [PortalAiAssistantController::class, 'sendMessage'])->middleware('throttle:portal-ai')->name('portal.ai.message');
Route::get('/portal/ai/{conversation}', [PortalAiAssistantController::class, 'loadConversation'])->name('portal.ai.load');

// Agent AI Chat Routes (Authenticated)
Route::middleware(['auth'])->group(function () {
    Route::post('/app/ai/start', [AppAiAssistantController::class, 'startConversation'])->name('app.ai.start');
    Route::post('/app/ai/message', [AppAiAssistantController::class, 'sendMessage'])->middleware('throttle:agent-ai')->name('app.ai.message');
    Route::get('/app/ai/{conversation}', [AppAiAssistantController::class, 'history'])->name('app.ai.history');
});

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
    Route::get('tenants/{tenant}/edit', [AdminTenantController::class, 'edit'])->name('tenants.edit');
    Route::put('tenants/{tenant}', [AdminTenantController::class, 'update'])->name('tenants.update');
    Route::delete('tenants/{tenant}', [AdminTenantController::class, 'destroy'])->name('tenants.destroy');
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

    // AI Administration & Management Module
    Route::post('ai/chat', [AdminAiCopilotController::class, 'chat'])->name('ai.chat');
    Route::get('ai/chat-page', [AiChatbotController::class, 'index'])->name('ai.chat-page');
    Route::get('ai/chatbot/conversations', [AiChatbotController::class, 'getConversations'])->name('ai.chatbot.conversations');
    Route::post('ai/chatbot/conversations', [AiChatbotController::class, 'startConversation'])->name('ai.chatbot.start');
    Route::get('ai/chatbot/conversations/{conversation}', [AiChatbotController::class, 'getMessages'])->name('ai.chatbot.messages');
    Route::post('ai/chatbot/conversations/{conversation}/send', [AiChatbotController::class, 'sendMessage'])->name('ai.chatbot.send');
    Route::patch('ai/chatbot/conversations/{conversation}/rename', [AiChatbotController::class, 'renameConversation'])->name('ai.chatbot.rename');
    Route::delete('ai/chatbot/conversations/{conversation}', [AiChatbotController::class, 'deleteConversation'])->name('ai.chatbot.delete');
    Route::get('ai/chatbot/conversations/{conversation}/export/{format}', [AiChatbotController::class, 'exportConversation'])->name('ai.chatbot.export');
    Route::get('ai', [AiDashboardController::class, 'index'])->name('ai.dashboard');
    Route::get('ai/settings', [AiAdminManagerController::class, 'settings'])->name('ai.settings');
    Route::post('ai/settings', [AiAdminManagerController::class, 'updateSettings'])->name('ai.settings.update');
    Route::get('ai/prompts', [AiAdminManagerController::class, 'prompts'])->name('ai.prompts');
    Route::post('ai/prompts', [AiAdminManagerController::class, 'storePrompt'])->name('ai.prompts.store');
    Route::get('ai/conversations', [AiAdminManagerController::class, 'conversations'])->name('ai.conversations');
    Route::get('ai/conversations/export', [AiAdminManagerController::class, 'exportConversations'])->name('ai.conversations.export');
    Route::delete('ai/conversations/{id}', [AiAdminManagerController::class, 'deleteConversation'])->name('ai.conversations.delete');
    Route::get('ai/feedback', [AiAdminManagerController::class, 'feedback'])->name('ai.feedback');
    Route::post('ai/feedback', [AiAdminManagerController::class, 'storeFeedback'])->name('ai.feedback.store');
    Route::get('ai/playground', [AiAdminManagerController::class, 'playground'])->name('ai.playground');
    Route::post('ai/playground/run', [AiAdminManagerController::class, 'runPlaygroundTest'])->name('ai.playground.run');
    Route::get('ai/analytics', [AiAdminManagerController::class, 'analytics'])->name('ai.analytics');
    Route::get('ai/health', [AiAdminManagerController::class, 'health'])->name('ai.health');
    Route::get('ai/conversations/{id}/export/{format}', [AiAdminManagerController::class, 'exportSingleConversation'])->name('ai.conversations.export-single');

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

    // Admin Reports
    Route::view('reports', 'admin.reports.index')
        ->name('reports.index');

    // Admin Help & Tutorials
    Route::get('help', [TutorialController::class, 'index'])
        ->name('help.index');
    Route::get('help/download-manual', [TutorialController::class, 'downloadManual'])
        ->name('help.download-manual');
    Route::get('help/{tutorial}', [TutorialController::class, 'show'])
        ->name('help.show');

    // Admin Notifications
    Route::get('notifications', function () {
        return view('admin.notifications.index');
    })->name('notifications.index');

    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');

    // Admin SLA Policies
    Route::get('sla', [SlaPolicyController::class, 'index'])->name('sla.index');
    Route::post('sla', [SlaPolicyController::class, 'store'])->name('sla.store');
    Route::put('sla/{policy}', [SlaPolicyController::class, 'update'])->name('sla.update');
    Route::post('sla/{policy}/toggle', [SlaPolicyController::class, 'toggle'])->name('sla.toggle');
    Route::delete('sla/{policy}', [SlaPolicyController::class, 'destroy'])->name('sla.destroy');
    Route::post('sla/bulk-action', [SlaPolicyController::class, 'bulkAction'])->name('sla.bulk-action');
    Route::get('sla/export', [SlaPolicyController::class, 'export'])->name('sla.export');
    Route::post('sla/seed-defaults', [SlaPolicyController::class, 'seedDefaults'])->name('sla.seed-defaults');
    Route::get('sla/tier/{tier}/edit', [SlaPolicyController::class, 'editTier'])->name('sla.edit-tier');
    Route::post('sla/tier/{tier}', [SlaPolicyController::class, 'updateTier'])->name('sla.update-tier');
    Route::delete('sla/tier/{tier}', [SlaPolicyController::class, 'destroyTier'])->name('sla.destroy-tier');
});
require __DIR__.'/auth.php';
