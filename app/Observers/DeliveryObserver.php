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
            // The photo is mentioned in the sentence below ("photo attached")
            // but its storage path never appears in the log at all — not
            // even as a reference — per the brief's "never embeds the image."
            'proof_photo_path',
            // Already logged with its own clearer sentence by
            // Customer\OrderController::confirmReceived() — ignoring it here
            // stops that same save from also producing a generic, redundant
            // "updated delivery (customer_confirmed_at)" entry.
            'customer_confirmed_at',
            // Logged as "delivery.qr_verified" by App\Services\QrHandoff.
            'qr_scanned_at', 'qr_scanned_by', 'qr_scan_lat', 'qr_scan_lng', 'qr_scan_accuracy',
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
            $photoNote = ($diff['status']['new'] === 'delivered' && $delivery->proof_photo_path)
                ? ' (photo attached)'
                : '';

            ActivityLogger::log(
                'delivery.status_changed',
                'orders',
                "{$actorName} marked {$orderLabel} delivery as " . str_replace('_', ' ', $diff['status']['new']) . $photoNote,
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
