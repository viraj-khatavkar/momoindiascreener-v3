<?php

namespace App\Models;

use Database\Factories\AdminProcessOutputChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProcessOutputChunk extends Model
{
    /** @use HasFactory<AdminProcessOutputChunkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'admin_process_step_id',
        'stream',
        'output',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(AdminProcessStep::class, 'admin_process_step_id');
    }
}
