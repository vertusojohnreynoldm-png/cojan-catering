<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonEmailTypo implements ValidationRule
{
    /**
     * Common misspellings of popular email providers, mapped to the
     * domain they almost certainly meant to type.
     */
    private const TYPO_MAP = [
        // Gmail
        'gnail.com'  => 'gmail.com',
        'gmial.com'  => 'gmail.com',
        'gmal.com'   => 'gmail.com',
        'gmai.com'   => 'gmail.com',
        'gmil.com'   => 'gmail.com',
        'gamil.com'  => 'gmail.com',
        'gmail.co'   => 'gmail.com',
        'gmail.cm'   => 'gmail.com',
        'gmailcom'   => 'gmail.com',
        // Yahoo
        'yaho.com'   => 'yahoo.com',
        'yahooo.com' => 'yahoo.com',
        'yhoo.com'   => 'yahoo.com',
        'yahoo.co'   => 'yahoo.com',
        'yaho.co'    => 'yahoo.com',
        // Hotmail
        'hotmial.com' => 'hotmail.com',
        'hotmil.com'  => 'hotmail.com',
        'hotmai.com'  => 'hotmail.com',
        'hotmail.co'  => 'hotmail.com',
        'hotmali.com' => 'hotmail.com',
        // Outlook
        'outlok.com'   => 'outlook.com',
        'outllook.com' => 'outlook.com',
        'outlook.co'   => 'outlook.com',
        'outlok.co'    => 'outlook.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_contains($value, '@')) {
            return; // not this rule's job — the email/rfc rule handles basic shape
        }

        [$local, $domain] = explode('@', $value, 2);
        $domain = strtolower(trim($domain));

        if (isset(self::TYPO_MAP[$domain])) {
            $fail("Did you mean {$local}@" . self::TYPO_MAP[$domain] . '?');
        }
    }
}
