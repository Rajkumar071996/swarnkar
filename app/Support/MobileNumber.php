<?php

namespace App\Support;

/**
 * Normalises Indian mobile numbers the way a jeweller types them: with or
 * without +91 / 0 prefix, spaces, or dashes. Login and signup both go through
 * here so "98290 11223" and "919829011223" land on the same account.
 */
class MobileNumber
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    /**
     * @return array<int, string|\Illuminate\Validation\Rules\Unique>
     */
    public static function rules(bool $unique = false, ?int $ignoreUserId = null): array
    {
        $rules = ['required', 'string', 'regex:/^[6-9]\d{9}$/'];

        if ($unique) {
            $uniqueRule = \Illuminate\Validation\Rule::unique('users', 'phone');

            if ($ignoreUserId !== null) {
                $uniqueRule = $uniqueRule->ignore($ignoreUserId);
            }

            $rules[] = $uniqueRule;
        }

        return $rules;
    }
}
