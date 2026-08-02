<?php

namespace App\Enums;

enum RiskBand: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    /**
     * A customer with no scoreable history at all. Deliberately distinct from
     * Red: absence of evidence is not evidence of default, and treating every
     * walk-in as a proven defaulter would make the product cry wolf.
     */
    case Unscored = 'unscored';

    public static function forScore(?int $score): self
    {
        if ($score === null) {
            return self::Unscored;
        }

        return match (true) {
            $score >= config('goldscore.bands.green') => self::Green,
            $score >= config('goldscore.bands.yellow') => self::Yellow,
            default => self::Red,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Excellent',
            self::Yellow => 'Moderate Risk',
            self::Red => 'High Risk',
            self::Unscored => 'No History',
        };
    }

    public function riskLabel(): string
    {
        return match ($this) {
            self::Green => 'Low Risk',
            self::Yellow => 'Moderate Risk',
            self::Red => 'High Risk',
            self::Unscored => 'Unrated',
        };
    }

    public function recommendation(): string
    {
        return match ($this) {
            self::Green => 'Low risk. Safe for high-value store credit and 0% EMI offers.',
            self::Yellow => 'Moderate risk. Restrict credit to 30% of order value and take collateral.',
            self::Red => 'High risk. Advance cash payment only, no store credit.',
            self::Unscored => 'No transaction history on the network. Take advance payment or refer to the owner.',
        };
    }

    public function cssClass(): string
    {
        return 'gs-band-'.$this->value;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Green => 'bg-success',
            self::Yellow => 'bg-warning text-dark',
            self::Red => 'bg-danger',
            self::Unscored => 'bg-secondary',
        };
    }
}
