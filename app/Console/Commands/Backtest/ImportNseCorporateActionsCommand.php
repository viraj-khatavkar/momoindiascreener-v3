<?php

namespace App\Console\Commands\Backtest;

use App\Actions\ReadCsvAction;
use App\Enums\CorporateActionTypeEnum;
use App\Models\BacktestNseInstrumentPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
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

        $corporateActions = $this->fetchCorporateActionsFromWebsiteFile($date, $series);

        if (empty($corporateActions)) {
            $this->info('No corporate actions found');

            return Command::SUCCESS;
        }

        foreach ($corporateActions as $corporateAction) {
            $this->components->info($corporateAction['sentence'].' '.$corporateAction['ratio']);

            if ($omitCreate) {
                continue;
            }

            if ($corporateAction['type'] == CorporateActionTypeEnum::DIVIDEND) {
                $string = Str::of($corporateAction['sentence'])->afterLast('DIV');
                $floatNumber = filter_var($string, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $finalDivNumeric = Str::of($floatNumber)->trim('-.')->trim('-.');

                BacktestNseInstrumentPrice::query()
                    ->where('symbol', $corporateAction['symbol'])
                    ->where('date', $date)
                    ->update([
                        'dividend' => $finalDivNumeric,
                    ]);
            }

            BacktestNseInstrumentPrice::query()
                ->where('symbol', $corporateAction['symbol'])
                ->where('date', $date)
                ->update([
                    'corporate_actions' => [$corporateAction['sentence']],
                ]);
        }

        $corporateActionsForToday = BacktestNseInstrumentPrice::query()
            ->where('date', $date)
            ->whereNotNull('corporate_actions')
            ->get();

        foreach ($corporateActionsForToday as $corporateAction) {
            $this->components->info($corporateAction->symbol.' '.$corporateAction->dividend);
        }

        return Command::SUCCESS;
    }

    protected function fetchCorporateActionsFromWebsiteFile($date, $series): array
    {
        /** @var ReadCsvAction $readCsvAction */
        $readCsvAction = app(ReadCsvAction::class);

        $filePath = Storage::path('uploads/'.(new Carbon($date))->format('Y-m-d').'/corporate_actions.csv');
        $this->info($filePath);

        $rows = $readCsvAction->execute($filePath)->toCollection();
        $exDate = (new Carbon($date))->format('d/m/Y');
        $searchKeys = ['BONUS', 'BON', 'FV SPL', 'FVSPLT', 'FV SPLT', 'SPLIT', 'DIV', 'RIGHTS', 'RGHTS', 'RHT', 'DEMERGER'];

        $rows = $rows->reject(function ($row) {
            return ! isset($row[6]);
        })->filter(function ($row) use ($exDate, $searchKeys) {
            return trim($row[6]) == $exDate && Str::contains($row[9], $searchKeys);
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
            } elseif (Str::contains($row[9], 'DIV')) {
                $ratio = $this->parseSentenceForDividend($row[9]);
                $type = CorporateActionTypeEnum::DIVIDEND;
            } elseif (Str::contains($row[9], ['RIGHTS', 'RGHTS'])) {
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

    protected function fetchCorporateActionsFromServerFile($date): array
    {
        return [];
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
