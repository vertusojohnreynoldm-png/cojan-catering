<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\OrderCancellation;

class OrderObserver
{
    public function created(Order $order): void
    {
        $label = $order->isBooking() ? 'catering booking' : 'order';
        $actorName = auth()->user()?->name ?? 'A customer';

        $changes = $order->payment_method === 'gcash'
            ? ['payment_method' => ['old' => null, 'new' => 'gcash'], 'gcash_reference' => ['old' => null, 'new' => $order->gcash_reference]]
            : null;

        // Bookings show their own event_date elsewhere and never set
        // delivery_type/requested_delivery_at, so this is regular-order-only.
        $timeFrame = $order->isBooking() ? '' : ' — ' . $order->deliveryTimeLabel();

        ActivityLogger::log(
            $order->isBooking() ? 'booking.requested' : 'order.placed',
            'orders',
            "{$actorName} placed " . ($order->isBooking() ? 'a catering booking request' : 'an order')
                . " #{$order->order_number} (₱" . number_format($order->total_amount, 2) . "){$timeFrame}",
            subject: $order,
            changes: $changes,
        );
    }

    public function updated(Order $order): void
    {
        try {
            $this->logUpdate($order);
        } finally {
            // Every way an order becomes cancelled (admin status change,
            // No-show/Fake, unpaid auto-expiry) passes through here, so this
            // is the one place utensil stock is returned. Guarded inside
            // restoreStock() by stock_restored_at — never twice. After the
            // log call so the activity log reads "cancelled" → "restored".
            if ($order->wasChanged('status') && $order->status === 'cancelled') {
                OrderCancellation::restoreStock($order);
            }
        }
    }

    private function logUpdate(Order $order): void
    {
        $diff = ActivityLogger::diffFor($order, ignore: ['qr_code', 'gcash_reference', 'reserved_utensils', 'stock_restored_at', 'flagged_at', 'flagged_by']);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';

        if (isset($diff['status'])) {
            ActivityLogger::log(
                'order.status_changed',
                'orders',
                "{$actorName} changed Order #{$order->order_number} from "
                    . "{$order->statusLabel($diff['status']['old'])} to {$order->statusLabel($diff['status']['new'])}",
                subject: $order,
                changes: $diff,
            );
            return;
        }

        if (isset($diff['payment_status'])) {
            ActivityLogger::log(
                'order.payment_status_changed',
                'orders',
                "{$actorName} marked Order #{$order->order_number} payment as {$diff['payment_status']['new']}",
                subject: $order,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'order.updated',
            'orders',
            "{$actorName} updated Order #{$order->order_number} (" . implode(', ', array_keys($diff)) . ')',
            subject: $order,
            changes: $diff,
        );
    }
}
