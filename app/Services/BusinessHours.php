<?php

namespace App\Services;

use Carbon\Carbon;

class BusinessHours
{
    // Public so DeliverySlots can generate slots against the same hours
    // without duplicating the magic strings (and risking drift if these
    // ever change).
    public const OPEN_TIME = '07:30';
    public const CLOSE_TIME = '20:00';

    /**
     * Whether regular menu-item ordering is accepted right now. Relies on
     * APP_TIMEZONE being set to Asia/Manila app-wide, so now() already
     * returns the correct local time without an explicit timezone here.
     *
     * Catering package bookings are NOT subject to this — see
     * Customer\PackageController, which deliberately never calls this class.
     */
    public static function isMenuOrderingOpen(): bool
    {
        if (self::bypassActive()) {
            return true;
        }

        $now = Carbon::now();
        $open = $now->copy()->setTimeFromTimeString(self::OPEN_TIME);
        $close = $now->copy()->setTimeFromTimeString(self::CLOSE_TIME);

        return $now->between($open, $close);
    }

    /**
     * Minutes until closing, or null if not currently open (including
     * already past closing time).
     */
    public static function minutesUntilClose(): ?int
    {
        if (self::bypassActive() || !self::isMenuOrderingOpen()) {
            return null;
        }

        $now = Carbon::now();
        $close = $now->copy()->setTimeFromTimeString(self::CLOSE_TIME);

        return (int) $now->diffInMinutes($close, false);
    }

    /**
     * Local-testing escape hatch (BUSINESS_HOURS_BYPASS in .env, read via
     * config('app.business_hours_bypass')). Only honoured when APP_ENV is
     * "local" — ignored in production and in the test suite ("testing").
     * Only the ordering gate is bypassed; delivery time-frame slots still
     * follow the real hours (DeliverySlots).
     */
    public static function bypassActive(): bool
    {
        return config('app.business_hours_bypass') === true && app()->environment('local');
    }

    public static function hoursLabel(): string
    {
        return '7:30 AM – 8:00 PM';
    }
}
