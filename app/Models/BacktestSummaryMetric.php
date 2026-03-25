<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestSummaryMetric extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'max_drawdown_start_date' => 'date',
            'max_drawdown_end_date' => 'date',
            'rolling_returns_one_year' => 'array',
            'rolling_returns_three_year' => 'array',
            'rolling_returns_five_year' => 'array',
        ];
    }

    public function backtest(): BelongsTo
    {
        return $this->belongsTo(Backtest::class);
    }
}
