<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketHeartbeat extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
