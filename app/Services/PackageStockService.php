<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
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
     */
    public static function lockAndDecrement(array $packageLines): void
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
                    "Sorry, we don't have enough {$utensilName} in stock right now "
                        . "to fulfill: {$packageNames}. Please try again later or reduce the quantity."
                );
            }
        }

        foreach ($utensilNeeds as $utensilId => $needed) {
            $utensilsInStock->get($utensilId)->decrement('quantity', $needed);
        }
    }
}
