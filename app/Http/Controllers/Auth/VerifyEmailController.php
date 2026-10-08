<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // route('dashboard') doesn't exist in this app (routes are
        // customer.dashboard/admin.dashboard/delivery.dashboard) — this
        // previously would have thrown a RouteNotFoundException the moment
        // anyone actually clicked a verification link.
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('customer.menu'))
                ->with('success', 'Your email is already verified.');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // intended(): if EnsureEmailIsVerified bounced them here from a
        // gated route (checkout, Avail, etc.), that URL was stashed via
        // Redirect::guest() the same way the auth middleware does — so this
        // sends them back to what they were actually trying to do, not just
        // the menu.
        return redirect()->intended(route('customer.menu'))
            ->with('success', 'Your email has been verified! You can now place orders and book catering packages.');
    }
}
