<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('api-token')->prefix('v1')->group(function () {
    Route::get('tickets', [TicketController::class, 'index'])->name('api.v1.tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('api.v1.tickets.store');
    Route::get('tickets/{ticketNumber}', [TicketController::class, 'show'])->name('api.v1.tickets.show');

    Route::post('clients', [ClientController::class, 'store'])->name('api.v1.clients.store');

    Route::get('departments', [LookupController::class, 'departments'])->name('api.v1.lookup.departments');
    Route::get('categories', [LookupController::class, 'categories'])->name('api.v1.lookup.categories');
});
