<?php

namespace App\Models;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\BacktestCashCallEnum;
use App\Enums\BacktestRebalanceFrequencyEnum;
use App\Enums\BacktestStatusEnum;
use App\Enums\BacktestWeightageEnum;
use App\Enums\NseIndexEnum;
use Database\Factories\BacktestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Backtest extends Model
{
    /** @use HasFactory<BacktestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BacktestStatusEnum::class,
            'rebalance_frequency' => BacktestRebalanceFrequencyEnum::class,
            'weightage' => BacktestWeightageEnum::class,
            'cash_call' => BacktestCashCallEnum::class,
            'index' => NseIndexEnum::class,
            'apply_filters_on' => ApplyFiltersOnOptionEnum::class,
            'apply_hold_above_dma' => 'boolean',
            'execute_next_trading_day' => 'boolean',
            'apply_ma' => 'boolean',
            'above_ma_200' => 'boolean',
            'above_ma_100' => 'boolean',
            'above_ma_50' => 'boolean',
            'above_ma_20' => 'boolean',
            'below_ma_200' => 'boolean',
            'below_ma_100' => 'boolean',
            'below_ma_50' => 'boolean',
            'below_ma_20' => 'boolean',
            'apply_ema' => 'boolean',
            'above_ema_200' => 'boolean',
            'above_ema_100' => 'boolean',
            'above_ema_50' => 'boolean',
            'above_ema_20' => 'boolean',
            'below_ema_200' => 'boolean',
            'below_ema_100' => 'boolean',
            'below_ema_50' => 'boolean',
            'below_ema_20' => 'boolean',
            'apply_pe' => 'boolean',
            'series_eq' => 'boolean',
            'series_be' => 'boolean',
            'apply_factor_two' => 'boolean',
            'apply_factor_three' => 'boolean',
            'apply_custom_filter_one' => 'boolean',
            'apply_custom_filter_two' => 'boolean',
            'apply_custom_filter_three' => 'boolean',
            'apply_custom_filter_four' => 'boolean',
            'apply_custom_filter_five' => 'boolean',
            'start_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(BacktestTrade::class);
    }

    public function dailySnapshots(): HasMany
    {
        return $this->hasMany(BacktestDailySnapshot::class);
    }

    public function summaryMetrics(): HasOne
    {
        return $this->hasOne(BacktestSummaryMetric::class);
    }
}
