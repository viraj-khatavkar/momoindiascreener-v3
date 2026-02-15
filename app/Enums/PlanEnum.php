<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum PlanEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Forever = 'forever';
    case Newsletter = 'newsletter';

    public function getAmountInRupees(): int
    {
        return match ($this) {
            self::Monthly => 500,
            self::Yearly => 3999,
            self::Forever => 14999,
            self::Newsletter => 2999,
        };
    }

    public function getAmountInPaise(): int
    {
        return $this->getAmountInRupees() * 100;
    }

    public function monthsToAdd(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
            self::Forever => 999,
        };
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly Plan',
            self::Yearly => 'Yearly Plan',
            self::Forever => 'Forever (Lifetime) Plan',
            self::Newsletter => 'Newsletter Plan',
        };
    }
}
