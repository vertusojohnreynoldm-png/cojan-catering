<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogger;

class UserObserver
{
    /**
     * Fires for BOTH admin-created staff/customer accounts (Admin\UserController)
     * and self-registration (Auth\RegisteredUserController) — only the admin
     * case is logged here. Self-registration is already covered by the
     * Registered auth-event listener with its own wording, and at the point
     * User::create() runs during self-registration Auth::login() hasn't
     * happened yet, so there's no authenticated admin to misattribute this to.
     */
    public function created(User $user): void
    {
        $actor = auth()->user();
        if (!$actor || $actor->role !== 'admin') {
            return;
        }

        ActivityLogger::log(
            'user.created',
            'admin',
            "{$actor->name} created a new {$user->role} account for {$user->name}",
            subject: $user,
            actor: $actor,
        );
    }

    public function updated(User $user): void
    {
        // email_verified_at is covered by the Verified auth-event listener;
        // password changes are covered by the dedicated auth.password_changed
        // entry — logging either again here would duplicate that event.
        $diff = ActivityLogger::diffFor($user, ignore: ['password', 'remember_token', 'email_verified_at']);
        if ($diff === null) {
            return;
        }

        $actorName = auth()->user()?->name ?? 'System';

        if (isset($diff['role'])) {
            ActivityLogger::log(
                'user.role_changed',
                'admin',
                "{$actorName} changed {$user->name}'s role from {$diff['role']['old']} to {$diff['role']['new']}",
                subject: $user,
                changes: $diff,
            );
            return;
        }

        ActivityLogger::log(
            'user.updated',
            'admin',
            "{$actorName} updated {$user->name}'s account (" . implode(', ', array_keys($diff)) . ')',
            subject: $user,
            changes: $diff,
        );
    }

    public function deleted(User $user): void
    {
        $actorName = auth()->user()?->name ?? 'System';

        ActivityLogger::log(
            'user.deleted',
            'admin',
            "{$actorName} deleted the {$user->role} account for {$user->name}",
            subject: $user,
        );
    }
}
