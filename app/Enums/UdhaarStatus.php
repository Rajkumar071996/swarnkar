<?php

namespace App\Enums;

enum UdhaarStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Settled = 'settled';
    case Defaulted = 'defaulted';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::PartiallyPaid => 'Partially Paid',
            self::Settled => 'Settled',
            self::Defaulted => 'Defaulted',
            self::WrittenOff => 'Written Off',
        };
    }

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid, self::Defaulted], true);
    }

    public function isWrittenOff(): bool
    {
        return $this === self::WrittenOff;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Settled => 'bg-success',
            self::Open => 'bg-primary',
            self::PartiallyPaid => 'bg-info text-dark',
            self::Defaulted => 'bg-danger',
            self::WrittenOff => 'bg-secondary',
        };
    }
}
