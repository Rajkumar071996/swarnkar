<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aadhaar numbers carry a Verhoeff check digit. Validating it catches the
 * transposed digits that are common when a number is read out at the counter,
 * which matters because a wrong number would silently create a second identity.
 */
class AadhaarNumber implements ValidationRule
{
    private const D = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];

    private const P = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) !== 12) {
            $fail('The Aadhaar number must be 12 digits.');

            return;
        }

        if ($digits[0] === '0' || $digits[0] === '1') {
            $fail('The Aadhaar number is not valid.');

            return;
        }

        if (! $this->passesVerhoeff($digits)) {
            $fail('The Aadhaar number failed its checksum. Please re-enter it.');
        }
    }

    private function passesVerhoeff(string $digits): bool
    {
        $checksum = 0;

        foreach (array_reverse(str_split($digits)) as $position => $digit) {
            $checksum = self::D[$checksum][self::P[$position % 8][(int) $digit]];
        }

        return $checksum === 0;
    }
}
