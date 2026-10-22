<?php

namespace App\Services;

use App\Exceptions\BookingUnavailableException;
use App\Exceptions\DuplicateSlotOrderException;
use App\Models\BlockedDate;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "can this date / time frame still be booked?"
 * — used both to grey out the customer date pickers and, authoritatively,
 * inside the order-creation transaction.
 *
 * The reserve*() methods MUST be called inside DB::transaction(). They take
 * a row lock (SELECT ... FOR UPDATE on booking_locks) for the bucket being
 * booked before counting, so two customers racing for the last catering
 * date or delivery slot are handled one after the other: the second only
 * gets to count once the first has committed (or rolled back), and then
 * sees the first one's order. The browser's greyed-out dates are only ever
 * a hint — this is what actually holds.
 */
class BookingCapacity
{
    /** Every status except cancelled still occupies its date / slot. */
    private static function active($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    // ---- Catering (Avail flow) ------------------------------------------

    public static function earliestCateringDate(): Carbon
    {
        return Carbon::today()->addDays(BookingSettings::get('catering_min_lead_days'));
    }

    public static function latestCateringDate(): Carbon
    {
        return Carbon::today()->addMonthsNoOverflow(BookingSettings::get('catering_max_advance_months'));
    }

    public static function cateringBookingsOn(Carbon $day): int
    {
        return self::active(Order::bookings())
            ->whereDate('event_date', $day->toDateString())
            ->count();
    }

    /**
     * Customer-facing reason this event date can't be booked, or null if it
     * can. $userId adds the "you already have a booking that day" check.
     */
    public static function cateringDateError(Carbon $eventAt, ?int $userId = null): ?string
    {
        $day = $eventAt->copy()->startOfDay();

        if ($day->lt(self::earliestCateringDate())) {
            $lead = BookingSettings::get('catering_min_lead_days');

            return "Catering events must be booked at least {$lead} " . str('day')->plural($lead)
                . ' ahead — the earliest available date is ' . self::earliestCateringDate()->format('M d, Y') . '.';
        }

        if ($day->gt(self::latestCateringDate())) {
            return 'We only take catering bookings up to ' . self::latestCateringDate()->format('M d, Y')
                . ' — please choose an earlier date.';
        }

        if ($reason = self::blockedReason($day)) {
            return $day->format('M d, Y') . " is not available for bookings ({$reason}). Please choose another date.";
        }

        if (self::cateringBookingsOn($day) >= BookingSettings::get('max_catering_events_per_date')) {
            return $day->format('M d, Y') . ' is fully booked. Please choose another date.';
        }

        if ($userId) {
            $existing = self::active(Order::bookings())
                ->where('user_id', $userId)
                ->whereDate('event_date', $day->toDateString())
                ->first();

            if ($existing) {
                return "You already have a catering booking on {$day->format('M d, Y')} (Order #{$existing->order_number}). "
                    . 'Only one booking per date is allowed — please choose another date or contact us.';
            }
        }

        return null;
    }

    /**
     * Dates inside the bookable window that can't be picked, for greying
     * out the calendar: 'Y-m-d' => 'Fully booked' | 'Closed — <reason>'.
     *
     * @return array<string, string>
     */
    public static function unavailableCateringDates(): array
    {
        $from = self::earliestCateringDate();
        $to = self::latestCateringDate()->endOfDay();
        $unavailable = [];

        self::active(Order::bookings())
            ->whereBetween('event_date', [$from, $to])
            ->selectRaw('DATE(event_date) as day, COUNT(*) as total')
            ->groupByRaw('DATE(event_date)')
            ->havingRaw('COUNT(*) >= ?', [BookingSettings::get('max_catering_events_per_date')])
            ->pluck('day')
            ->each(function ($day) use (&$unavailable) {
                $unavailable[Carbon::parse($day)->toDateString()] = 'Fully booked';
            });

        foreach (self::blockedDatesBetween($from, $to) as $date => $reason) {
            $unavailable[$date] = 'Closed — ' . $reason;
        }

        ksort($unavailable);

        return $unavailable;
    }

    /**
     * Locks this event date's bucket, then re-checks everything with the
     * lock held. Throws instead of returning so the surrounding transaction
     * rolls back automatically.
     */
    public static function reserveCatering(Carbon $eventAt, int $userId): void
    {
        self::lock('catering:' . $eventAt->toDateString());

        if ($error = self::cateringDateError($eventAt, $userId)) {
            throw new BookingUnavailableException($error, 'event_date');
        }
    }

    // ---- Menu orders (regular checkout) ---------------------------------

    /** @return array<string, int> 'Y-m-d H:i' => active scheduled orders in that slot */
    public static function slotCountsOn(Carbon $day): array
    {
        return self::active(Order::regularOrders())
            ->where('delivery_type', 'scheduled')
            ->whereDate('requested_delivery_at', $day->toDateString())
            ->pluck('requested_delivery_at')
            ->countBy(fn ($at) => Carbon::parse($at)->format('Y-m-d H:i'))
            ->all();
    }

    public static function slotIsFull(Carbon $slotStart): bool
    {
        $counts = self::slotCountsOn($slotStart);

        return ($counts[$slotStart->format('Y-m-d H:i')] ?? 0) >= BookingSettings::get('max_orders_per_time_frame');
    }

    /**
     * Locks the bucket for this order's delivery day/slot and re-checks it.
     * ASAP orders are only subject to blocked dates (they don't count
     * toward any time frame); scheduled ones also to slot capacity and the
     * same-customer same-slot confirmation.
     */
    public static function reserveMenuOrder(?Carbon $slotStart, int $userId, bool $duplicateConfirmed = false): void
    {
        $day = ($slotStart ?? Carbon::now())->copy()->startOfDay();

        if (!$slotStart) {
            if ($reason = self::blockedReason($day)) {
                throw new BookingUnavailableException(
                    "We're not taking orders today ({$reason}). You can schedule your order for another day instead.",
                    'delivery_type'
                );
            }

            return;
        }

        self::lock('slot:' . $slotStart->format('Y-m-d H:i'));

        if ($reason = self::blockedReason($day)) {
            throw new BookingUnavailableException(
                "{$day->format('M d, Y')} is not available for deliveries ({$reason}). Please choose another date.",
                'delivery_slot'
            );
        }

        if (self::slotIsFull($slotStart)) {
            throw new BookingUnavailableException(
                'That delivery time frame just filled up — please pick another one.',
                'delivery_slot'
            );
        }

        if (!$duplicateConfirmed) {
            $existing = self::active(Order::regularOrders())
                ->where('user_id', $userId)
                ->where('delivery_type', 'scheduled')
                ->whereDate('requested_delivery_at', $day->toDateString())
                ->get()
                ->first(fn ($order) => $order->requested_delivery_at->format('Y-m-d H:i') === $slotStart->format('Y-m-d H:i'));

            if ($existing) {
                throw new DuplicateSlotOrderException(
                    "You already have an order (#{$existing->order_number}) for this delivery time frame. "
                        . 'Tick the confirmation box below if you really want to place a second order.',
                    'delivery_slot'
                );
            }
        }
    }

    // ---- Pending-order limit (per customer) -----------------------------

    /**
     * Customer-facing message if this customer already has the maximum
     * number of still-'pending' bookings (or menu orders), else null.
     * A mixed cart order counts as a menu order — it isn't a booking.
     */
    public static function pendingLimitError(int $userId, bool $isBooking): ?string
    {
        $query = $isBooking ? Order::bookings() : Order::regularOrders();
        $pending = $query->where('user_id', $userId)->where('status', 'pending')->orderBy('created_at')->get();

        $max = BookingSettings::get($isBooking ? 'max_pending_catering_bookings' : 'max_pending_menu_orders');

        if ($pending->count() < $max) {
            return null;
        }

        // At most three numbers, so the message stays short on a phone.
        $numbers = $pending->take(3)->map(fn ($o) => '#' . $o->order_number)->implode(', ')
            . ($pending->count() > 3 ? ' and ' . ($pending->count() - 3) . ' more' : '');

        return $isBooking
            ? "You already have {$pending->count()} catering " . str('booking')->plural($pending->count())
                . " waiting for confirmation ({$numbers}). You can book another package once one of them is confirmed or cancelled."
            : "You already have {$pending->count()} " . str('order')->plural($pending->count())
                . " waiting for confirmation ({$numbers}). You can place another order once one of them is confirmed or cancelled.";
    }

    /**
     * The authoritative pending-limit check, under a per-customer lock so
     * two simultaneous submissions from one account can't both slip under
     * the limit. Call inside the order transaction.
     */
    public static function reservePendingSlot(int $userId, bool $isBooking): void
    {
        self::lock('user:' . $userId);

        if ($error = self::pendingLimitError($userId, $isBooking)) {
            throw new BookingUnavailableException($error, 'pending_limit');
        }
    }

    // ---- Blocked dates ---------------------------------------------------

    /** The admin's reason (or a generic label) if this day is blocked, else null. */
    public static function blockedReason(Carbon $day): ?string
    {
        $blocked = BlockedDate::where('date', $day->toDateString())->first();

        if (!$blocked) {
            return null;
        }

        return $blocked->reason ?: 'closed';
    }

    /** @return array<string, string> 'Y-m-d' => reason */
    public static function blockedDatesBetween(Carbon $from, Carbon $to): array
    {
        return BlockedDate::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn ($b) => [$b->day()->toDateString() => $b->reason ?: 'closed'])
            ->all();
    }

    // ---- Locking ---------------------------------------------------------

    /**
     * Holds a FOR UPDATE lock on the bucket's row until the surrounding
     * transaction ends, creating the row first if this is the bucket's
     * first ever booking. (On SQLite — the test database — FOR UPDATE is a
     * no-op, but SQLite only ever allows one writer at a time anyway.)
     *
     * SELECT ... FOR UPDATE comes first on purpose: INSERT IGNORE on a row
     * that already exists takes a *shared* lock in MySQL, and two requests
     * both holding a shared lock and then both asking for FOR UPDATE is a
     * deadlock. Only a bucket's very first booking can still deadlock (two
     * inserts racing on a missing row) — the order transactions are run
     * with deadlock retries (DB::transaction(..., 3)) to absorb that.
     */
    public static function lock(string $key): void
    {
        if (DB::table('booking_locks')->where('key', $key)->lockForUpdate()->exists()) {
            return;
        }

        DB::table('booking_locks')->insertOrIgnore([
            'key' => $key, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('booking_locks')->where('key', $key)->lockForUpdate()->first();
    }
}
