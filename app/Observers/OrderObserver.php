<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ActivityLogger;

class OrderObserver
{
    public function created(Order $order): void
    {
        $label = $order->isBooking() ? 'catering booking' : 'order';
        $actorName = auth()->user()?->name ?? 'A customer';

        $changes = $order->payment_method === 'gcash'
            ? ['payment_method' => ['old' => null, 'new' => 'gcash'], 'gcash_reference' => ['old' => null, 'new' => $order->gcash_reference]]
            : null;

        ActivityLogger::log(
            $order->isBooking() ? 'booking.requested' : 'order.placed',
            'orders',
            "{$actorName} placed " . ($order->isBooking() ? 'a catering booking request' : 'an order')
                . " #{$order->order_number} (₱" . number_format($order->total_amount, 2) . ")",
            subject: $order,
            changes: $changes,
        );
    }

    public function updated(Order $order): void
    {
        $diff = ActivityLogger::diffFor($order, ignore: ['qr_code', 'gcash_reference']);
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
