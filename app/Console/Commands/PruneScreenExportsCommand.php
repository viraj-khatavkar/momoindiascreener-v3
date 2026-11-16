<?php

namespace App\Console\Commands;

use App\Enums\ScreenExportStatusEnum;
use App\Models\ScreenExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('screens:prune-exports')]
#[Description('Remove expired historical screen ZIP files')]
class PruneScreenExportsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;
        $disk = Storage::disk('local');
        foreach (ScreenExport::query()->whereIn('status', [ScreenExportStatusEnum::Completed, ScreenExportStatusEnum::Failed])
            ->where('expires_at', '<=', now())->lazyById(100) as $export) {
            if (! $disk->deleteDirectory($export->directory())) {
                $this->error('Could not remove files for export '.$export->id.'.');

                return self::FAILURE;
            }
            $export->update(['status' => ScreenExportStatusEnum::Expired, 'path' => null, 'settings' => []]);
            $count++;
        }

        foreach (collect($disk->directories('screen-exports'))->chunk(250) as $directories) {
            $exportIds = $directories->map(fn (string $directory): string => basename($directory))
                ->filter(fn (string $id): bool => ctype_digit($id));
            $existingIds = ScreenExport::query()->whereIn('id', $exportIds)->pluck('id')->flip();
            foreach ($exportIds as $id) {
                if (! $existingIds->has($id) && ! $disk->deleteDirectory('screen-exports/'.$id)) {
                    $this->error('Could not remove files for deleted export '.$id.'.');

                    return self::FAILURE;
                }
            }
        }

        $this->info("Expired {$count} screen exports.");

        return self::SUCCESS;
    }
}
