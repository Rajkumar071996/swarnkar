<?php

namespace App\Enums;

enum DefaultFlagReason: string
{
    case BouncedCheque = 'bounced_cheque';
    case FakeGold = 'fake_gold';
    case Absconded = 'absconded';
    case UdhaarDefault = 'udhaar_default';

    public function label(): string
    {
        return match ($this) {
            self::BouncedCheque => 'Bounced Cheque',
            self::FakeGold => 'Fake Gold Attempt',
            self::Absconded => 'Absconded With Store Credit',
            self::UdhaarDefault => 'Udhaar Default',
        };
    }

    public function severity(): float
    {
        return (float) (config('goldscore.flags.severity')[$this->value]
            ?? config('goldscore.flags.default_severity'));
    }
}
