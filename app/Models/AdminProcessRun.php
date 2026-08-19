<?php

namespace App\Models;

use App\Enums\AdminProcessRunStatusEnum;
use Database\Factories\AdminProcessRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminProcessRun extends Model
{
    /** @use HasFactory<AdminProcessRunFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'process_date',
        'status',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'process_date' => 'date',
            'status' => AdminProcessRunStatusEnum::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AdminProcessStep::class)->orderBy('position');
    }
}
