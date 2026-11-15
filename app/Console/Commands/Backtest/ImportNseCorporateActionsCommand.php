<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ParseTrustCashAction;
use App\Actions\ReadCsvAction;
use App\Enums\CorporateActionTypeEnum;
use App\Models\BacktestNseCorporateAction;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportNseCorporateActionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backtest:import-corporate-actions {--date=} {--series=} {--O|omit-create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports corporate actions from the NSE file into backtest_nse_corporate_actions';

    /**
     * Execute the console command.
     */
    public function handle(ParseTrustCashAction $parseCash): int
    {
        $this->info('Importing corporate actions for NSE...');
        $date = $this->option('date');
        $series = $this->option('series');
        $omitCreate = $this->option('omit-create');

        if (is_null($date)) {
            $this->error('Please provide a date');

            return Command::FAILURE;
        }

        if (is_null($series)) {
            $this->error('Please provide a series');

            return Command::FAILURE;
        }

        $date = (new Carbon($date))->format('Y-m-d');

        if (in_array($series, ['RR', 'IV'], true)) {
            return $this->importTrustCash($date, $series, (bool) $omitCreate, $parseCash);
        }
        $corporateActions = collect($this->fetchCorporateActionsFromWebsiteFile($date, $series));
        $previousFileDate = $this->findPreviousCorporateActionsFileDate($date);

        if ($previousFileDate !== null) {
            $corporateActions = $corporateActions->concat(
                $this->fetchCorporateActionsFromWebsiteFile($date, $series, $previousFileDate)
            );
        }

        $corporateActions = $corporateActions->uniqueStrict(fn (array $corporateAction): array => [
            $corporateAction['symbol'],
            $corporateAction['type'],
            $corporateAction['ratio'],
        ]);

        if ($corporateActions->isEmpty()) {
            $this->info('No corporate actions found');

            return Command::SUCCESS;
        }

        foreach ($corporateActions as $corporateAction) {
            $this->components->info($corporateAction['sentence'].' '.$corporateAction['ratio']);

            if ($omitCreate) {
                continue;
            }

            $hasPriceRecordForDate = BacktestNseInstrumentPrice::query()
                ->where('symbol', $corporateAction['symbol'])
                ->where('date', $date)
                ->exists();

            if (! $hasPriceRecordForDate) {
                $this->components->warn('No price record for '.$corporateAction['symbol'].' on '.$date);
            }

            $dividend = null;

            if ($corporateAction['type'] == CorporateActionTypeEnum::DIVIDEND) {
                $string = Str::of($corporateAction['sentence'])->afterLast('DIV');
                $floatNumber = filter_var($string, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $dividend = (string) Str::of($floatNumber)->trim('-.')->trim('-.');
            }

            BacktestNseCorporateAction::updateOrCreate([
                'symbol' => $corporateAction['symbol'],
                'date' => $date,
                'type' => $corporateAction['type'],
                'ratio' => $corporateAction['ratio'],
            ], [
                'series' => $corporateAction['series'],
                'description' => $corporateAction['sentence'],
                'dividend' => $dividend,
            ]);
        }

        $corporateActionsForToday = BacktestNseCorporateAction::query()
            ->where('date', $date)
            ->get();

        foreach ($corporateActionsForToday as $corporateAction) {
            $this->components->info($corporateAction->symbol.' '.$corporateAction->dividend);
        }

        return Command::SUCCESS;
    }

    private function importTrustCash(string $date, string $series, bool $preview, ParseTrustCashAction $parseCash): int
    {
        try {
            $bySymbol = [];
            $previousDate = $this->findPreviousCorporateActionsFileDate($date);

            foreach (array_filter([$date, $previousDate]) as $fileDate) {
                $fileRows = app(ReadCsvAction::class)->execute(Storage::path("uploads/{$fileDate}/corporate_actions.csv"))->toCollection()
                    ->filter(fn (array $row): bool => isset($row[9]) && trim($row[0]) === $series && trim($row[6]) === $date)
                    ->groupBy(fn (array $row): string => trim($row[1]));

                foreach ($fileRows as $symbol => $rows) {
                    if (isset($bySymbol[$symbol])) {
                        continue;
                    }

                    $cashRows = [];
                    foreach ($rows->unique(fn (array $row): string => Str::upper(Str::squish($row[9]))) as $row) {
                        $cash = $parseCash->execute($row[9]);
                        if ($cash !== null) {
                            $cashRows[] = [...$cash, 'description' => trim($row[9])];
                        }
                    }

                    if ($cashRows === []) {
                        continue;
                    }
                    if (count($cashRows) > 1 && collect($cashRows)->contains('type', CorporateActionTypeEnum::DISTRIBUTION)) {
                        throw new InvalidArgumentException("Check multiple distribution entries for {$symbol} on {$date} before import.");
                    }

                    $amount = $parseCash->sum(array_column($cashRows, 'amount'));
                    $description = implode(' / ', array_column($cashRows, 'description'));
                    if (Str::length($description) > 255) {
                        throw new InvalidArgumentException("Cash description exceeds 255 characters for {$symbol}.");
                    }
                    $bySymbol[$symbol] = [
                        'type' => count($cashRows) === 1 ? $cashRows[0]['type'] : CorporateActionTypeEnum::DISTRIBUTION,
                        'dividend' => $amount, 'ratio' => $amount, 'description' => $description,
                    ];
                }
            }

            foreach ($bySymbol as $symbol => $values) {
                $existing = BacktestNseCorporateAction::query()->where('symbol', $symbol)->whereDate('date', $date)->whereNotNull('dividend')->get();
                if ($existing->count() > 1 || ($existing->isNotEmpty() && (float) $existing->first()->dividend !== (float) $values['dividend'])) {
                    throw new InvalidArgumentException("Existing cash amount conflicts for {$symbol} on {$date}; rebuild its history first.");
                }
                $this->info("{$symbol} {$date}: cash ".$values['dividend']);
            }

            if (! $preview) {
                DB::transaction(function () use ($bySymbol, $date, $series): void {
                    foreach ($bySymbol as $symbol => $values) {
                        $action = BacktestNseCorporateAction::query()->where('symbol', $symbol)->whereDate('date', $date)->whereNotNull('dividend')->first();
                        if ($action === null) {
                            BacktestNseCorporateAction::create(['symbol' => $symbol, 'date' => $date, 'series' => $series, ...$values]);
                        }
                    }
                });
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function findPreviousCorporateActionsFileDate(string $date): ?string
    {
        $previousDate = BacktestNseInstrumentPrice::query()
            ->where('date', '<', $date)
            ->max('date');

        if ($previousDate === null) {
            return null;
        }

        if (! Storage::exists('uploads/'.$previousDate.'/corporate_actions.csv')) {
            $this->warn('No corporate actions file for '.$previousDate.', skipping previous trading day');

            return null;
        }

        return $previousDate;
    }

    /**
     * @return array<int, array{symbol: string, series: string, ratio: ?string, type: ?CorporateActionTypeEnum, sentence: string}>
     */
    protected function fetchCorporateActionsFromWebsiteFile(string $date, string $series, ?string $fileDate = null): array
    {
        /** @var ReadCsvAction $readCsvAction */
        $readCsvAction = app(ReadCsvAction::class);

        $filePath = Storage::path('uploads/'.($fileDate ?? $date).'/corporate_actions.csv');
        $this->info($filePath);

        $rows = $readCsvAction->execute($filePath)->toCollection();
        $searchKeys = ['BONUS', 'BON', 'FV SPL', 'FVSPLT', 'FV SPLT', 'SPLIT', 'DIV', 'INTDV', 'SPDV', 'DV', 'RIGHTS', 'RIGHT', 'RGHTS', 'RGHT', 'RHT', 'RGTS', 'DEMERGER'];

        $rows = $rows->reject(function ($row) {
            return ! isset($row[6]);
        })->filter(function ($row) use ($date, $searchKeys) {
            return trim($row[6]) === $date && Str::contains($row[9], $searchKeys);
        })->filter(function ($row) use ($series) {
            return trim($row[0]) == $series;
        })->map(function ($row) {
            $ratio = null;
            $type = null;
            if (Str::contains($row[9], 'SPLT')) {
                $ratio = $this->parseSentenceForSplit($row[9]);
                $type = CorporateActionTypeEnum::SPLIT;
            } elseif (Str::contains($row[9], 'BONUS')) {
                $ratio = $this->parseSentenceForBonus($row[9]);
                $type = CorporateActionTypeEnum::BONUS;
            } elseif (Str::contains($row[9], ['DIV', 'DV', 'INTDV', 'SPDV'])) {
                $ratio = $this->parseSentenceForDividend($row[9]);
                $type = CorporateActionTypeEnum::DIVIDEND;
            } elseif (Str::contains($row[9], ['RIGHTS', 'RIGHT', 'RGHTS', 'RGHT', 'RHT', 'RGTS'])) {
                $ratio = 'RIGHTS';
                $type = CorporateActionTypeEnum::RIGHTS;
            } elseif (Str::contains($row[9], 'DEMERGER')) {
                $ratio = 'DEMERGER';
                $type = CorporateActionTypeEnum::DEMERGER;
            }

            return [
                'symbol' => trim($row[1]),
                'series' => trim($row[0]),
                'ratio' => $ratio,
                'type' => $type,
                'sentence' => 'Corporate Action: '.$row[0].' '.$row[1].' '.$row[9],
            ];
        });

        return $rows->toArray();
    }

    public function parseSentenceForBonus($sentence)
    {
        $parts = explode(' ', $sentence);
        foreach ($parts as $part) {
            if (Str::contains($part, ':')) {
                return trim($part);
            }
        }
    }

    public function parseSentenceForSplit($sentence)
    {
        $splitFrom = null;
        $splitTo = null;
        $parts = explode(' ', $sentence);
        foreach ($parts as $part) {
            if (preg_match('~[0-9]+~', $part)) {
                preg_match_all('!\d+!', $part, $matches);

                if ($splitFrom) {
                    $splitTo = implode(' ', $matches[0]);
                } else {
                    $splitFrom = implode(' ', $matches[0]);
                }
            }
        }

        return $splitFrom.':'.$splitTo;
    }

    public function parseSentenceForDividend($sentence): string
    {
        if (preg_match('/(?:RS|RE)\s*(\d+(?:\.\d+)?)\s*PER\s*SH/i', $sentence, $matches)) {
            return $matches[1];
        }

        return '-';
    }
}
