<?php

namespace App\Actions;

use App\Actions\Backtest\BacktestSortDateAction;
use App\Actions\Backtest\BacktestTradingCalendarAction;
use App\Enums\AdminProcessRunStatusEnum;
use App\Models\AdminProcessRun;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use App\Models\ScreenExport;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\SimpleExcel\SimpleExcelWriter;
use ZipArchive;

class GenerateScreenExportAction
{
    public function __construct(
        private ApplyScreenFiltersAction $filters,
        private WriteScreenCsvAction $writer,
        private BacktestTradingCalendarAction $calendar,
        private BacktestSortDateAction $sortDates,
    ) {}

    public function execute(ScreenExport $export): string
    {
        if ($export->start_date->gt($export->end_date)
            || $export->start_date->diffInDays($export->end_date) >= ScreenExport::MAX_DAYS
            || $export->start_date->toDateString() < '2010-01-04'
            || $export->end_date->gt(today())) {
            throw ValidationException::withMessages(['export' => 'The export requires a past or current date range of no more than 30 calendar days.']);
        }

        $minimumDate = $this->sortDates->minimumDate($export->settings);
        if ($minimumDate > BacktestSortDateAction::DEFAULT_START_DATE && $export->start_date->toDateString() < $minimumDate) {
            throw ValidationException::withMessages(['export' => str_replace(':date', $minimumDate, BacktestSortDateAction::VALIDATION_MESSAGE)]);
        }

        $from = $export->start_date->toDateString();
        $through = $export->end_date->toDateString();
        $tradingDates = $this->calendar->priceDates($from, $through)->pluck('date')->flip();
        if ($tradingDates->isEmpty()) {
            throw ValidationException::withMessages(['export' => 'No trading data is available in the selected date range.']);
        }

        $unfinishedDates = AdminProcessRun::query()->whereBetween('process_date', [$from, $through])
            ->where('status', '!=', AdminProcessRunStatusEnum::Completed)->toBase()->pluck('process_date')->flip();
        $screen = (new Screen)->forceFill($export->settings);
        $disk = Storage::disk('local');
        $directory = $export->directory().'/working';
        if (! $disk->makeDirectory($directory)) {
            throw new RuntimeException('Could not create the export directory.');
        }

        $summary = [];
        $files = [];
        $published = false;
        $archive = new ZipArchive;
        $archiveOpen = false;

        try {
            foreach (CarbonPeriod::create($from, $through) as $date) {
                $dateString = $date->toDateString();
                if ($unfinishedDates->has($dateString)) {
                    $result = $this->unavailable($dateString, 'Daily data processing is not complete.');
                } elseif (! $tradingDates->has($dateString)) {
                    $result = ['date' => $dateString, 'status' => 'no_market_data', 'rows' => 0, 'file' => '',
                        'warnings' => [], 'note' => 'No stored market prices. Weekends and holidays normally have no file.'];
                } else {
                    $result = DB::transaction(fn (): array => $this->exportDate($screen, $dateString, $disk->path($directory)));
                }

                if ($result['file'] !== '') {
                    $files[] = $result['file'];
                }

                $summary[] = $result;
                $export->update([
                    'processed_dates' => count($summary),
                    'file_count' => count($files),
                    'row_count' => $export->row_count + $result['rows'],
                    'warning_count' => $export->warning_count + count($result['warnings']),
                ]);
            }

            if ($files === []) {
                throw ValidationException::withMessages(['export' => 'No daily CSV files could be created. Prices or ranking data are unavailable, or daily processing is incomplete.']);
            }

            $summaryWriter = SimpleExcelWriter::create($disk->path($directory.'/summary.csv'), 'csv');
            try {
                $summaryWriter->addHeader(['date', 'status', 'matching_stocks', 'file', 'warnings', 'note']);
                foreach ($summary as $day) {
                    $summaryWriter->addRow([$day['date'], $day['status'], $day['rows'], $day['file'], implode(' ', $day['warnings']), $day['note']]);
                }
            } finally {
                $summaryWriter->close();
            }

            if (! $disk->put($directory.'/settings.json', json_encode([
                'screen_name' => $export->screen_name,
                'start_date' => $from,
                'end_date' => $through,
                'generated_at' => now()->toIso8601String(),
                'settings' => $export->settings,
                'notes' => 'Each daily CSV uses its filename date. The saved single historical date is ignored. Results use the historical data currently stored.',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Could not write the export settings.');
            }

            $temporaryPath = $export->directory().'/export.tmp.zip';
            if ($archive->open($disk->path($temporaryPath), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the ZIP file.');
            }
            $archiveOpen = true;

            foreach ([...$files, 'summary.csv', 'settings.json'] as $file) {
                if (! $archive->addFile($disk->path($directory.'/'.$file), $file)) {
                    throw new RuntimeException('Could not add a CSV file to the ZIP.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Could not complete the ZIP file.');
            }
            $archiveOpen = false;

            $path = $export->directory().'/export.zip';
            if (! $disk->move($temporaryPath, $path)) {
                throw new RuntimeException('Could not save the ZIP file.');
            }
            $published = true;

            return $path;
        } finally {
            if ($archiveOpen) {
                $archive->close();
            }
            $disk->deleteDirectory($directory);
            if (! $published) {
                $disk->deleteDirectory($export->directory());
            }
        }
    }

    /** @return array{date: string, status: string, rows: int, file: string, warnings: list<string>, note: string} */
    private function exportDate(Screen $screen, string $date, string $directory): array
    {
        $quotes = BacktestNseInstrumentPrice::query()->where('date', $date)->where($screen->index->isIndexFieldName(), true);
        if (! $quotes->exists()) {
            return $this->unavailable($date, 'Historical membership or prices are unavailable for the selected universe.');
        }

        $factors = [$screen->sort_by];
        if ($screen->apply_factor_two) {
            $factors[] = $screen->factor_two_sort_by;
        }
        if ($screen->apply_factor_three) {
            $factors[] = $screen->factor_three_sort_by;
        }

        foreach (array_unique($factors) as $factor) {
            if (! (clone $quotes)->whereNotNull($factor)->exists()) {
                return $this->unavailable($date, 'The ranking field '.$factor.' has no data for the selected universe.');
            }
        }

        $results = $this->filters->executeOnDate($screen, $date, true);
        $this->writer->execute($directory.'/'.$date.'.csv', $results, $screen);

        return ['date' => $date, 'status' => $results->isEmpty() ? 'no_matches' : 'exported',
            'rows' => $results->count(), 'file' => $date.'.csv', 'warnings' => $this->filters->getValuationFilterWarnings(), 'note' => ''];
    }

    /** @return array{date: string, status: string, rows: int, file: string, warnings: list<string>, note: string} */
    private function unavailable(string $date, string $warning): array
    {
        return ['date' => $date, 'status' => 'unavailable', 'rows' => 0, 'file' => '', 'warnings' => [$warning], 'note' => ''];
    }
}
