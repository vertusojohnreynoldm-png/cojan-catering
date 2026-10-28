<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who may rate what, and until when. Every rating route, button and the
 * reminder banner ask this class, so the rules live in one place:
 *  - only the customer's own order, and only once it's Delivered (a
 *    catering booking: Completed);
 *  - a new rating is accepted for "Days to rate an order" after that;
 *  - a submitted rating can be changed for "Days to edit a rating" after it
 *    was first submitted — a separate window, so a rating made on the last
 *    rating day can still be corrected;
 *  - one rating per order.
 * Verified-email and not-blocked are enforced by route middleware.
 */
class Ratings
{
    public const OPEN = 'open';         // can rate now
    public const EDITABLE = 'editable'; // rated, can still edit
    public const LOCKED = 'locked';     // rated, edit window over
    public const CLOSED = 'closed';     // never rated, window over
    public const NOT_YET = 'not_yet';   // not delivered / completed

    public static function windowDays(): int
    {
        return BookingSettings::get('rating_window_days');
    }

    public static function editDays(): int
    {
        return BookingSettings::get('rating_edit_days');
    }

    /** When the order became Delivered/Completed (null if it isn't). */
    public static function completedAt(Order $order): ?Carbon
    {
        if ($order->status !== 'delivered') {
            return null;
        }

        return $order->completed_at ?? $order->updated_at;
    }

    /** Last moment a new rating is accepted. */
    public static function rateDeadline(Order $order): ?Carbon
    {
        return self::completedAt($order)?->copy()->addDays(self::windowDays());
    }

    /** Last moment the existing rating can be changed. */
    public static function editDeadline(Order $order): ?Carbon
    {
        $feedback = $order->feedback;

        return $feedback ? $feedback->created_at->copy()->addDays(self::editDays()) : null;
    }

    public static function status(Order $order): string
    {
        if (!self::completedAt($order)) {
            return self::NOT_YET;
        }

        if ($order->feedback) {
            return now()->lt(self::editDeadline($order)) ? self::EDITABLE : self::LOCKED;
        }

        return now()->lte(self::rateDeadline($order)) ? self::OPEN : self::CLOSED;
    }

    public static function ownedBy(Order $order, ?User $user): bool
    {
        return $user && $order->user_id === $user->id;
    }

    /**
     * The customer's delivered/completed orders still waiting for a rating,
     * newest first — drives the reminder banner.
     */
    public static function awaiting(User $user): Collection
    {
        $earliest = now()->subDays(self::windowDays());

        return Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->whereDoesntHave('feedback')
            ->where(fn ($q) => $q->where('completed_at', '>=', $earliest)
                ->orWhere(fn ($q) => $q->whereNull('completed_at')->where('updated_at', '>=', $earliest)))
            ->latest('completed_at')
            ->get();
    }

    /** Plain text only: tags stripped, whitespace collapsed; empty → null. */
    public static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        return $text === '' ? null : $text;
    }
}
