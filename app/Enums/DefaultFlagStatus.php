<?php

namespace App\Enums;

enum DefaultFlagStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Disputed = 'disputed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Verified => 'Verified',
            self::Disputed => 'Disputed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Verified => 'bg-danger',
            self::Pending => 'bg-warning text-dark',
            self::Disputed => 'bg-info text-dark',
            self::Withdrawn => 'bg-secondary',
        };
    }
}
