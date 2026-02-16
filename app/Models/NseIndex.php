<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NseIndex extends Model
{
    protected $table = 'nse_indices';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'open' => 'decimal:2',
            'high' => 'decimal:2',
            'low' => 'decimal:2',
            'close' => 'decimal:2',
            'points_change' => 'decimal:2',
            'percentage_change' => 'decimal:2',
            'turnover' => 'decimal:2',
            'price_to_earnings' => 'decimal:2',
            'price_to_book' => 'decimal:2',
            'dividend_yield' => 'decimal:2',
        ];
    }
}
