<?php

namespace App\Models;

use Database\Factories\BacktestNseTradingGapStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BacktestNseTradingGapState extends Model
{
    /** @use HasFactory<BacktestNseTradingGapStateFactory> */
    use HasFactory;

    protected $fillable = ['id', 'processed_through', 'requires_rebuild'];

    protected $attributes = ['requires_rebuild' => false];

    protected function casts(): array
    {
        return ['processed_through' => 'date', 'requires_rebuild' => 'boolean'];
    }
}
