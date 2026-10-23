<?php

namespace App\Services;

use App\Models\Order;

/**
 * Map pins for MENU-ORDER deliveries (San Jose, Occidental Mindoro only —
 * catering bookings have no pin). Reads the static, committed
 * database/data/barangay_coords.php (generated once by
 * `php artisan geo:fetch-barangay-coords`) — never calls a geocoder at
 * runtime.
 *
 * The bounding box is deliberately rough: San Jose's OSM boundary box plus
 * ~1 km. It only catches pins dropped somewhere obviously wrong; the real
 * service-area rule is still the barangay check in ServiceArea.
 */
class DeliveryLocation
{
    /** ~1 km in degrees of latitude/longitude at this latitude. */
    public const PADDING_DEG = 0.009;

    private static ?array $data = null;

    private static function data(): array
    {
        return self::$data ??= require database_path('data/barangay_coords.php');
    }

    /** @return array{south: float, north: float, west: float, east: float} */
    public static function menuBounds(): array
    {
        $b = self::data()['bounds'];

        return [
            'south' => $b['south'] - self::PADDING_DEG,
            'north' => $b['north'] + self::PADDING_DEG,
            'west'  => $b['west'] - self::PADDING_DEG,
            'east'  => $b['east'] + self::PADDING_DEG,
        ];
    }

    public static function isInsideMenuArea(float $lat, float $lng): bool
    {
        $b = self::menuBounds();

        return $lat >= $b['south'] && $lat <= $b['north'] && $lng >= $b['west'] && $lng <= $b['east'];
    }

    /** @return array{0: float, 1: float} */
    public static function townCenter(): array
    {
        return self::data()['town_center'];
    }

    /**
     * Approximate centre of a San Jose barangay, or the town centre if the
     * barangay is unknown / wasn't found when the data file was generated.
     *
     * @return array{0: float, 1: float}
     */
    public static function barangayCenter(?string $barangay): array
    {
        return ($barangay ? (self::data()['barangays'][$barangay] ?? null) : null) ?? self::townCenter();
    }

    /**
     * Where a menu order is delivered: its pin, or — for orders placed
     * before pins existed — its barangay's centre, flagged approximate.
     *
     * @return array{lat: float, lng: float, approximate: bool}
     */
    public static function forOrder(Order $order): array
    {
        if ($order->latitude !== null && $order->longitude !== null) {
            return ['lat' => (float) $order->latitude, 'lng' => (float) $order->longitude, 'approximate' => false];
        }

        [$lat, $lng] = self::barangayCenter($order->barangay);

        return ['lat' => $lat, 'lng' => $lng, 'approximate' => true];
    }

    /** Everything the checkout map needs, as one JSON-able array. */
    public static function checkoutMapConfig(): array
    {
        return [
            'bounds'     => self::menuBounds(),
            'townCenter' => self::townCenter(),
            'barangays'  => array_filter(self::data()['barangays']),
        ];
    }
}
