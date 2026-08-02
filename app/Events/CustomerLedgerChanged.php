<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised whenever something happens that could move a customer's score: credit
 * issued or cleared, a payment recorded, a default flag verified.
 */
class CustomerLedgerChanged
{
    use Dispatchable;

    public function __construct(public readonly Customer $customer, public readonly string $reason) {}
}
