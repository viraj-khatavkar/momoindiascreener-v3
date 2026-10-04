<?php

namespace App\Enums;

use App\Contracts\ResolveDisplayableValueListForEnum;
use App\Traits\ResolveDisplayableValueListForEnumTrait;

enum BacktestStopLossProceedsEnum: string implements ResolveDisplayableValueListForEnum
{
    use ResolveDisplayableValueListForEnumTrait;

    case WaitForRebalance = 'wait_for_rebalance';
    case ReplaceImmediately = 'replace_immediately';
}
