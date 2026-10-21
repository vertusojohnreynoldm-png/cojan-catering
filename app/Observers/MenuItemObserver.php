<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Services\ActivityLogger;

class MenuItemObserver
{
    public function created(MenuItem $menuItem): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'menu.created',
            'admin',
            "{$actorName} added menu item \"{$menuItem->name}\"",
            subject: $menuItem,
        );
    }

    public function updated(MenuItem $menuItem): void
    {
        $diff = ActivityLogger::diffFor($menuItem, ignore: ['image']);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';

        if (isset($diff['is_available']) && count($diff) === 1) {
            // Check the model's real current value, not diffFor()'s already
            // Yes/No-stringified entry — that string is always truthy.
            $state = $menuItem->is_available ? 'on' : 'off';
            ActivityLogger::log(
                'menu.availability_toggled',
                'admin',
                "{$actorName} turned {$state} availability for menu item \"{$menuItem->name}\"",
                subject: $menuItem,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'menu.updated',
            'admin',
            "{$actorName} updated menu item \"{$menuItem->name}\" (" . implode(', ', array_keys($diff)) . ')',
            subject: $menuItem,
            changes: $diff,
        );
    }

    public function deleted(MenuItem $menuItem): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'menu.deleted',
            'admin',
            "{$actorName} deleted menu item \"{$menuItem->name}\"",
            subject: $menuItem,
        );
    }
}
