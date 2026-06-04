<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('localdt', function (string $expression) {
            return "<?php echo e(\App\Support\TenantTime::format({$expression})); ?>";
        });

        // Throttle the public AI chat endpoint per client IP and per tenant slug.
        RateLimiter::for('ai-chat', function (Request $request) {
            $slug = (string) ($request->route('slug') ?? $request->ip());

            return [
                Limit::perMinute(20)->by($request->ip()),
                Limit::perMinute(60)->by('tenant:'.$slug),
            ];
        });
    }
}
