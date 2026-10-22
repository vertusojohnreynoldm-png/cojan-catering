<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every route that starts or places an order/booking. A blocked
 * customer is sent to a polite explanation page instead — they keep their
 * login, order history and chat (none of those routes use this).
 */
class EnsureCustomerNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This account can\'t place new orders right now.'], 403);
            }

            return redirect()->route('customer.account-restricted');
        }

        return $next($request);
    }
}
