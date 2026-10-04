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
    case RankWeighted = 'rank_weighted';
    case PriceWeighted = 'price_weighted';

    public function rebalancesHoldings(): bool
    {
        return $this !== self::EqualWeight;
    }

    public function usesRankOrPriceWeights(): bool
    {
        return in_array($this, [self::RankWeighted, self::PriceWeighted], true);
    }
}
