<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
    case GoldsmithManager = 'goldsmith_manager';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner Jeweller',
            self::Staff => 'Store Staff',
            self::GoldsmithManager => 'Goldsmith Manager',
        };
    }

    /**
     * Owners are the only role that may extend credit, report defaults, or
     * manage other users. Everyone else records payments and reads scores.
     */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
