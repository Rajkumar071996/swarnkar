<?php

namespace App\Support\Otp;

use App\Models\ConsentRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

/**
 * Issues a genuinely random code but writes it to the OTP log instead of an SMS
 * gateway. Useful for rehearsing the real flow without a provider.
 */
class LogOtpChannel implements OtpChannel
{
    public function generate(): string
    {
        $length = (int) config('goldscore.consent.otp_length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
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
        return 'A one-time code was written to storage/logs/otp.log.';
    }
}
