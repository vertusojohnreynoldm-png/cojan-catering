<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * The catering downpayment rules (Admin → Booking Rules): how much is paid
 * upfront via GCash, and by when.
 */
class Downpayment
{
    public static function percent(): int
    {
        return BookingSettings::get('catering_downpayment_percent');
    }

    public static function amountFor(float $total): float
    {
        return round($total * self::percent() / 100, 2);
    }

    /**
     * Now + the admin's deadline hours, but never later than the event
     * itself — a booking a few hours out must be paid before it starts.
     */
    public static function deadlineFor(Carbon $eventAt, ?Carbon $from = null): Carbon
    {
        $deadline = ($from ?? Carbon::now())->copy()->addHours(BookingSettings::get('payment_deadline_hours'));

        return $deadline->lt($eventAt) ? $deadline : $eventAt->copy();
    }
}
