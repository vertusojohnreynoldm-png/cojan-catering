<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->role !== $role) {
            // Redirect to correct dashboard instead of 403
            $userRole = $request->user()->role;
            if ($userRole === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($userRole === 'delivery') {
                return redirect()->route('delivery.dashboard');
            } else {
                return redirect()->route('customer.dashboard');
            }
        }

        return $next($request);
    }
}