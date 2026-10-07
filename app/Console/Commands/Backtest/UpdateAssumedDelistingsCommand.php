<?php

namespace App\Console\Commands\Backtest;

use App\Actions\Backtest\UpdateAssumedDelistingsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class UpdateAssumedDelistingsCommand extends Command
{
    protected $signature = 'backtest:update-assumed-delistings {--date= : Process through this market date} {--rebuild : Rebuild all saved gaps after historical corrections}';

    protected $description = 'Update shared trading gaps used by every backtest for assumed delisting exits';

    public function handle(UpdateAssumedDelistingsAction $update): int
    {
        $date = $this->option('date');
        if (Validator::make(['date' => $date], ['date' => ['nullable', 'date_format:Y-m-d']])->fails()) {
            $this->error('Please provide a valid date in YYYY-MM-DD format.');

            return self::FAILURE;
        }

        $this->info('Updating shared assumed-delisting data...');
        try {
            $result = $update->execute($date, (bool) $this->option('rebuild'), function (string $message): void {
                $this->line($message);
            });
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($result['rebuilt'] ? 'Rebuilt' : 'Updated').' through '.$result['processed_through'].'. Confirmed gaps: '.$result['confirmed_gaps'].'.');

        return self::SUCCESS;
    }
}
