<?php

namespace App\Models;

use App\Enums\MarketIndexAliasStatusEnum;
use Database\Factories\MarketIndexAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketIndexAlias extends Model
{
    /** @use HasFactory<MarketIndexAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'source_label',
        'normalized_label',
        'market_index_id',
        'suggested_market_index_id',
        'suggested_slug',
        'status',
        'sample_symbol',
        'first_seen_on',
        'last_seen_on',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketIndexAliasStatusEnum::class,
            'first_seen_on' => 'date',
            'last_seen_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function marketIndex(): BelongsTo
    {
        return $this->belongsTo(MarketIndex::class);
    }

    public function suggestedMarketIndex(): BelongsTo
    {
        return $this->belongsTo(MarketIndex::class, 'suggested_market_index_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(BacktestNseInstrument::class);
    }
}
