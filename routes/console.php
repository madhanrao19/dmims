<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proactive operational alerts (PRD §16 / TDD §24). Requires the scheduler
// cron entry from the Deployment Guide: `* * * * * php artisan schedule:run`.
Schedule::command('dmims:generate-notifications')->hourly()->withoutOverlapping();

// Nightly database backup (TDD §27).
Schedule::command('dmims:backup-database')->dailyAt('02:30')->withoutOverlapping();

// Weekly restore-readiness check on the latest backup.
Schedule::command('dmims:verify-latest-backup')->weekly()->withoutOverlapping();

// Prune expired Sanctum tokens (defence-in-depth alongside SANCTUM_TOKEN_EXPIRATION).
Schedule::command('sanctum:prune-expired', ['--hours=24'])->daily();
