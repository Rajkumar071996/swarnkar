<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Score range
    |--------------------------------------------------------------------------
    |
    | A component ratio of 0.0 maps to the floor and 1.0 maps to the ceiling.
    |
    */

    'range' => [
        'min' => 300,
        'max' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Component weights
    |--------------------------------------------------------------------------
    |
    | Weights are renormalised across only the components that actually have
    | data for a customer, so a customer with no gold loan history is scored
    | on their remaining history rather than penalised for the gap.
    |
    */

    'weights' => [
        'udhaar' => 60,
        'gold_loan' => 20,
        'flags' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk bands
    |--------------------------------------------------------------------------
    |
    | Evaluated as "score >= min". Anything below the lowest band is red.
    |
    */

    'bands' => [
        'green' => 750,
        'yellow' => 650,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recency weighting
    |--------------------------------------------------------------------------
    |
    | Every observation carries a weight that decays with age, halving once per
    | half_life_months. Observations older than lookback_months are ignored.
    |
    */

    'recency' => [
        'lookback_months' => 36,
        'half_life_months' => 18,
        'min_weight' => 0.15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Udhar khata / store credit component
    |--------------------------------------------------------------------------
    |
    | Settlement credit is chosen by how many days past the due date the account
    | was cleared. Tiers are read in ascending order of days_late.
    |
    */

    'udhaar' => [
        'settlement_tiers' => [
            ['days_late' => 0, 'credit' => 1.0],
            ['days_late' => 30, 'credit' => 0.7],
            ['days_late' => 60, 'credit' => 0.4],
        ],
        'beyond_tier_credit' => 0.1,
        // An account still open this far past its due date scores zero.
        'open_overdue_zero_days' => 60,
        'default_days' => 60,
        'min_observations' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pledged gold loan component
    |--------------------------------------------------------------------------
    */

    'gold_loan' => [
        'closed_on_time_credit' => 1.0,
        'closed_late_credit' => 0.6,
        'renewed_credit' => 0.5,
        'open_overdue_credit' => 0.1,
        'auctioned_credit' => 0.0,
        'grace_days' => 15,
        'min_observations' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant feedback / default flags component
    |--------------------------------------------------------------------------
    |
    | Starts at a clean 1.0 and deducts per verified flag. Only verified flags
    | count, so a competitor cannot dent a score by filing an unbacked report.
    |
    */

    'flags' => [
        'severity' => [
            'bounced_cheque' => 0.35,
            'fake_gold' => 1.0,
            'absconded' => 1.0,
            'udhaar_default' => 0.4,
        ],
        'default_severity' => 0.3,
        'decay_after_months' => 24,
        'decayed_multiplier' => 0.4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommended store credit limit
    |--------------------------------------------------------------------------
    |
    | Capacity is inferred from what the customer has already proven they can
    | repay, then scaled by band. Yellow customers are additionally capped at a
    | share of the order value at the point of sale.
    |
    | Whatever the customer already owes across the network is then subtracted,
    | so the figure on screen is headroom to lend today rather than a total
    | exposure the shop would be the second lender into.
    |
    */

    'credit_limit' => [
        'multipliers' => [
            'green' => 1.5,
            'yellow' => 0.5,
            'red' => 0.0,
            'unscored' => 0.0,
        ],
        'rounding' => 5000,
        'ceiling' => 500000,
        'yellow_order_value_share' => 0.30,
        // Ignore trivially small leftovers when netting off existing exposure.
        'exposure_tolerance' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent (DPDP)
    |--------------------------------------------------------------------------
    |
    | No score is revealed without a verified consent, and each grant is valid
    | for a short window rather than indefinitely.
    |
    */

    'consent' => [
        'otp_length' => 4,
        'otp_ttl_minutes' => 10,
        'max_attempts' => 5,
        'grant_ttl_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP delivery
    |--------------------------------------------------------------------------
    |
    | The static driver always issues GOLDSCORE_OTP_STATIC_CODE and writes the
    | delivery to the log. Swap the driver once an SMS provider is contracted.
    |
    */

    'otp' => [
        'driver' => env('GOLDSCORE_OTP_DRIVER', 'static'),
        'static_code' => env('GOLDSCORE_OTP_STATIC_CODE', '9999'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blind index
    |--------------------------------------------------------------------------
    |
    | Encrypted PII cannot be queried, so mobile/PAN/Aadhaar each carry a
    | deterministic HMAC alongside the ciphertext to support exact-match lookup.
    |
    */

    'blind_index_key' => env('GOLDSCORE_BLIND_INDEX_KEY'),

];
