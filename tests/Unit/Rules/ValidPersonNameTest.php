<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidPersonName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidPersonNameTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new ValidPersonName)->validate('name', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public static function validNames(): array
    {
        return [
            'simple name' => ['Juan Dela Cruz'],
            'with middle initial' => ['Juan D. Cruz'],
            'hyphenated' => ['Maria Clara Santos-Reyes'],
            'with apostrophe' => ["Pat O'Brien"],
            'two letters' => ['Jo Li'],
        ];
    }

    #[DataProvider('validNames')]
    public function test_accepts_valid_names(string $value): void
    {
        $this->assertFalse($this->fails($value));
    }

    public static function invalidNames(): array
    {
        return [
            'contains digits' => ['John123'],
            'single stray letter' => ['J D Cruz'],
            'placeholder test' => ['test'],
            'placeholder asdf' => ['asdf'],
            'placeholder xxx' => ['xxx'],
            'placeholder n/a' => ['n/a'],
            'empty string' => [''],
            'whitespace only' => ['   '],
            'symbols' => ['John@Doe'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_names(string $value): void
    {
        $this->assertTrue($this->fails($value));
    }
}
