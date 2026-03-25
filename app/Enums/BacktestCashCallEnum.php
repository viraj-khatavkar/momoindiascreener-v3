<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum BacktestCashCallEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case NoCashCall = 'no_cash_call';
    case CashCallIfNotEnoughStocks = 'cash_call_if_not_enough_stocks';
    case FullCashBelowIndexDma = 'full_cash_below_index_dma';
    case OnlyExitsBelowIndexDma = 'only_exits_below_index_dma';
    case AllocateToGoldBelowIndexDma = 'allocate_to_gold_below_index_dma';
    case OnlyExitsAllocateToGoldBelowIndexDma = 'only_exits_allocate_to_gold_below_index_dma';
}
