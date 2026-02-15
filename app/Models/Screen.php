<?php

namespace App\Models;

use App\Enums\ApplyFiltersOnOptionEnum;
use App\Enums\NseIndexEnum;
use Database\Factories\ScreenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Screen extends Model
{
    /** @use HasFactory<ScreenFactory> */
    use HasFactory;

    public const PUBLIC_SCREENS = [1, 2, 3];

    protected function casts(): array
    {
        return [
            'index' => NseIndexEnum::class,
            'apply_filters_on' => ApplyFiltersOnOptionEnum::class,
            'apply_ma' => 'boolean',
            'above_ma_200' => 'boolean',
            'above_ma_100' => 'boolean',
            'above_ma_50' => 'boolean',
            'above_ma_20' => 'boolean',
            'below_ma_200' => 'boolean',
            'below_ma_100' => 'boolean',
            'below_ma_50' => 'boolean',
            'below_ma_20' => 'boolean',
            'apply_pe' => 'boolean',
            'series_eq' => 'boolean',
            'series_be' => 'boolean',
            'apply_factor_two' => 'boolean',
            'apply_factor_three' => 'boolean',
            'apply_historical_date' => 'boolean',
            'historical_date' => 'date',
            'apply_custom_filter_one' => 'boolean',
            'apply_custom_filter_two' => 'boolean',
            'apply_custom_filter_three' => 'boolean',
            'apply_custom_filter_four' => 'boolean',
            'apply_custom_filter_five' => 'boolean',
            'columns' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
