<?php

namespace App\Enums;

enum GoldLoanStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Renewed = 'renewed';
    case Auctioned = 'auctioned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Renewed => 'Renewed',
            self::Auctioned => 'Pledge Auctioned',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Closed => 'bg-success',
            self::Active => 'bg-primary',
            self::Renewed => 'bg-warning text-dark',
            self::Auctioned => 'bg-danger',
        };
    }
}
