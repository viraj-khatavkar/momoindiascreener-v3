<?php

namespace App\Enums;

enum BacktestCashCallSourceEnum: string
{
    case Index = 'index';
    case Backtest = 'backtest';
}
