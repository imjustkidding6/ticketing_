<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\KbPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client Portal Routes
|--------------------------------------------------------------------------
|
| Routes for the client-facing portal. Each tenant has their own portal
| accessible via /portal/{tenant-slug}/...
|
*/

Route::prefix('portal/{tenant:slug}')->name('portal.')->group(function () {
    // Public routes (no auth)
    Route::get('/', [ClientPortalController::class, 'index'])->name('index');

    // Knowledge Base (public, feature-gated at controller level)
    Route::get('knowledge-base', [KbPortalController::class, 'index'])->name('knowledge-base.index');
    Route::get('knowledge-base/search', [KbPortalController::class, 'search'])->name('knowledge-base.search');
    Route::get('knowledge-base/{categorySlug}', [KbPortalController::class, 'category'])->name('knowledge-base.category');
    Route::get('knowledge-base/{categorySlug}/{articleSlug}', [KbPortalController::class, 'article'])->name('knowledge-base.article');

    Route::get('login', [ClientPortalController::class, 'showLogin'])->name('login');
    Route::post('login', [ClientPortalController::class, 'login']);
    Route::get('register', [ClientPortalController::class, 'showRegister'])->name('register');
    Route::post('register', [ClientPortalController::class, 'register']);

    // Authenticated client routes
    Route::middleware(['auth', 'portal'])->group(function () {
        Route::get('dashboard', [ClientPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('tickets/create', [ClientPortalController::class, 'createTicket'])->name('tickets.create');
        Route::post('tickets', [ClientPortalController::class, 'storeTicket'])->name('tickets.store');
        Route::get('tickets/{ticket}', [ClientPortalController::class, 'showTicket'])->name('tickets.show');
        Route::post('logout', [ClientPortalController::class, 'logout'])->name('logout');
    });
});
