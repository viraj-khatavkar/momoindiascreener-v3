<?php

namespace App\Models;

use App\Enums\PlanEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'plan' => PlanEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'paid');
    }
}
