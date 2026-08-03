<?php

use App\Console\Commands\CheckLicenseExpirations;
use App\Console\Commands\EmbedResolvedTickets;
use App\Console\Commands\SendSlaBreachWarnings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendSlaBreachWarnings::class)->everyFifteenMinutes();
Schedule::command(CheckLicenseExpirations::class)->dailyAt('02:00');
Schedule::command(EmbedResolvedTickets::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ai:embed:tickets')->daily()->withoutOverlapping();
Schedule::command('ai:embed:knowledge')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('ai:embed:snippets')->hourly()->withoutOverlapping();
Schedule::command('ai:maintenance --mode=daily')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('ai:maintenance --mode=hourly')->hourly()->withoutOverlapping();
Schedule::command('ai:maintenance --mode=weekly')->weeklyOn(0, '01:00')->withoutOverlapping();
