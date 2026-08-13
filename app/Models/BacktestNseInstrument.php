<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestNseInstrument extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'etf_index_source_date' => 'date',
        ];
    }

    public function marketIndexAlias(): BelongsTo
    {
        return $this->belongsTo(MarketIndexAlias::class);
    }
}
