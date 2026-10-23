<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels catering bookings whose GCash downpayment deadline has passed.
 *
 * Two triggers, because production (Railway) has no scheduler process and
 * runs the queue synchronously:
 *  - `php artisan bookings:expire-unpaid`, scheduled every five minutes in
 *    routes/console.php — works wherever `schedule:run` is set up (e.g.
 *    locally, or if a cron is ever added on Railway);
 *  - runIfDue(), called from the busiest pages (admin dashboard/bookings,
 *    the customer's orders and packages pages, booking submission). It
 *    runs the check at most once every five minutes across all visitors,
 *    so expiry is never more than a few minutes late while anyone is
 *    using the site — and it costs nothing on the other requests.
 *
 * Never expires a booking whose receipt is waiting for admin review — the
 * customer did their part; it's on the admin to confirm or reject.
 * Cancelling sets status = 'cancelled', so OrderObserver returns the
 * utensils (Stage 3) exactly as for any other cancellation.
 */
class UnpaidBookingExpirer
{
    public const REASON = 'Downpayment not received by the deadline';
    private const THROTTLE_KEY = 'bookings:expire-unpaid:last-run';
    private const THROTTLE_SECONDS = 300;

    /** The lazy trigger: runs run() at most once per five minutes. Never throws. */
    public static function runIfDue(): void
    {
        try {
            if (Cache::add(self::THROTTLE_KEY, now()->timestamp, self::THROTTLE_SECONDS)) {
                self::run();
            }
        } catch (Throwable $e) {
            // Expiry is housekeeping — it must never break the page that
            // happened to trigger it.
            Log::error('Lazy unpaid-booking expiry failed: ' . $e->getMessage());
        }
    }

    /** @return int how many bookings were expired */
    public static function run(): int
    {
        $expired = 0;

        $candidateIds = self::overdue()->pluck('id');

        foreach ($candidateIds as $id) {
            $order = DB::transaction(function () use ($id) {
                // Re-check under a row lock: the admin may have confirmed the
                // downpayment, or the customer uploaded a receipt, a moment ago.
                $order = self::overdue()->whereKey($id)->lockForUpdate()->first();

                if (!$order) {
                    return null;
                }

                $order->update(['status' => 'cancelled', 'cancellation_reason' => self::REASON]);

                return $order;
            });

            if (!$order) {
                continue;
            }

            $expired++;

            ActivityLogger::log(
                'booking.expired_unpaid',
                'orders',
                "Booking #{$order->order_number} was cancelled automatically — downpayment not received by "
                    . $order->payment_due_at->format('M d, Y g:i A'),
                subject: $order,
            );

            OrderNotifier::sendBookingPayment($order, 'expired');
        }

        return $expired;
    }

    private static function overdue()
    {
        return Order::bookings()
            ->where('status', 'pending')
            ->whereNotNull('payment_due_at')
            ->whereNull('downpayment_paid_at')
            ->where('payment_due_at', '<', now())
            // A receipt waiting for review is never expired.
            ->where(fn ($q) => $q->whereNull('receipt_path')->orWhereNotNull('receipt_rejected_at'));
    }
}
