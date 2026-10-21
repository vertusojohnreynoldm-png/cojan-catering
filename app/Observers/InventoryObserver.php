<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\ActivityLogger;

class InventoryObserver
{
    public function created(Inventory $inventory): void
    {
        $actorName = auth()->user()?->name ?? 'System';
        $itemName = $inventory->menuItem?->name ?? 'a menu item';

        ActivityLogger::log(
            'stock.inventory_created',
            'stock',
            "{$actorName} set up inventory tracking for \"{$itemName}\" ({$inventory->quantity} {$inventory->unit})",
            subject: $inventory,
        );
    }

    public function updated(Inventory $inventory): void
    {
        $diff = ActivityLogger::diffFor($inventory);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';
        $itemName = $inventory->menuItem?->name ?? 'a menu item';

        if (isset($diff['quantity']) && count($diff) === 1) {
            ActivityLogger::log(
                'stock.inventory_adjusted',
                'stock',
                "{$actorName} adjusted stock for \"{$itemName}\" from {$diff['quantity']['old']} "
                    . "to {$diff['quantity']['new']} {$inventory->unit}",
                subject: $inventory,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'stock.inventory_updated',
            'stock',
            "{$actorName} updated inventory for \"{$itemName}\" (" . implode(', ', array_keys($diff)) . ')',
            subject: $inventory,
            changes: $diff,
        );
    }
}
