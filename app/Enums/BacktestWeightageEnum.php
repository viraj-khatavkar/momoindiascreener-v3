<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum BacktestWeightageEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case EqualWeight = 'equal_weight';
    case EqualWeightRebalanced = 'equal_weight_rebalanced';
    case InverseVolatility = 'inverse_volatility';
}
