<?php

use App\Jobs\ReleaseExpiredReservations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReleaseExpiredReservations)->everyFiveMinutes()->withoutOverlapping();

// There are no long-running queue workers on this host (shared hosting,
// cron-only). Queued mail (order confirmations, shipment notifications,
// payment anomaly alerts) would otherwise pile up in the `jobs` table and
// never be sent, so drain it on the same cron tick instead.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
