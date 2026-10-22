<?php

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The checks that run first on every order-creating POST (menu checkout
 * and catering package checkout), before any validation or stock work:
 *
 * 1. Same one-time token as an order that already exists → just show that
 *    order again (a double tap / retry). Doesn't count as an attempt.
 * 2. Token not issued to this session → the page is stale; send the
 *    customer back to a fresh form.
 * 3. Rate limit — 5 submission attempts per 10 minutes per customer,
 *    shared across menu orders and catering bookings.
 * 4. Pending-order limit (admin-editable) — an early, friendly check; the
 *    authoritative one runs under a lock inside the order transaction
 *    (BookingCapacity::reservePendingSlot()).
 *
 * Returns a redirect to send instead of continuing, or null to proceed.
 */
class OrderSubmissionGuard
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_SECONDS = 600;

    public static function check(Request $request, string $form, string $formRoute, bool $isBooking): ?RedirectResponse
    {
        $user = $request->user();
        $token = $request->input('submission_token');

        if ($existing = SubmissionToken::existingOrder($token, $user->id)) {
            return redirect()->route('customer.orders.show', $existing->id)
                ->with('success', $existing->isBooking()
                    ? 'Your booking was already placed — here it is.'
                    : 'Your order was already placed — here it is.');
        }

        if (!SubmissionToken::isIssued($form, $token)) {
            return redirect()->route($formRoute)->withInput()->with(
                'error',
                'This page had expired, so nothing was submitted. Please review your details and try again.'
            );
        }

        $key = self::rateLimitKey($user->id);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return back()->withInput()->withErrors([
                'rate_limit' => "Too many order attempts in a short time. Please wait {$minutes} "
                    . str('minute')->plural($minutes) . ' and try again.',
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        if ($error = BookingCapacity::pendingLimitError($user->id, $isBooking)) {
            return back()->withInput()->withErrors(['pending_limit' => $error]);
        }

        return null;
    }

    public static function rateLimitKey(int $userId): string
    {
        return 'order-submit:' . $userId;
    }
}
