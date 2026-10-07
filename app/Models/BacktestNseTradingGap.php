<?php

namespace App\Models;

use Database\Factories\BacktestNseTradingGapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BacktestNseTradingGap extends Model
{
    /** @use HasFactory<BacktestNseTradingGapFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['symbol', 'last_traded_date', 'confirmation_date', 'resumed_date'];

    protected function casts(): array
    {
        return ['last_traded_date' => 'date', 'confirmation_date' => 'date', 'resumed_date' => 'date'];
    }
}
