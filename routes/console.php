<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired tokens stop authenticating on their own; this only removes their
// rows a day later (ADR-0013, item 17). The deploy must run the scheduler.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
