<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\EnsureClientPortalAccess;
use App\Http\Middleware\EnsureTenantSession;
use App\Http\Middleware\SetTenantUrlDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->prefix('{slug}')
                ->where(['slug' => '[a-z0-9][a-z0-9\-]*[a-z0-9]'])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            SetTenantUrlDefaults::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'tenant' => EnsureTenantSession::class,
            'feature' => CheckPlanFeature::class,
            'portal' => EnsureClientPortalAccess::class,
            'api-token' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'A database error occurred. Please try again.'], 500);
            }

            return response()->view('errors.database', [], 500);
        });
    })->create();
