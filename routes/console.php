<?php

use App\Console\Commands\AutoClosePendingTickets;
use App\Console\Commands\CheckLicenseExpirations;
use App\Console\Commands\SendSlaBreachWarnings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Both send notifications, so an overlapping run double-notifies. The SLA sweep
// runs every 15 minutes and is the likelier of the two to overrun.
Schedule::command(SendSlaBreachWarnings::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command(CheckLicenseExpirations::class)->dailyAt('02:00')->withoutOverlapping();
// Closes tickets left waiting on the customer. A no-op for every tenant that has
// not set auto_close_pending_days, which is all of them until they opt in.
Schedule::command(AutoClosePendingTickets::class)->hourly()->withoutOverlapping();
// Fallback for bug reports whose PR webhook never arrived; no-op when GitHub
// is unconfigured.
Schedule::command('bugs:reconcile-prs')->everyTenMinutes()->withoutOverlapping();
Schedule::command('ai:embed:tickets --limit=200')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ai:embed:knowledge')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('ai:embed:snippets')->hourly()->withoutOverlapping();
Schedule::command('ai:maintenance --mode=daily')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('ai:maintenance --mode=hourly')->hourly()->withoutOverlapping();
Schedule::command('ai:maintenance --mode=weekly')->weeklyOn(0, '01:00')->withoutOverlapping();
