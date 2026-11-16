<?php

namespace App\Actions;

use App\Actions\Backtest\BacktestSortDateAction;
use App\Enums\ScreenExportStatusEnum;
use App\Jobs\GenerateScreenExportJob;
use App\Models\Screen;
use App\Models\ScreenExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueueScreenExportAction
{
    public function __construct(private BacktestSortDateAction $sortDates) {}

    public function execute(User $user, Screen $screen, string $startDate, string $endDate): ScreenExport
    {
        $minimumDate = $this->sortDates->minimumDate($screen->getAttributes());
        if ($minimumDate > BacktestSortDateAction::DEFAULT_START_DATE && $startDate < $minimumDate) {
            throw ValidationException::withMessages(['start_date' => str_replace(':date', $minimumDate, BacktestSortDateAction::VALIDATION_MESSAGE)]);
        }

        $export = DB::transaction(function () use ($user, $screen, $startDate, $endDate): ScreenExport {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail(['id']);

            if (ScreenExport::query()->where('user_id', $user->id)
                ->whereIn('status', [ScreenExportStatusEnum::Queued, ScreenExportStatusEnum::Running])->exists()) {
                throw ValidationException::withMessages(['export' => 'Wait until your current screen export finishes before starting another.']);
            }

            return ScreenExport::create([
                'user_id' => $user->id,
                'screen_id' => $screen->id,
                'screen_name' => $screen->name,
                'settings' => Arr::except($screen->attributesToArray(), ['id', 'user_id', 'created_at', 'updated_at']),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_dates' => (int) CarbonImmutable::parse($startDate)->diffInDays(CarbonImmutable::parse($endDate)) + 1,
            ]);
        });

        $job = new GenerateScreenExportJob($export->id);

        try {
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            $job->failed($exception);
            report($exception);
        }

        return $export->refresh();
    }
}
