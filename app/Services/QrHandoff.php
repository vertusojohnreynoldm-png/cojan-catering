<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;

/**
 * "QR verified at hand-off": the ASSIGNED rider scans the customer's order
 * QR (its /track/{order number} link) while the order is picked up / in
 * transit. Optional — a delivery can still be marked Delivered without it
 * (the customer's phone may be dead or offline); admins then see
 * "Delivered without QR verification".
 */
class QrHandoff
{
    /** GPS may be attached after a phone-camera scan for this long. */
    public const POSITION_WINDOW_MINUTES = 10;

    /**
     * True when $code is THIS site's /track/{order number} link for THIS
     * order (http or https; this host or APP_URL's host; query ignored).
     */
    public static function matches(string $code, Order $order): bool
    {
        $parts = parse_url(trim($code));
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');
        $expectedPath = parse_url(route('customer.orders.track', $order->order_number), PHP_URL_PATH);

        return in_array($host, self::hosts(), true)
            && rtrim($parts['path'] ?? '', '/') === $expectedPath;
    }

    /** Hosts a genuine order QR can point at (shared with the scanner JS). */
    public static function hosts(): array
    {
        return array_values(array_unique(array_filter([
            strtolower(request()->getHost()),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
        ])));
    }

    /**
     * Record the hand-off. Only the first scan counts (a conditional update,
     * so a camera-app scan and an in-app scan at the same moment can't both
     * log). A later scan with a position fills in a missing position.
     *
     * @param  array{lat: float, lng: float, accuracy: ?float}|null  $position
     * @param  'phone camera'|'in-app scanner'  $via
     * @return bool  true when this call verified it
     */
    public static function record(Delivery $delivery, User $rider, ?array $position, string $via): bool
    {
        $now = now();
        $verified = Delivery::whereKey($delivery->id)->whereNull('qr_scanned_at')->update(array_merge(
            ['qr_scanned_at' => $now, 'qr_scanned_by' => $rider->id, 'updated_at' => $now],
            self::positionColumns($position),
        )) === 1;

        if ($verified) {
            $order = $delivery->order;
            ActivityLogger::log(
                'delivery.qr_verified',
                'orders',
                "{$rider->name} verified the customer's QR at hand-off for Order #{$order?->order_number} ({$via})",
                subject: $order ?? $delivery,
                changes: ['qr_scanned_at' => ['old' => null, 'new' => $now->toDateTimeString()]],
                actor: $rider,
            );
        } elseif ($position) {
            self::attachPosition($delivery, $rider, $position);
        }

        $delivery->refresh();

        return $verified;
    }

    /**
     * Phone-camera scans arrive as a plain link (no GPS), so the rider page
     * sends the position right after. Only fills an empty position, only for
     * the rider who scanned, and only shortly after the scan.
     */
    public static function attachPosition(Delivery $delivery, User $rider, array $position): bool
    {
        return Delivery::whereKey($delivery->id)
            ->where('qr_scanned_by', $rider->id)
            ->where('qr_scanned_at', '>=', now()->subMinutes(self::POSITION_WINDOW_MINUTES))
            ->whereNull('qr_scan_lat')
            ->update(self::positionColumns($position)) === 1;
    }

    private static function positionColumns(?array $position): array
    {
        if (!$position || !isset($position['lat'], $position['lng'])) {
            return [];
        }

        return [
            'qr_scan_lat' => $position['lat'],
            'qr_scan_lng' => $position['lng'],
            'qr_scan_accuracy' => $position['accuracy'] ?? null,
        ];
    }
}
