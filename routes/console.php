<?php

use Illuminate\Support\Facades\Schedule;

// Driven by one cron entry: * * * * * php artisan schedule:run (see docs/DEPLOYMENT.md).

// Shared hosting cannot keep a worker running, so the scheduler drains the queue every minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

Schedule::command('oneseller:expire-unpaid-orders')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('oneseller:low-stock-digest')->dailyAt('09:00');
