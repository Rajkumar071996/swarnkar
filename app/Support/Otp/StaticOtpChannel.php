<?php

namespace App\Support\Otp;

use App\Models\ConsentRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Development driver: always issues the same code so the consent flow can be
 * exercised end to end before an SMS provider is contracted. The code is still
 * hashed and still expires, so swapping drivers changes delivery only.
 */
class StaticOtpChannel implements OtpChannel
{
    public function __construct(private readonly string $code = '9999') {}

    public function generate(): string
    {
        return $this->code;
    }

    public function send(Customer $customer, string $code, ConsentRequest $consent): void
    {
        Log::channel('otp')->info('Consent OTP issued', [
            'consent_request_id' => $consent->id,
            'customer_id' => $customer->id,
            'mobile' => $customer->maskedMobile(),
            'code' => $code,
            'expires_at' => $consent->otp_expires_at->toIso8601String(),
        ]);
    }

    public function describe(): string
    {
        return 'Development mode: the OTP is fixed at '.$this->code.'. No SMS is sent.';
    }
}
