<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestDailySnapshot extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'market_cap_allocation' => 'array',
            'market_cap_allocation_calculated_at' => 'datetime',
        ];
    }

    public function backtest(): BelongsTo
    {
        return $this->belongsTo(Backtest::class);
    }
}
