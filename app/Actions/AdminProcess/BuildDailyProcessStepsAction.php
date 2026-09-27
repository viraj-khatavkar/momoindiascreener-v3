<?php

namespace App\Actions\AdminProcess;

use App\Enums\NseFileEnum;

class BuildDailyProcessStepsAction
{
    /**
     * @return list<array{key: string, name: string, description: string, command: string, arguments: list<string>, command_line: string, is_preview: bool}>
     */
    public function execute(string $date): array
    {
        return [
            $this->step(
                key: 'check-instruments',
                name: 'Check new instruments',
                description: 'Find new instruments without creating database records.',
                command: 'backtest:import-instruments',
                arguments: ['--omit-create', "--date={$date}"],
                isPreview: true,
            ),
            $this->step(
                key: 'import-instruments',
                name: 'Import instruments',
                description: 'Create or update the instruments for the selected date.',
                command: 'backtest:import-instruments',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'import-corporate-actions-be',
                name: 'Import BE corporate actions',
                description: 'Import corporate actions for the BE series.',
                command: 'backtest:import-corporate-actions',
                arguments: ['--series=BE', "--date={$date}"],
            ),
            $this->step(
                key: 'import-corporate-actions-eq',
                name: 'Import EQ corporate actions',
                description: 'Import corporate actions for the EQ series.',
                command: 'backtest:import-corporate-actions',
                arguments: ['--series=EQ', "--date={$date}"],
            ),
            $this->step(
                key: 'import-corporate-actions-sm',
                name: 'Import SM corporate actions',
                description: 'Import corporate actions for the SM series.',
                command: 'backtest:import-corporate-actions',
                arguments: ['--series=SM', "--date={$date}"],
            ),
            $this->step(
                key: 'import-corporate-actions-st',
                name: 'Import ST corporate actions',
                description: 'Import corporate actions for the ST series.',
                command: 'backtest:import-corporate-actions',
                arguments: ['--series=ST', "--date={$date}"],
            ),
            $this->step(
                key: 'import-corporate-actions-bz',
                name: 'Import BZ corporate actions',
                description: 'Import corporate actions for the BZ series.',
                command: 'backtest:import-corporate-actions',
                arguments: ['--series=BZ', "--date={$date}"],
            ),
            $this->step(
                key: 'calculate-dividend-adjustment-factor',
                name: 'Preview dividend adjustment factors',
                description: 'Calculate dividend adjustment factors without saving changes.',
                command: 'backtest:calculate-dividend-adjustment-factor',
                arguments: ["--date={$date}", '--dry-run'],
                isPreview: true,
            ),
            $this->step(
                key: 'apply-dividend-adjustment-factor',
                name: 'Apply dividend adjustment factors',
                description: 'Save the calculated dividend adjustment factors.',
                command: 'backtest:calculate-dividend-adjustment-factor',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'adjust-dividends',
                name: 'Preview dividend adjustments',
                description: 'Check dividend adjustments without saving changes.',
                command: 'backtest:adjust-dividends',
                arguments: ["--date={$date}", '--dry-run'],
                isPreview: true,
            ),
            $this->step(
                key: 'apply-dividends',
                name: 'Apply dividend adjustments',
                description: 'Apply dividend adjustment factors to historic prices.',
                command: 'backtest:adjust-dividends',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'adjust-corporate-action',
                name: 'Preview corporate action adjustments',
                description: 'Check corporate action adjustments without saving changes.',
                command: 'backtest:adjust-corporate-action',
                arguments: ["--date={$date}", '--dry-run'],
                isPreview: true,
            ),
            $this->step(
                key: 'apply-corporate-action',
                name: 'Apply corporate action adjustments',
                description: 'Apply corporate action price factors to historic prices.',
                command: 'backtest:adjust-corporate-action',
                arguments: ["--date={$date}"],
            ),
            ...($date >= '2024-03-01' ? [
                $this->step(
                    key: 'import-marketcap',
                    name: 'Import market cap',
                    description: 'Import market cap for the selected date and save values in crores.',
                    command: 'backtest:import-marketcap',
                    arguments: ["--date={$date}"],
                ),
                $this->step(
                    key: 'import-price-to-earnings',
                    name: 'Import price to earnings',
                    description: 'Import ADJUSTED P/E values for the selected date.',
                    command: 'backtest:import-price-to-earnings',
                    arguments: ["--date={$date}"],
                ),
            ] : []),
            $this->step(
                key: 'mark-etfs',
                name: 'Mark ETFs',
                description: 'Mark instruments that are exchange-traded funds.',
                command: 'backtest:mark-etfs',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'import-constituents',
                name: 'Import index constituents',
                description: 'Import index constituent data for the selected date.',
                command: 'backtest:import-constituents',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'calculate-t-percent',
                name: 'Calculate T percent',
                description: 'Calculate the delivery percentage data.',
                command: 'backtest:calculate-t-percent',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'process-daily-data',
                name: 'Process daily data',
                description: 'Calculate the per-instrument daily backtest values. This step waits for all related jobs.',
                command: 'backtest:process-daily-data',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'calculate-market-heartbeat',
                name: 'Calculate market heartbeat',
                description: 'Calculate the market heartbeat after daily data is complete.',
                command: 'backtest:calculate-market-heartbeat',
                arguments: ["--date={$date}"],
            ),
            $this->step(
                key: 'copy-instruments',
                name: 'Copy instruments',
                description: 'Copy the completed instrument data to the main tables.',
                command: 'backtest:copy-instruments',
                arguments: ["--date={$date}"],
            ),
        ];
    }

    /**
     * @return list<array{key: string, name: string}>
     */
    public function requiredFiles(string $date): array
    {
        return [
            ['key' => NseFileEnum::Bhavcopy->value, 'name' => 'Bhavcopy'],
            ['key' => NseFileEnum::CorporateActions->value, 'name' => 'Corporate actions'],
            ['key' => NseFileEnum::Etf->value, 'name' => 'ETF list'],
            ...($date >= '2024-03-01' ? [
                ['key' => NseFileEnum::Marketcap->value, 'name' => 'Market cap'],
                ['key' => NseFileEnum::PriceToEarnings->value, 'name' => 'Price to earnings'],
            ] : []),
        ];
    }

    /**
     * @param  list<string>  $arguments
     * @return array{key: string, name: string, description: string, command: string, arguments: list<string>, command_line: string, is_preview: bool}
     */
    private function step(
        string $key,
        string $name,
        string $description,
        string $command,
        array $arguments,
        bool $isPreview = false,
    ): array {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'command' => $command,
            'arguments' => $arguments,
            'command_line' => implode(' ', ['php artisan', $command, ...$arguments]),
            'is_preview' => $isPreview,
        ];
    }
}
