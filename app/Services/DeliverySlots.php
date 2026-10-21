<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Generates and validates 30-minute delivery windows for "Schedule for
 * later" regular menu orders. Catering package bookings have their own
 * event_date flow (Customer\PackageController) and never touch this.
 *
 * This is the single source of truth for slot generation — the checkout
 * page's slot chips are populated from this class via an AJAX endpoint
 * (Customer\OrderController::deliverySlots()), and the order submission is
 * re-validated against it server-side, so the browser is never trusted.
 */
class DeliverySlots
{
    public const MIN_LEAD_MINUTES = 45;
    public const MAX_DAYS_AHEAD = 3;
    public const SLOT_MINUTES = 30;

    public static function earliestSelectableDate(): Carbon
    {
        return Carbon::now()->startOfDay();
    }

    public static function latestSelectableDate(): Carbon
    {
        return Carbon::now()->addDays(self::MAX_DAYS_AHEAD)->startOfDay();
    }

    /**
     * Every 30-minute slot for the given date that's still selectable right
     * now: inside business hours, starting at/after the minimum lead time
     * from this exact moment, and within the 3-day booking window.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function slotsFor(string $date): array
    {
        try {
            $day = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        if ($day->lt(self::earliestSelectableDate()) || $day->gt(self::latestSelectableDate())) {
            return [];
        }

        $open = $day->copy()->setTimeFromTimeString(BusinessHours::OPEN_TIME);
        $close = $day->copy()->setTimeFromTimeString(BusinessHours::CLOSE_TIME);
        $earliestStart = Carbon::now()->addMinutes(self::MIN_LEAD_MINUTES);

        $slots = [];
        $cursor = $open->copy();

        while ($cursor->copy()->addMinutes(self::SLOT_MINUTES)->lte($close)) {
            if ($cursor->gte($earliestStart)) {
                $slotEnd = $cursor->copy()->addMinutes(self::SLOT_MINUTES);
                $slots[] = [
                    'value' => $cursor->format('Y-m-d H:i'),
                    'label' => $cursor->format('g:i A') . ' - ' . $slotEnd->format('g:i A'),
                ];
            }

            $cursor->addMinutes(self::SLOT_MINUTES);
        }

        return $slots;
    }

    public static function isValidSlot(string $date, string $slotValue): bool
    {
        foreach (self::slotsFor($date) as $slot) {
            if ($slot['value'] === $slotValue) {
                return true;
            }
        }

        return false;
    }

    public static function labelFor(string $slotValue): string
    {
        $start = Carbon::parse($slotValue);

        return $start->format('g:i A') . ' - ' . $start->copy()->addMinutes(self::SLOT_MINUTES)->format('g:i A');
    }
}
