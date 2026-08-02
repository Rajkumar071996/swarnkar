<?php

namespace App\Support\Otp;

use App\Models\ConsentRequest;
use App\Models\Customer;

/**
 * Delivery mechanism for the consent OTP. Contracting an SMS provider means
 * adding one implementation and flipping GOLDSCORE_OTP_DRIVER; nothing in the
 * consent flow itself changes.
 */
interface OtpChannel
{
    public function generate(): string;

    public function send(Customer $customer, string $code, ConsentRequest $consent): void;

    public function describe(): string;
}
