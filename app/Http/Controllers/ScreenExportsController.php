<?php

namespace App\Http\Controllers;

use App\Actions\QueueScreenExportAction;
use App\Enums\ScreenExportStatusEnum;
use App\Http\Requests\StoreScreenExportRequest;
use App\Models\Screen;
use App\Models\ScreenExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ScreenExportsController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Screens/Exports', [
            'screens' => fn () => Screen::query()
                ->where(fn (Builder $query) => $query->where('user_id', $request->user()->id)->orWhereIn('id', Screen::PUBLIC_SCREENS))
                ->orderBy('name')->get(['id', 'name'])->map(fn (Screen $screen): array => [
                    'id' => $screen->id,
                    'name' => $screen->name.(in_array($screen->id, Screen::PUBLIC_SCREENS, true) ? ' (Example)' : ''),
                ]),
            'exports' => fn () => ScreenExport::query()->where('user_id', $request->user()->id)
                ->select(['id', 'screen_name', 'start_date', 'end_date', 'status', 'processed_dates', 'total_dates',
                    'file_count', 'row_count', 'warning_count', 'error_message', 'expires_at', 'created_at', 'path'])
                ->latest('id')->paginate(15)->through(fn (ScreenExport $export): array => [
                    'id' => $export->id,
                    'screen_name' => $export->screen_name,
                    'start_date' => $export->start_date->toDateString(),
                    'end_date' => $export->end_date->toDateString(),
                    'status' => $export->status === ScreenExportStatusEnum::Completed && $export->expires_at?->isPast()
                        ? ScreenExportStatusEnum::Expired->value : $export->status->value,
                    'processed_dates' => $export->processed_dates,
                    'total_dates' => $export->total_dates,
                    'file_count' => $export->file_count,
                    'row_count' => $export->row_count,
                    'warning_count' => $export->warning_count,
                    'error_message' => $export->error_message,
                    'expires_at' => $export->expires_at?->toIso8601String(),
                    'created_at' => $export->created_at->toIso8601String(),
                    'download_url' => $export->isDownloadable() ? route('screen-exports.download', $export) : null,
                ]),
            'hasActiveExport' => fn () => ScreenExport::query()->where('user_id', $request->user()->id)
                ->whereIn('status', [ScreenExportStatusEnum::Queued, ScreenExportStatusEnum::Running])->exists(),
            'selectedScreenId' => $request->integer('screen') ?: null,
            'defaultStartDate' => today()->subDays(ScreenExport::MAX_DAYS - 1)->toDateString(),
            'defaultEndDate' => today()->toDateString(),
            'maxDays' => ScreenExport::MAX_DAYS,
            'retentionDays' => ScreenExport::RETENTION_DAYS,
            'storeUrl' => route('screen-exports.store'),
        ]);
    }

    public function store(StoreScreenExportRequest $request, QueueScreenExportAction $queue): RedirectResponse
    {
        $screen = Screen::findOrFail($request->validated('screen_id'));
        if ($request->user()->cannot('view', $screen)) {
            abort(404);
        }

        $export = $queue->execute($request->user(), $screen, $request->validated('start_date'), $request->validated('end_date'));

        return redirect()->route('screen-exports.index', ['screen' => $screen->id])
            ->with($export->status === ScreenExportStatusEnum::Failed ? 'error' : 'success',
                $export->status === ScreenExportStatusEnum::Failed ? $export->error_message : 'Your screen export is queued. You can leave this page and return to download it.');
    }
}
