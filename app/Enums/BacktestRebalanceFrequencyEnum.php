<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum BacktestRebalanceFrequencyEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SixMonthly = 'six_monthly';
    case Yearly = 'yearly';

    public function monthInterval(): ?int
    {
        return match ($this) {
            self::Weekly, self::Fortnightly => null,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SixMonthly => 6,
            self::Yearly => 12,
        };
    }
}
