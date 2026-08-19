<?php

namespace App\Models;

use App\Enums\AdminProcessStepStatusEnum;
use Database\Factories\AdminProcessStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminProcessStep extends Model
{
    /** @use HasFactory<AdminProcessStepFactory> */
    use HasFactory;

    protected $fillable = [
        'admin_process_run_id',
        'position',
        'key',
        'name',
        'description',
        'command',
        'arguments',
        'command_line',
        'is_preview',
        'status',
        'attempts',
        'exit_code',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'is_preview' => 'boolean',
            'status' => AdminProcessStepStatusEnum::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AdminProcessRun::class, 'admin_process_run_id');
    }

    public function outputChunks(): HasMany
    {
        return $this->hasMany(AdminProcessOutputChunk::class)->orderBy('id');
    }
}
