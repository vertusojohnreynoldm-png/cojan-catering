<?php

namespace Tests\Unit\Rules;

use App\Rules\PhilippineMobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhilippineMobileNumberTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new PhilippineMobileNumber)->validate('phone', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public static function validNumbers(): array
    {
        return [
            'local format' => ['09171234567'],
            'plus-63 format' => ['+639171234567'],
            'bare-63 format' => ['639171234567'],
            'with spaces' => ['0917 123 4567'],
            'with dashes' => ['0917-123-4567'],
        ];
    }

    #[DataProvider('validNumbers')]
    public function test_accepts_valid_numbers(string $value): void
    {
        $this->assertFalse($this->fails($value));
    }

    public static function invalidNumbers(): array
    {
        return [
            'too short' => ['091712345'],
            'too long' => ['091712345678'],
            'contains letters' => ['0917abc4567'],
            'landline (02-prefix)' => ['028123456'],
            'landline (area code)' => ['0432881234'],
            'all-repeated junk (1s)' => ['09111111111'],
            'all-repeated junk (0s)' => ['09000000000'],
            'empty string' => [''],
            'whitespace only' => ['   '],
            'wrong mobile prefix shape' => ['08171234567'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_numbers(string $value): void
    {
        $this->assertTrue($this->fails($value));
    }

    public static function normalizeCases(): array
    {
        return [
            ['09171234567', '09171234567'],
            ['+639171234567', '09171234567'],
            ['639171234567', '09171234567'],
            ['0917 123 4567', '09171234567'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize_collapses_to_one_format(string $input, string $expected): void
    {
        $this->assertSame($expected, PhilippineMobileNumber::normalize($input));
    }
}
