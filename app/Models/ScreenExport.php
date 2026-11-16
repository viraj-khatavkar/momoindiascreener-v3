<?php

namespace App\Models;

use App\Enums\ScreenExportStatusEnum;
use Database\Factories\ScreenExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenExport extends Model
{
    /** @use HasFactory<ScreenExportFactory> */
    use HasFactory;

    public const MAX_DAYS = 30;

    public const RETENTION_DAYS = 7;

    protected $fillable = [
        'user_id', 'screen_id', 'screen_name', 'settings', 'start_date', 'end_date',
        'status', 'total_dates', 'processed_dates', 'file_count', 'row_count',
        'warning_count', 'path', 'error_message', 'started_at', 'completed_at', 'expires_at',
    ];

    protected $attributes = [
        'status' => 'queued', 'total_dates' => 0, 'processed_dates' => 0,
        'file_count' => 0, 'row_count' => 0, 'warning_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status' => ScreenExportStatusEnum::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function directory(): string
    {
        return 'screen-exports/'.$this->getKey();
    }

    public function isDownloadable(): bool
    {
        return $this->status === ScreenExportStatusEnum::Completed
            && $this->expires_at?->isFuture() && filled($this->path);
    }
}
