<?php

namespace App\Services;

use App\Models\CateringPackage;
use App\Models\MenuItem;

/**
 * The session cart, made trustworthy before it's shown or ordered:
 *  - price and name are re-read from the DATABASE every time (never from
 *    anything the browser sent, and never a stale price from when the item
 *    was added);
 *  - menu-item quantities are whole numbers capped at the admin's
 *    "Max quantity per menu item per order" (Booking Rules). Catering
 *    packages are not capped.
 * Items deleted or made unavailable since are left for the caller (checkout
 * already removes those with its own message).
 */
class CartService
{
    public static function maxPerItem(): int
    {
        return BookingSettings::get('max_quantity_per_menu_item');
    }

    public static function maxMessage(): string
    {
        return 'Maximum ' . self::maxPerItem() . ' per item per order.';
    }

    /**
     * Normalise the session cart in place.
     *
     * @return array{0: array, 1: array<int, string>}  [cart, notices about anything that was capped]
     */
    public static function refresh(): array
    {
        $cart = session()->get('cart', []);
        $max = self::maxPerItem();
        $notices = [];

        $menuIds = $packageIds = [];
        foreach ($cart as $line) {
            if (($line['type'] ?? 'menu') === 'package') {
                $packageIds[] = $line['id'];
            } else {
                $menuIds[] = $line['id'];
            }
        }
        $menuItems = MenuItem::whereIn('id', $menuIds)->get()->keyBy('id');
        $packages = CateringPackage::whereIn('id', $packageIds)->get()->keyBy('id');

        foreach ($cart as $key => $line) {
            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty < 1) {
                unset($cart[$key]);
                continue;
            }

            $isPackage = ($line['type'] ?? 'menu') === 'package';
            $model = $isPackage ? $packages->get($line['id']) : $menuItems->get($line['id']);

            if ($model) {
                $cart[$key]['price'] = (float) $model->price;
                $cart[$key]['name'] = $model->name;
            }

            if (!$isPackage && $qty > $max) {
                $notices[] = "We set {$cart[$key]['name']} to {$max} — the maximum per item per order.";
                $qty = $max;
            }

            $cart[$key]['quantity'] = $qty;
        }

        session()->put('cart', $cart);

        return [$cart, $notices];
    }

    /** Subtotal from the (already refreshed) cart: database price × validated quantity. */
    public static function subtotal(array $cart): float
    {
        return round(array_sum(array_map(fn ($line) => $line['price'] * $line['quantity'], $cart)), 2);
    }
}
