<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\ImportNseValuationAction;
use App\Enums\NseFileEnum;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportNseMarketcapCommand extends Command
{
    protected $signature = 'backtest:import-marketcap {--date=}';

    protected $description = 'Import NSE market cap in crores for the selected date';

    public function handle(ImportNseValuationAction $importNseValuation): int
    {
        try {
            $result = $importNseValuation->execute($this->option('date'), NseFileEnum::Marketcap);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Market cap saved in crores for {$result['matched']} price rows. {$result['unmatched']} file rows had no matching price row.");

        return self::SUCCESS;
    }
}
