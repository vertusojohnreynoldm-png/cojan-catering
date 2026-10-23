<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Utensil;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything that happens to utensil stock and admin flags when an order is
 * cancelled — or un-cancelled.
 *
 * restoreStock() is called from OrderObserver whenever an order's status
 * becomes 'cancelled', whatever did it (admin status change, No-show/Fake,
 * Stage 4's unpaid auto-expiry), so no cancel path can forget it.
 * orders.stock_restored_at, checked under a row lock, makes it run at most
 * once per cancellation.
 */
class OrderCancellation
{
    /**
     * Give a cancelled order's utensils back to stock, once. Returns true if
     * anything was restored. Safe to call any number of times.
     */
    public static function restoreStock(Order $order): bool
    {
        $restored = DB::transaction(function () use ($order) {
            // Row lock: two requests cancelling the same order at once can't
            // both see stock_restored_at as empty.
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'cancelled' || $locked->stock_restored_at !== null) {
                return null;
            }

            $needs = PackageStockService::utensilsHeldBy($locked);
            if (empty($needs)) {
                return null; // menu-only order — nothing was ever reserved
            }

            $utensils = Utensil::whereIn('id', array_keys($needs))->lockForUpdate()->get()->keyBy('id');
            $changes = [];

            // Suppressed for the same reason as the decrement in
            // PackageStockService: one consolidated entry below instead of
            // one "stock adjusted" entry per utensil.
            ActivityLogger::withoutLogging(function () use ($needs, $utensils, &$changes) {
                foreach ($needs as $utensilId => $quantity) {
                    $utensil = $utensils->get($utensilId);
                    if (!$utensil || $quantity <= 0) {
                        continue; // utensil deleted since — nothing to return it to
                    }

                    $before = $utensil->quantity;
                    $utensil->increment('quantity', $quantity);
                    $changes[$utensil->name] = ['old' => $before, 'new' => $before + $quantity];
                }
            });

            $locked->stock_restored_at = now();
            $locked->saveQuietly(); // the guard column alone isn't worth its own "order updated" entry

            return [$locked, $changes];
        });

        if (!$restored) {
            return false;
        }

        [$locked, $changes] = $restored;
        $order->stock_restored_at = $locked->stock_restored_at;

        ActivityLogger::log(
            'stock.utensils_restored',
            'stock',
            'Utensils from cancelled ' . ($order->isBooking() ? 'booking' : 'order')
                . " #{$order->order_number} were returned to stock",
            subject: $order,
            changes: $changes ?: null,
        );

        return true;
    }

    /**
     * Admin moves a cancelled order back to an active status. If its stock
     * had been returned, it's taken out again first — and if that stock is
     * no longer there, the reopen is refused (InsufficientStockException)
     * rather than silently overbooking utensils.
     */
    public static function reopen(Order $order, string $newStatus): void
    {
        DB::transaction(function () use ($order, $newStatus) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked->stock_restored_at !== null) {
                $needs = PackageStockService::utensilsHeldBy($locked);
                PackageStockService::lockAndDecrementNeeds($needs);

                ActivityLogger::log(
                    'stock.utensils_reserved_again',
                    'stock',
                    "Utensils for reopened Order #{$order->order_number} were taken out of stock again",
                    subject: $order,
                );
            }

            $changes = [
                'status'              => $newStatus,
                'flag'                => null,
                'cancellation_reason' => null,
                'flagged_at'          => null,
                'flagged_by'          => null,
                'stock_restored_at'   => null,
            ];

            // A booking reopened while its downpayment is still unpaid gets a
            // fresh deadline — otherwise the old, past one would expire it
            // again on the very next check.
            if ($locked->downpayment_amount !== null && $locked->downpayment_paid_at === null && $locked->event_date) {
                $changes['payment_due_at'] = Downpayment::deadlineFor($locked->event_date);
            }

            $order->update($changes);
        });
    }

    /**
     * Admin marks an active order/booking as a No-show or Fake: it's
     * cancelled (which returns its stock via the observer) and flagged.
     */
    public static function flag(Order $order, string $flag, ?string $reason): void
    {
        if (!array_key_exists($flag, Order::FLAGS)) {
            throw new InvalidArgumentException("Unknown flag [$flag].");
        }

        $before = $order->status;

        DB::transaction(function () use ($order, $flag, $reason) {
            $order->update([
                'status'              => 'cancelled',
                'flag'                => $flag,
                'cancellation_reason' => $reason,
                'flagged_at'          => now(),
                'flagged_by'          => auth()->id(),
            ]);
        });

        $actor = auth()->user()?->name ?? 'System';
        $what = $order->isBooking() ? 'booking' : 'order';

        ActivityLogger::log(
            'order.flagged_' . $flag,
            'orders',
            "{$actor} marked {$what} #{$order->order_number} as " . Order::FLAGS[$flag]
                . ($reason ? " — {$reason}" : ''),
            subject: $order,
            changes: [
                'status' => ['old' => $before, 'new' => 'cancelled'],
                'flag'   => ['old' => null, 'new' => $flag],
            ],
        );
    }
}
