<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class BookingController extends Controller
{
    public function index()
    {
        // Upcoming bookings sort ahead of past ones regardless of exact date
        // (event_date < NOW() evaluates to 0/1, so future rows sort first),
        // then soonest-first within each group — a booking from months ago
        // shouldn't outrank one happening next week just because its date is
        // numerically smaller.
        $bookings = Order::bookings()
            ->with('user', 'orderItems.package')
            ->orderByRaw('event_date < NOW()')
            ->orderBy('event_date', 'asc')
            ->get();

        return view('admin.bookings', compact('bookings'));
    }
}
