<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AgentScheduleController;
use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\CannedResponseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EscalationController;
use App\Http\Controllers\KbArticleController;
use App\Http\Controllers\KbCategoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServiceReportController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketTaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are loaded under the /{slug}/ path prefix.
| The {slug} parameter is resolved by the EnsureTenantSession middleware
| and removed from route parameters before reaching controllers.
|
*/

// Public pages (no auth required)
Route::get('/', [ClientPortalController::class, 'publicLanding'])->name('tenant.landing');
Route::get('submit-ticket', [ClientPortalController::class, 'publicSubmitForm'])->name('tenant.submit-ticket');
Route::post('submit-ticket', [ClientPortalController::class, 'publicSubmitStore'])->name('tenant.submit-ticket.store');
Route::get('track-ticket', [ClientPortalController::class, 'publicTrackForm'])->name('tenant.track-ticket');
Route::get('track-ticket/{token}', [ClientPortalController::class, 'publicTrackByToken'])->name('tenant.track-ticket.token');
Route::post('track-ticket/{token}/reply', [ClientPortalController::class, 'publicReply'])->name('tenant.track-ticket.reply');

// Knowledge Base portal (public, feature-gated at controller level)
Route::get('kb', [App\Http\Controllers\KbPortalController::class, 'index'])->name('portal.knowledge-base.index');
Route::get('kb/search', [App\Http\Controllers\KbPortalController::class, 'search'])->name('portal.knowledge-base.search');
Route::get('kb/{categorySlug}', [App\Http\Controllers\KbPortalController::class, 'category'])->name('portal.knowledge-base.category');
Route::get('kb/{categorySlug}/{articleSlug}', [App\Http\Controllers\KbPortalController::class, 'article'])->name('portal.knowledge-base.article');

// Public API for cascading selects (no auth required, scoped by slug)
Route::get('api/public/categories', function (\Illuminate\Http\Request $request, string $slug) {
    $tenant = \App\Models\Tenant::where('slug', $slug)->where('is_active', true)->firstOrFail();
    $query = \App\Models\TicketCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order');
    if ($request->filled('department_id')) {
        $query->where('department_id', $request->department_id);
    }

    return $query->get(['id', 'name']);
})->name('api.public.categories');

Route::get('api/public/products', function (\Illuminate\Http\Request $request, string $slug) {
    $tenant = \App\Models\Tenant::where('slug', $slug)->where('is_active', true)->firstOrFail();
    $query = \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('sort_order');
    if ($request->filled('category_id')) {
        $query->where('category_id', $request->category_id);
    } elseif ($request->filled('department_id')) {
        $categoryIds = \App\Models\TicketCategory::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('department_id', $request->department_id)
            ->pluck('id');
        $query->whereIn('category_id', $categoryIds);
    }

    return $query->get(['id', 'name']);
})->name('api.public.products');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'tenant'])->name('dashboard');
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
    ->middleware(['auth', 'verified', 'tenant'])->name('dashboard.stats');

