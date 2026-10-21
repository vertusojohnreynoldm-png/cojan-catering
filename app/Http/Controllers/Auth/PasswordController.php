<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Unlike a reset-via-email (PasswordReset event, already logged) or a
        // failed/forgotten password, this is the logged-in "change my own
        // password" flow — it fires no framework event of its own.
        ActivityLogger::log(
            'auth.password_changed',
            'auth',
            "{$request->user()->name} changed their password",
            actor: $request->user(),
        );

        return back()->with('status', 'password-updated');
    }
}
