<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt numbering
    |--------------------------------------------------------------------------
    |
    | Deposits are booked as GRT-19/27-4 and releases as GRS-19/27-17, matching
    | the numbering the counter already reads out loud. The serial runs per
    | store and per book, so two shops never collide.
    |
    */

    'receipt' => [
        'deposit_prefix' => env('GIRVI_DEPOSIT_PREFIX', 'GRT'),
        'release_prefix' => env('GIRVI_RELEASE_PREFIX', 'GRS'),
        'book_code' => env('GIRVI_BOOK_CODE', '19/27'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Valuation
    |--------------------------------------------------------------------------
    |
    | Purity is entered as a percentage of the net weight, not as karat, so a
    | 22 karat ornament is entered as 91.6 and fine weight follows from it.
    |
    */

    'estimate_percent' => 75,

    'rate_per_gram' => [
        'gold' => 14000,
        'silver' => 90,
    ],

    'metal_types' => [
        'gold' => 'Gold',
        'silver' => 'Silver',
    ],

    'item_types' => [
        'Ring', 'Chain', 'Necklace', 'Bangle', 'Bracelet',
        'Earrings', 'Pendant', 'Anklet', 'Coin', 'Biscuit', 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Loan terms
    |--------------------------------------------------------------------------
    |
    | The rate is an annual percentage. Interest is simple, and a part month is
    | charged as a full month, which is how the counter has always quoted it.
    |
    */

    'interest_rate' => 60,

    'duration_months' => 6,

    'loan_reasons' => [
        'Transaction Loan', 'Agriculture Loan', 'Personal Loan', 'Business Loan', 'Other',
    ],

    'loan_types' => [
        'Ornaments', 'Coin', 'Bullion',
    ],

];
