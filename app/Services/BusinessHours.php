<?php

namespace App\Services;

use Carbon\Carbon;

class BusinessHours
{
    private const OPEN_TIME = '07:30';
    private const CLOSE_TIME = '20:00';

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
        if (!self::isMenuOrderingOpen()) {
            return null;
        }

        $now = Carbon::now();
        $close = $now->copy()->setTimeFromTimeString(self::CLOSE_TIME);

        return (int) $now->diffInMinutes($close, false);
    }

    public static function hoursLabel(): string
    {
        return '7:30 AM – 8:00 PM';
    }
}
