<?php

namespace App\Services;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderNotifier
{
    /**
     * Send the order-status-changed email, if the recipient address looks
     * valid. Never throws — a failed send must never break the status
     * update that triggered it. Callers are responsible for only calling
     * this when the status has actually changed.
     */
    public static function sendStatusChanged(Order $order): void
    {
        $email = $order->user?->email;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::debug('Skipped order status email — missing or invalid address', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new OrderStatusMail($order));
        } catch (Throwable $e) {
            Log::error('Failed to send order status email', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
