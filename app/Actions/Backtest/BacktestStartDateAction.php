<?php

namespace App\Actions\Backtest;

use App\Enums\NseIndexEnum;
use App\Models\Backtest;
use Illuminate\Validation\ValidationException;

class BacktestStartDateAction
{
    public const DEFAULT_START_DATE = BacktestSortDateAction::DEFAULT_START_DATE;

    /** First dated membership in the imported price history, checked on 2026-11-18. */
    public const UNIVERSE_START_DATES = [
        'nifty_50' => '2010-11-29',
        'nifty_next_50' => '2010-11-29',
        'nifty_100' => '2010-11-29',
        'nifty_200' => '2014-01-01',
        'nifty_midcap_100' => '2010-11-29',
        'nifty_midcap_150' => '2017-07-31',
        'nifty_500' => '2010-11-29',
        'nifty_total_market' => '2024-11-01',
        'nifty_1000' => '2026-11-16',
        'nifty_smallcap_250' => '2017-07-31',
        'nifty_microcap_250' => '2024-11-01',
        'nifty_largemidcap_250' => '2017-07-31',
        'nifty_midsmallcap_400' => '2017-07-31',
        'nifty_allcap' => '2010-01-04',
        'nifty_fno' => '2011-01-03',
        'etf' => '2010-11-29',
    ];

    public const SORT_START_DATES = [
        ...BacktestSortDateAction::SORT_START_DATES,
        'return_twelve_minus_one_months' => '2011-01-10',
        'return_twelve_minus_two_months' => '2011-02-08',
    ];

    public const FILTER_START_DATES = [
        'apply_marketcap' => self::SORT_START_DATES['marketcap'],
        'apply_pe' => self::SORT_START_DATES['price_to_earnings'],
    ];

    public const VALIDATION_MESSAGE = 'The selected universe and settings require a start date on or after :date.';

    /** @param array<string, mixed> $settings */
    public function minimumDate(array $settings): string
    {
        $index = $settings['index'] ?? null;
        $index = $index instanceof NseIndexEnum ? $index->value : $index;
        $minimumDate = max(self::DEFAULT_START_DATE, is_string($index) ? self::UNIVERSE_START_DATES[$index] ?? self::DEFAULT_START_DATE : self::DEFAULT_START_DATE);
        foreach ($this->rankingFields($settings) as $field) {
            $sort = $settings[$field] ?? null;
            if (is_string($sort)) {
                $minimumDate = max($minimumDate, self::SORT_START_DATES[$sort] ?? self::DEFAULT_START_DATE);
            }
        }

        foreach (self::FILTER_START_DATES as $field => $date) {
            if (! empty($settings[$field])) {
                $minimumDate = max($minimumDate, $date);
            }
        }

        return $minimumDate;
    }

    /** @return array{default: string, universes: array<string, string>, sorts: array<string, string>, filters: array<string, string>} */
    public function limits(): array
    {
        return [
            'default' => self::DEFAULT_START_DATE,
            'universes' => self::UNIVERSE_START_DATES,
            'sorts' => self::SORT_START_DATES,
            'filters' => self::FILTER_START_DATES,
        ];
    }

    /**
     * ETFs have no company valuation data at any date.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public function unavailableSettingsErrors(array $settings): array
    {
        if (! in_array($settings['index'] ?? null, [NseIndexEnum::NIFTY_ETF, NseIndexEnum::NIFTY_ETF->value], true)) {
            return [];
        }

        $errors = [];
        foreach ($this->rankingFields($settings) as $field) {
            if (in_array($settings[$field] ?? null, ['marketcap', 'price_to_earnings'], true)) {
                $errors[$field] = 'Market cap and P/E data are not available for ETFs. Select another ranking factor.';
            }
        }

        foreach (array_keys(self::FILTER_START_DATES) as $field) {
            if (! empty($settings[$field])) {
                $errors[$field] = 'Market cap and P/E range filters are not available for ETFs. Turn off these filters or select a stock universe.';
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function rankingFields(array $settings): array
    {
        $fields = ['sort_by'];
        if (! empty($settings['apply_factor_two'])) {
            $fields[] = 'factor_two_sort_by';
        }
        if (! empty($settings['apply_factor_three'])) {
            $fields[] = 'factor_three_sort_by';
        }

        return $fields;
    }

    public function validate(Backtest $backtest): void
    {
        $errors = $this->unavailableSettingsErrors($backtest->getAttributes());
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $minimumDate = $this->minimumDate($backtest->getAttributes());
        $startDate = $backtest->start_date?->toDateString() ?? self::DEFAULT_START_DATE;
        if ($startDate < $minimumDate) {
            throw ValidationException::withMessages([
                'start_date' => str_replace(':date', $minimumDate, self::VALIDATION_MESSAGE),
            ]);
        }
    }
}
