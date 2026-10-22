<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts 09XXXXXXXXX, +639XXXXXXXXX or 639XXXXXXXXX. Landlines and any
 * other shape are rejected by the pattern itself — a PH mobile number is
 * always a 10-digit subscriber number starting with 9.
 */
class PhilippineMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            $fail('Enter a mobile number.');
            return;
        }

        $cleaned = preg_replace('/[\s\-()]/', '', $value);

        if (!preg_match('/^(?:\+?63|0)9\d{9}$/', $cleaned)) {
            $fail('Enter a valid Philippine mobile number, e.g. 09171234567.');
            return;
        }

        // Repeated-digit junk (09111111111, 09000000000, ...) — checked on
        // the 9-digit subscriber part only, so a real prefix like 0917 that
        // happens to be followed by varied digits is never caught here.
        $subscriber = substr(self::normalize($cleaned), 2);
        if (preg_match('/^(\d)\1{8}$/', $subscriber)) {
            $fail("That doesn't look like a real mobile number.");
        }
    }

    /**
     * Collapses any accepted input shape to the one format we store:
     * 09XXXXXXXXX. Callers should only use this after validate() has
     * already confirmed the shape — it doesn't itself check validity.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }

        return $digits;
    }
}
