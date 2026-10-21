<?php

namespace App\Observers;

use App\Models\CateringPackage;
use App\Services\ActivityLogger;

class CateringPackageObserver
{
    public function created(CateringPackage $package): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'package.created',
            'admin',
            "{$actorName} added catering package \"{$package->name}\"",
            subject: $package,
        );
    }

    public function updated(CateringPackage $package): void
    {
        $diff = ActivityLogger::diffFor($package, ignore: ['image']);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';

        if (isset($diff['is_available']) && count($diff) === 1) {
            $state = $diff['is_available']['new'] ? 'on' : 'off';
            ActivityLogger::log(
                'package.availability_toggled',
                'admin',
                "{$actorName} turned {$state} availability for catering package \"{$package->name}\"",
                subject: $package,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'package.updated',
            'admin',
            "{$actorName} updated catering package \"{$package->name}\" (" . implode(', ', array_keys($diff)) . ')',
            subject: $package,
            changes: $diff,
        );
    }

    public function deleted(CateringPackage $package): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'package.deleted',
            'admin',
            "{$actorName} deleted catering package \"{$package->name}\"",
            subject: $package,
        );
    }
}
