<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * Admin blocks / unblocks a customer. A blocked customer keeps their login,
 * order history and chat, but EnsureCustomerNotBlocked stops every
 * order-placing route. The reason is for admins only.
 */
class CustomerBlocking
{
    public static function block(User $customer, string $reason): void
    {
        if (!$customer->isCustomer()) {
            throw new InvalidArgumentException('Only customer accounts can be blocked.');
        }

        $previous = $customer->blocked_reason;

        // Quiet: the generic "updated X's account (blocked_at, ...)" entry
        // UserObserver would write is replaced by the explicit one below.
        ActivityLogger::withoutLogging(fn () => $customer->update([
            'blocked_at'     => now(),
            'blocked_reason' => $reason,
            'blocked_by'     => auth()->id(),
        ]));

        ActivityLogger::log(
            'user.blocked',
            'admin',
            (auth()->user()?->name ?? 'System') . " blocked {$customer->name} from placing orders — {$reason}",
            subject: $customer,
            changes: ['blocked_reason' => ['old' => $previous, 'new' => $reason]],
        );
    }

    public static function unblock(User $customer): void
    {
        if (!$customer->isBlocked()) {
            return;
        }

        $previous = $customer->blocked_reason;

        ActivityLogger::withoutLogging(fn () => $customer->update([
            'blocked_at'     => null,
            'blocked_reason' => null,
            'blocked_by'     => null,
        ]));

        ActivityLogger::log(
            'user.unblocked',
            'admin',
            (auth()->user()?->name ?? 'System') . " unblocked {$customer->name} — they can place orders again",
            subject: $customer,
            changes: ['blocked_reason' => ['old' => $previous, 'new' => null]],
        );
    }
}
