<?php

if (! function_exists('money')) {
    /**
     * Formats an amount in the Indian grouping convention (1,50,000 rather than
     * 150,000), which is what a jeweller expects to read on a khata.
     */
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $negative = $amount < 0;
        $amount = abs($amount);

        $rounded = number_format($amount, 2, '.', '');
        [$whole, $decimals] = explode('.', $rounded);

        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        $formatted = $decimals === '00' ? $whole : $whole.'.'.$decimals;

        return ($negative ? '-' : '').($withSymbol ? '₹' : '').$formatted;
    }
}

if (! function_exists('grams')) {
    function grams(float|int|string|null $weight): string
    {
        return number_format((float) ($weight ?? 0), 3).' g';
    }
}
