<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\ImportNseValuationAction;
use App\Enums\NseFileEnum;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportNsePriceToEarningsCommand extends Command
{
    protected $signature = 'backtest:import-price-to-earnings {--date=}';

    protected $description = 'Import NSE ADJUSTED P/E values for the selected date';

    public function handle(ImportNseValuationAction $importNseValuation): int
    {
        try {
            $result = $importNseValuation->execute($this->option('date'), NseFileEnum::PriceToEarnings);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("ADJUSTED P/E saved for {$result['matched']} price rows. {$result['unmatched']} file rows had no matching price row.");

        return self::SUCCESS;
    }
}
