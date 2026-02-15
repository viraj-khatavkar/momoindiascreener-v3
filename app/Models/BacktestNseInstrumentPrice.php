<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BacktestNseInstrumentPrice extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'corporate_actions' => 'array',
            'is_delisted' => 'boolean',
            'is_nifty_50' => 'boolean',
            'is_nifty_next_50' => 'boolean',
            'is_nifty_100' => 'boolean',
            'is_nifty_200' => 'boolean',
            'is_nifty_midcap_100' => 'boolean',
            'is_nifty_500' => 'boolean',
            'is_nifty_smallcap_250' => 'boolean',
            'is_nifty_allcap' => 'boolean',
            'is_etf' => 'boolean',
        ];
    }
}
