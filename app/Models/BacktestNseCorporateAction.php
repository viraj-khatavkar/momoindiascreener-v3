<?php

namespace App\Models;

use App\Enums\CorporateActionTypeEnum;
use Illuminate\Database\Eloquent\Model;

class BacktestNseCorporateAction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => CorporateActionTypeEnum::class,
            'dividend_adjustment_applied_at' => 'datetime',
            'price_adjustment_applied_at' => 'datetime',
        ];
    }
}
