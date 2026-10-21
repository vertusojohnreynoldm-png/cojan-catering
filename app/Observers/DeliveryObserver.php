<?php

namespace App\Observers;

use App\Models\Delivery;
use App\Models\User;
use App\Services\ActivityLogger;

class DeliveryObserver
{
    public function created(Delivery $delivery): void
    {
        $actorName = auth()->user()?->name ?? 'System';
        $order = $delivery->order;
        $rider = $delivery->personnel;

        ActivityLogger::log(
            'delivery.assigned',
            'orders',
            "{$actorName} assigned Order #" . ($order?->order_number ?? $delivery->order_id)
                . " to rider " . ($rider?->name ?? 'Unknown'),
            subject: $order ?? $delivery,
            changes: ['rider' => ['old' => null, 'new' => $rider?->name]],
        );
    }

    public function updated(Delivery $delivery): void
    {
        // GPS pings update current_lat/current_lng/current_accuracy/
        // last_location_update every few seconds while a delivery is en
        // route — ignoring them here is what keeps those out of the log
        // entirely, with no special-casing needed in the controller.
        $diff = ActivityLogger::diffFor($delivery, ignore: [
            'current_lat', 'current_lng', 'current_accuracy', 'last_location_update',
            'picked_up_at', 'delivered_at', 'assigned_at',
        ]);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';
        $order = $delivery->order;
        $orderLabel = 'Order #' . ($order?->order_number ?? $delivery->order_id);

        if (isset($diff['user_id'])) {
            $newRider = User::find($diff['user_id']['new']);
            ActivityLogger::log(
                'delivery.reassigned',
                'orders',
                "{$actorName} reassigned {$orderLabel} delivery to " . ($newRider?->name ?? 'Unknown'),
                subject: $order ?? $delivery,
                changes: $diff,
            );
            return;
        }

        if (isset($diff['status'])) {
            ActivityLogger::log(
                'delivery.status_changed',
                'orders',
                "{$actorName} marked {$orderLabel} delivery as " . str_replace('_', ' ', $diff['status']['new']),
                subject: $order ?? $delivery,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'delivery.updated',
            'orders',
            "{$actorName} updated {$orderLabel} delivery (" . implode(', ', array_keys($diff)) . ')',
            subject: $order ?? $delivery,
            changes: $diff,
        );
    }
}
