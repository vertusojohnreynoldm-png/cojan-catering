<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnpaidBookingExpirer;
use App\Models\Order;

class BookingController extends Controller
{
    public function index()
    {
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

        // Upcoming bookings sort ahead of past ones regardless of exact date
        // (event_date < now evaluates to 0/1, so future rows sort first),
        // then soonest-first within each group — a booking from months ago
        // shouldn't outrank one happening next week just because its date is
        // numerically smaller. "now" is bound from PHP rather than SQL NOW(),
        // which SQLite (the test database) doesn't have — and it keeps the
        // app's Asia/Manila clock as the reference.
        $bookings = Order::bookings()
            ->with('user', 'orderItems.package')
            ->orderByRaw('event_date < ?', [now()->toDateTimeString()])
            ->orderBy('event_date', 'asc')
            ->get();

        return view('admin.bookings', compact('bookings'));
    }
}