// Tenant-scoped routes
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::resource('departments', DepartmentController::class)->except(['show'])->middleware('feature:department_management');
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/assign-agent', [ClientController::class, 'assignAgent'])->name('clients.assign-agent');

    // User Management (all plans)
    Route::resource('members', MemberController::class);

    // Ticket lookup APIs (for cascading selects)
    Route::get('api/categories', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\TicketCategory::active()->ordered();
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return $query->get(['id', 'name']);
    })->name('api.categories');

    Route::get('api/products', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\Product::active()->ordered();
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        } elseif ($request->filled('department_id')) {
            $categoryIds = \App\Models\TicketCategory::active()
                ->where('department_id', $request->department_id)
                ->pluck('id');
            $query->whereIn('category_id', $categoryIds);
        }

        return $query->get(['id', 'name']);
    })->name('api.products');

    // Tickets
    Route::resource('tickets', TicketController::class);
    Route::get('tickets-search', [TicketController::class, 'search'])->name('tickets.search');
    Route::get('tickets-trashed', [TicketController::class, 'trashed'])->name('tickets.trashed');
    Route::post('tickets/{ticket}/restore', [TicketController::class, 'restore'])->name('tickets.restore')->withTrashed();
    Route::delete('tickets/{ticket}/force-delete', [TicketController::class, 'forceDestroy'])->name('tickets.force-delete')->withTrashed();
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/self-assign', [TicketController::class, 'selfAssign'])->name('tickets.self-assign');
    Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.change-status');
    Route::post('tickets/{ticket}/priority', [TicketController::class, 'changePriority'])->name('tickets.change-priority');
    Route::post('tickets/{ticket}/false-alarm', [TicketController::class, 'markFalseAlarm'])->name('tickets.false-alarm');
    Route::post('tickets/{ticket}/child', [TicketController::class, 'createChild'])->name('tickets.create-child');
    Route::post('tickets/{ticket}/billing', [TicketController::class, 'updateBilling'])->name('tickets.billing')->middleware('feature:billing');
    Route::get('tickets/{ticket}/attachments/{index}', [TicketController::class, 'downloadAttachment'])->name('tickets.attachment')->middleware('feature:attachments');
    Route::post('tickets/{ticket}/spam', [TicketController::class, 'markAsSpam'])->name('tickets.mark-spam')->middleware('feature:spam_management');
    Route::post('tickets/{ticket}/unspam', [TicketController::class, 'unmarkAsSpam'])->name('tickets.unmark-spam')->middleware('feature:spam_management');
    Route::post('tickets/{ticket}/merge', [TicketController::class, 'merge'])->name('tickets.merge')->middleware('feature:ticket_merging');
    Route::post('tickets/{ticket}/unmerge', [TicketController::class, 'unmerge'])->name('tickets.unmerge')->middleware('feature:ticket_merging');
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen')->middleware('feature:ticket_reopening');

    // Ticket Comments (Enterprise via feature gate)
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store')->middleware('feature:client_comments');
    Route::put('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'update'])->name('tickets.comments.update')->middleware('feature:client_comments');
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy'])->name('tickets.comments.destroy')->middleware('feature:client_comments');
    Route::get('tickets/{ticket}/comments/{comment}/attachment/{index}', [TicketCommentController::class, 'downloadAttachment'])->name('tickets.comments.attachment')->middleware('feature:client_comments');

    // Escalation (Enterprise via feature gate)
    Route::post('tickets/{ticket}/escalate', [EscalationController::class, 'escalate'])->name('tickets.escalate')->middleware('feature:agent_escalation');

    // Ticket Tasks
    Route::post('tickets/{ticket}/tasks', [TicketTaskController::class, 'store'])->name('tickets.tasks.store');
    Route::put('tickets/{ticket}/tasks/{task}', [TicketTaskController::class, 'update'])->name('tickets.tasks.update');
    Route::post('tickets/{ticket}/tasks/{task}/status', [TicketTaskController::class, 'updateStatus'])->name('tickets.tasks.status');
    Route::delete('tickets/{ticket}/tasks/{task}', [TicketTaskController::class, 'destroy'])->name('tickets.tasks.destroy');
    Route::post('tickets/{ticket}/tasks/bulk-update', [TicketTaskController::class, 'bulkUpdate'])->name('tickets.tasks.bulk-update');
    Route::post('tickets/{ticket}/tasks/bulk-status-update', [TicketTaskController::class, 'bulkStatusUpdate'])->name('tickets.tasks.bulk-status-update');
    Route::get('tickets/{ticket}/tasks/{task}/history', [TicketTaskController::class, 'history'])->name('tickets.tasks.history');

    // Notifications
    Route::get('notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');

    // Settings
    Route::get('settings/general', [AppSettingController::class, 'general'])->name('settings.general');
    Route::post('settings/general', [AppSettingController::class, 'saveGeneral']);
    Route::get('settings/ticket', [AppSettingController::class, 'ticket'])->name('settings.ticket');
    Route::post('settings/ticket', [AppSettingController::class, 'saveTicket']);
    Route::get('settings/notifications', [AppSettingController::class, 'notifications'])->name('settings.notifications')->middleware('feature:email_notifications');
    Route::post('settings/notifications', [AppSettingController::class, 'saveNotifications'])->middleware('feature:email_notifications');
    Route::post('settings/notifications/test', [AppSettingController::class, 'testEmail'])->name('settings.notifications.test')->middleware('feature:email_notifications');
    Route::get('settings/branding', [AppSettingController::class, 'branding'])->name('settings.branding');
    Route::post('settings/branding', [AppSettingController::class, 'saveBranding']);
    Route::get('settings/service-report', [AppSettingController::class, 'serviceReport'])->name('settings.service-report')->middleware('feature:service_reports');
    Route::post('settings/service-report', [AppSettingController::class, 'saveServiceReport'])->middleware('feature:service_reports');

    // Reports
    Route::get('reports', [ReportController::class, 'overview'])->name('reports.overview');
    Route::get('reports/departments', [ReportController::class, 'departments'])->name('reports.departments');
    Route::get('reports/categories', [ReportController::class, 'categories'])->name('reports.categories');
    Route::get('reports/clients', [ReportController::class, 'clients'])->name('reports.clients');
    Route::get('reports/agents', [ReportController::class, 'agents'])->name('reports.agents');
    Route::get('reports/products', [ReportController::class, 'products'])->name('reports.products');
    Route::get('reports/tickets', [ReportController::class, 'tickets'])->name('reports.tickets');
    Route::get('reports/export/volume', [ReportController::class, 'exportVolume'])->name('reports.export.volume')->middleware('feature:detailed_reporting');
    Route::get('reports/export/tickets', [ReportController::class, 'exportTickets'])->name('reports.export.tickets')->middleware('feature:detailed_reporting');
    Route::get('reports/export/departments', [ReportController::class, 'exportDepartmentReport'])->name('reports.export.departments')->middleware('feature:detailed_reporting');
    Route::get('reports/export/categories', [ReportController::class, 'exportCategoryReport'])->name('reports.export.categories')->middleware('feature:detailed_reporting');
    Route::get('reports/export/clients', [ReportController::class, 'exportClientReport'])->name('reports.export.clients')->middleware('feature:detailed_reporting');
    Route::get('reports/export/agents', [ReportController::class, 'exportAgents'])->name('reports.export.agents')->middleware('feature:detailed_reporting');
    Route::get('reports/export/products', [ReportController::class, 'exportProductReport'])->name('reports.export.products')->middleware('feature:detailed_reporting');
    Route::get('reports/billing', [ReportController::class, 'billing'])->name('reports.billing')->middleware('feature:billing');
    Route::get('reports/export/billing', [ReportController::class, 'exportBilling'])->name('reports.export.billing')->middleware('feature:billing');
    Route::get('reports/sla-compliance', [ReportController::class, 'slaCompliance'])->name('reports.sla-compliance')->middleware('feature:sla_report');
    Route::get('reports/reopens', [ReportController::class, 'reopens'])->name('reports.reopens')->middleware('feature:ticket_reopening');
    Route::get('reports/export/reopens', [ReportController::class, 'exportReopens'])->name('reports.export.reopens')->middleware(['feature:ticket_reopening', 'feature:detailed_reporting']);
    Route::get('reports/export/sla-compliance', [ReportController::class, 'exportSlaCompliance'])->name('reports.export.sla-compliance')->middleware(['feature:sla_report', 'feature:detailed_reporting']);

    // Activity Logs (Business+ via feature gate)
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('feature:audit_logs');

    // SLA Policies (Business+ via feature gate)
    Route::middleware('feature:sla_management')->group(function () {
        Route::get('sla', [SlaPolicyController::class, 'index'])->name('sla.index');
        Route::post('sla/seed-defaults', [SlaPolicyController::class, 'seedDefaults'])->name('sla.seed-defaults');
        Route::get('sla/tier/{tier}/edit', [SlaPolicyController::class, 'editTier'])->name('sla.edit-tier');
        Route::post('sla/tier/{tier}', [SlaPolicyController::class, 'updateTier'])->name('sla.update-tier');
        Route::delete('sla/tier/{tier}', [SlaPolicyController::class, 'destroyTier'])->name('sla.destroy-tier');
    });

    // Service Reports (Business+ via feature gate)
    Route::get('service-reports', [ServiceReportController::class, 'index'])->name('service-reports.index')->middleware('feature:service_reports');
    Route::post('tickets/{ticket}/service-report', [ServiceReportController::class, 'generate'])->name('service-reports.generate')->middleware('feature:service_reports');
    Route::get('service-reports/{report}/download', [ServiceReportController::class, 'download'])->name('service-reports.download')->middleware('feature:service_reports');

    // Agent Schedules (Business+ via feature gate)
    Route::middleware('feature:agent_schedule')->group(function () {
        Route::get('schedules', [AgentScheduleController::class, 'index'])->name('schedules.index');
        Route::get('schedules/team', [AgentScheduleController::class, 'team'])->name('schedules.team');
        Route::post('schedules', [AgentScheduleController::class, 'save'])->name('schedules.save');
    });

    // Custom Roles (Enterprise via feature gate)
    Route::resource('roles', RoleController::class)->except(['show'])->middleware('feature:custom_roles');

    // Canned Responses (Business+ via feature gate)
    Route::middleware('feature:canned_responses')->group(function () {
        Route::get('canned-responses/list', [CannedResponseController::class, 'list'])->name('canned-responses.list');
        Route::resource('canned-responses', CannedResponseController::class)->except(['show']);
    });

    // Knowledge Base (Business+ via feature gate)
    Route::middleware('feature:knowledge_base')->prefix('knowledge-base')->name('knowledge-base.')->group(function () {
        Route::resource('categories', KbCategoryController::class)->except(['show']);
        Route::resource('articles', KbArticleController::class);
        Route::get('search', [KbArticleController::class, 'search'])->name('articles.search');
    });
});
