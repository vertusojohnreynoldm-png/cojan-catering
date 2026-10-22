<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Utensil;

class PackageStockService
{
    /**
     * Lock, verify, and decrement utensil stock for a set of packages being
     * ordered. Must be called inside the caller's own DB::transaction() —
     * this method does not open one itself, so the lock it takes is held for
     * whatever the caller does around it.
     *
     * @param  array<int, array{0: \App\Models\CateringPackage, 1: int}>  $packageLines
     *         Each entry is [CateringPackage $package, int $quantity].
     * @return array<int, int>  utensil_id => quantity taken — store it on the
     *         order (orders.reserved_utensils) so a cancellation can give
     *         back exactly this much.
     */
    public static function lockAndDecrement(array $packageLines): array
    {
        $utensilNeeds = [];         // utensil_id => total quantity needed
        $packageNamesByUtensil = []; // utensil_id => [package names]

        foreach ($packageLines as [$package, $quantity]) {
            foreach ($package->utensils as $utensil) {
                $needed = $utensil->pivot->quantity_needed * $quantity;
                $utensilNeeds[$utensil->id] = ($utensilNeeds[$utensil->id] ?? 0) + $needed;
                $packageNamesByUtensil[$utensil->id][] = $package->name;
            }
        }

        self::lockAndDecrementNeeds($utensilNeeds, $packageNamesByUtensil);

        return $utensilNeeds;
    }

    /**
     * The same lock-check-decrement for an exact utensil_id => quantity map
     * (used when an admin reopens a cancelled order and its stock has to be
     * taken out again). Call inside a transaction.
     *
     * @param  array<int, int>  $utensilNeeds
     * @param  array<int, string[]>  $packageNamesByUtensil  only for the error message
     */
    public static function lockAndDecrementNeeds(array $utensilNeeds, array $packageNamesByUtensil = []): void
    {
        if (empty($utensilNeeds)) {
            return;
        }

        // lockForUpdate(): pessimistic row lock so two simultaneous checkouts
        // can't both pass this check and together overdraw the same utensil
        // stock.
        $utensilsInStock = Utensil::whereIn('id', array_keys($utensilNeeds))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($utensilNeeds as $utensilId => $needed) {
            $utensil = $utensilsInStock->get($utensilId);

            if (!$utensil || $utensil->quantity < $needed) {
                $utensilName = $utensil->name ?? 'equipment';
                $packageNames = implode(', ', array_unique($packageNamesByUtensil[$utensilId] ?? []));

                throw new InsufficientStockException(
                    "Sorry, we don't have enough {$utensilName} in stock right now"
                        . ($packageNames ? " to fulfill: {$packageNames}" : '')
                        . '. Please try again later or reduce the quantity.'
                );
            }
        }

        // Suppressed: without this, decrementing N utensils for one order
        // would fire UtensilObserver N times — one near-identical "stock
        // adjusted" entry per utensil. The order's own ActivityLogger entry
        // (see OrderObserver::created()) is the single, consolidated record
        // of this stock movement instead.
        ActivityLogger::withoutLogging(function () use ($utensilNeeds, $utensilsInStock) {
            foreach ($utensilNeeds as $utensilId => $needed) {
                $utensilsInStock->get($utensilId)->decrement('quantity', $needed);
            }
        });
    }

    /**
     * What this order took out of utensil stock: the exact amounts recorded
     * when it was placed, or — for orders placed before that was recorded —
     * worked out from its packages' current contents.
     *
     * @return array<int, int>  utensil_id => quantity
     */
    public static function utensilsHeldBy(Order $order): array
    {
        if (is_array($order->reserved_utensils)) {
            return array_map('intval', $order->reserved_utensils);
        }

        $needs = [];
        foreach ($order->orderItems()->whereNotNull('package_id')->with('package.utensils')->get() as $item) {
            foreach ($item->package?->utensils ?? [] as $utensil) {
                $needs[$utensil->id] = ($needs[$utensil->id] ?? 0) + $utensil->pivot->quantity_needed * $item->quantity;
            }
        }

        return $needs;
    }
}
