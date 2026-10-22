<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPersonName implements ValidationRule
{
    /**
     * Whole-name (not per-word) placeholder blocklist, checked
     * case-insensitively against the trimmed input.
     */
    private const PLACEHOLDERS = [
        'test', 'tester', 'testing', 'asdf', 'asd', 'qwerty', 'xxx', 'xx',
        'n/a', 'na', 'none', 'admin', 'sample', 'example', 'unknown', 'abc',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            $fail('Enter a name.');
            return;
        }

        $name = trim($value);

        if (!preg_match("/^[\p{L} '.\-]+$/u", $name)) {
            $fail('Name can only contain letters, spaces, hyphens, apostrophes and periods.');
            return;
        }

        if (in_array(mb_strtolower($name), self::PLACEHOLDERS, true)) {
            $fail('Enter a real name.');
            return;
        }

        foreach (preg_split('/\s+/', $name) as $word) {
            $letters = trim($word, ".-'");

            // A single letter followed by a period ("D.") is a middle
            // initial, not junk — the 2-character minimum below is about
            // catching "Jo hn" / stray single letters, not initials.
            if ($letters === '' || mb_strlen($letters) === 1 && str_ends_with($word, '.')) {
                continue;
            }

            if (mb_strlen($letters) < 2) {
                $fail('Each part of the name should be at least 2 letters.');
                return;
            }
        }
    }
}
