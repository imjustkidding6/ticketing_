<?php

use App\Console\Commands\CleanupChatbotSessions;
use App\Console\Commands\SendSlaBreachWarnings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendSlaBreachWarnings::class)->everyFifteenMinutes();
Schedule::command(CleanupChatbotSessions::class)->daily();
