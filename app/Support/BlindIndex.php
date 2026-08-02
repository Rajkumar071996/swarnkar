<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Encrypted columns cannot be queried, so every searchable identifier is stored
 * twice: as ciphertext for display and as a keyed HMAC for exact-match lookup.
 * A plain hash would be trivially rainbow-tabled over a 10-digit mobile number,
 * hence the keyed HMAC.
 */
class BlindIndex
{
    public static function forMobile(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        // Accept 9876543210, +919876543210 and 09876543210 as the same person.
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? self::hash('mobile:'.$digits) : null;
    }

    public static function forPan(?string $value): ?string
    {
        $pan = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        return strlen($pan) === 10 ? self::hash('pan:'.$pan) : null;
    }

    public static function forAadhaar(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen($digits) === 12 ? self::hash('aadhaar:'.$digits) : null;
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    protected static function key(): string
    {
        $key = config('goldscore.blind_index_key') ?: config('app.key');

        if (blank($key)) {
            throw new RuntimeException('Set GOLDSCORE_BLIND_INDEX_KEY before storing customer identifiers.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'));
        }

        return $key;
    }
}
