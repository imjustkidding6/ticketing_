<?php

namespace App\Providers;

use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Ticket;
use App\Observers\KbArticleObserver;
use App\Observers\LearnedSnippetObserver;
use App\Observers\TicketObserver;
use App\Services\AiUsageTrackerService;
use App\Services\OpenAiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OpenAiService::class);

        // A singleton because AiUsageTrackerService::withContext() holds the tenant,
        // user and surface of the call in progress, and OpenAiService::post() resolves
        // the tracker out of the container on its own. A fresh instance per resolution
        // would hand post() an empty context and the attribution would silently be null
        // again — which is the gap withContext() exists to close.
        $this->app->singleton(AiUsageTrackerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Two directives, not one that guesses: @localdt is the workspace's zone,
        // @admindt is platform staff's. A single directive resolving "which
        // surface am I on?" at render time would be the ambient-state bug these
        // two classes exist to remove, and a grep for TenantTime cannot see
        // through a directive — so the split has to be visible in the template.
        Blade::directive('localdt', function (string $expression) {
            return "<?php echo e(\App\Support\TenantTime::format({$expression})); ?>";
        });

        Blade::directive('admindt', function (string $expression) {
            return "<?php echo e(\App\Support\AdminTime::format({$expression})); ?>";
        });

        KbArticle::observe(KbArticleObserver::class);
        Ticket::observe(TicketObserver::class);
        LearnedSnippet::observe(LearnedSnippetObserver::class);

        // Throttle the public AI chat endpoint per client IP and per tenant slug.
        RateLimiter::for('ai-chat', function (Request $request) {
            $slug = (string) ($request->route('slug') ?? $request->ip());

            return [
                Limit::perMinute(20)->by($request->ip()),
                Limit::perMinute(60)->by('tenant:'.$slug),
            ];
        });

        // Portal AI RateLimiter: 20 requests/minute
        RateLimiter::for('portal-ai', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip().'|'.($request->input('session_token') ?? 'guest'));
        });

        // Agent AI RateLimiter: 60 requests/minute for staff
        RateLimiter::for('agent-ai', function (Request $request) {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        // Admin AI RateLimiter: unlimited
        RateLimiter::for('admin-ai', function (Request $request) {
            return Limit::none();
        });

        // Task 4: Development Environment Database Safeguard
        if (app()->environment('local')) {
            Event::listen(CommandStarting::class, function (CommandStarting $event) {
                $destructiveCommands = ['migrate:fresh', 'db:wipe', 'migrate:refresh', 'migrate:reset'];

                if (in_array($event->command, $destructiveCommands, true)) {
                    $hasForce = $event->input->hasOption('force') && $event->input->getOption('force');
                    if (! $hasForce) {
                        $event->output->writeln('<error>===================================================================</error>');
                        $event->output->writeln('<error> LOCAL SAFEGUARD BLOCKED DESTRUCTIVE COMMAND: '.$event->command.' </error>');
                        $event->output->writeln('<comment> Accidental database wipe blocked in local development environment. </comment>');
                        $event->output->writeln('<comment> Pass --force if you intentionally intend to execute this command. </comment>');
                        $event->output->writeln('<error>===================================================================</error>');
                        exit(1);
                    }
                }
            });
        }
    }
}
