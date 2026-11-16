<?php

namespace App\Jobs;

use App\Actions\GenerateScreenExportAction;
use App\Enums\ScreenExportStatusEnum;
use App\Models\ScreenExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateScreenExportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $screenExportId)
    {
        $this->onQueue('screen-exports');
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateScreenExportAction $generate): void
    {
        $claimed = ScreenExport::query()->whereKey($this->screenExportId)->where('status', ScreenExportStatusEnum::Queued)
            ->update(['status' => ScreenExportStatusEnum::Running, 'started_at' => now()]);
        if ($claimed === 0) {
            return;
        }

        set_time_limit(0);
        $export = ScreenExport::findOrFail($this->screenExportId);

        try {
            $path = $generate->execute($export);
            $export->update([
                'status' => ScreenExportStatusEnum::Completed,
                'path' => $path,
                'completed_at' => now(),
                'expires_at' => now()->addDays(ScreenExport::RETENTION_DAYS),
            ]);
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $export = ScreenExport::find($this->screenExportId);
        if (! $export || ! in_array($export->status, [ScreenExportStatusEnum::Queued, ScreenExportStatusEnum::Running], true)) {
            return;
        }

        $export->update([
            'status' => ScreenExportStatusEnum::Failed,
            'path' => null,
            'error_message' => $exception instanceof ValidationException
                ? implode(' ', Arr::flatten($exception->errors()))
                : 'The screen export could not complete. Please generate a new export. If the error continues, contact support.',
            'completed_at' => now(),
            'expires_at' => now()->addDays(ScreenExport::RETENTION_DAYS),
        ]);
        Storage::disk('local')->deleteDirectory($export->directory());
    }
}
