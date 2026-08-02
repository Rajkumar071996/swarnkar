<?php

namespace App\Enums;

enum ConsentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Expired = 'expired';
    case Failed = 'failed';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting OTP',
            self::Verified => 'Consent Granted',
            self::Expired => 'Expired',
            self::Failed => 'Failed - Too Many Attempts',
            self::Revoked => 'Revoked By Customer',
        };
    }
}
