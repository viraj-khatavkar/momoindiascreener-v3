<?php

namespace App\Models;

use Database\Factories\MarketIndexFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketIndex extends Model
{
    /** @use HasFactory<MarketIndexFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'provider',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MarketIndexAlias::class);
    }
}
