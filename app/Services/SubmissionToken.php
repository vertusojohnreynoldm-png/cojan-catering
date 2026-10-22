<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * One-time tokens for the forms that create an order (menu checkout and
 * catering package checkout), so a double tap or a slow-network retry
 * creates exactly ONE order.
 *
 * - issue() puts a fresh token in the rendered form and remembers it in the
 *   session (a few per form, so two open tabs both still work).
 * - The controller looks for an order already created with that token
 *   first (existingOrder()) and, if there is one, just shows it again.
 * - The token is saved on the order as orders.idempotency_key, which is
 *   UNIQUE — so even two truly simultaneous requests (which both see the
 *   token in their copy of the session) can't both insert. Whatever stops
 *   the losing request (the unique key, or the slot/date lock re-check),
 *   the caller looks the token up again and shows the winner's order.
 * - forget() drops it from the session once the order exists.
 */
class SubmissionToken
{
    // Every render of the form issues one (including the re-render after a
    // validation error), so this is how many recent renders stay valid —
    // enough for a few open tabs.
    private const KEEP_PER_FORM = 10;

    public static function issue(string $form): string
    {
        $token = (string) Str::uuid();
        $tokens = session()->get(self::key($form), []);
        $tokens[] = $token;

        session()->put(self::key($form), array_slice($tokens, -self::KEEP_PER_FORM));

        return $token;
    }

    public static function isIssued(string $form, ?string $token): bool
    {
        return $token !== null && in_array($token, session()->get(self::key($form), []), true);
    }

    public static function forget(string $form, string $token): void
    {
        session()->put(
            self::key($form),
            array_values(array_diff(session()->get(self::key($form), []), [$token]))
        );
    }

    /** The order this user already placed with this token, if any. */
    public static function existingOrder(?string $token, int $userId): ?Order
    {
        if (!$token) {
            return null;
        }

        return Order::where('idempotency_key', $token)->where('user_id', $userId)->first();
    }

    private static function key(string $form): string
    {
        return "submission_tokens.$form";
    }
}
