<?php

use App\Services\UnpaidBookingExpirer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-unpaid', function () {
    $count = UnpaidBookingExpirer::run();
    $this->info("Expired {$count} unpaid " . str('booking')->plural($count) . '.');
})->purpose('Cancel catering bookings whose GCash downpayment deadline has passed');

// Only runs where `php artisan schedule:run` is wired up (locally or via a
// cron). Railway has no scheduler, so the same check also runs lazily from
// busy pages — see UnpaidBookingExpirer::runIfDue().
Schedule::command('bookings:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();
