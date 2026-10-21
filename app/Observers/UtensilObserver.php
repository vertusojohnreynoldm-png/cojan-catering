<?php

namespace App\Observers;

use App\Models\Utensil;
use App\Services\ActivityLogger;

class UtensilObserver
{
    public function created(Utensil $utensil): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'stock.utensil_created',
            'stock',
            "{$actorName} added utensil \"{$utensil->name}\" ({$utensil->quantity} {$utensil->unit})",
            subject: $utensil,
        );
    }

    public function updated(Utensil $utensil): void
    {
        $diff = ActivityLogger::diffFor($utensil);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';

        if (isset($diff['quantity']) && count($diff) === 1) {
            ActivityLogger::log(
                'stock.utensil_adjusted',
                'stock',
                "{$actorName} adjusted stock for \"{$utensil->name}\" from {$diff['quantity']['old']} "
                    . "to {$diff['quantity']['new']} {$utensil->unit}",
                subject: $utensil,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'stock.utensil_updated',
            'stock',
            "{$actorName} updated utensil \"{$utensil->name}\" (" . implode(', ', array_keys($diff)) . ')',
            subject: $utensil,
            changes: $diff,
        );
    }
}
