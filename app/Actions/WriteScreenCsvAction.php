<?php

namespace App\Actions;

use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Support\Collection;
use Spatie\SimpleExcel\SimpleExcelWriter;

class WriteScreenCsvAction
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'name' => 'name',
        'symbol' => 'symbol',
        'series' => 'series',
        'date' => 'date',
        'close_adjusted' => 'close_adjusted',
        'close_raw' => 'close_raw',
        'volume_adjusted' => 'volume_adjusted',
        'marketcap' => 'marketcap',
        'absolute_return_one_year' => 'absolute_return_one_year',
        'absolute_return_nine_months' => 'absolute_return_nine_months',
        'absolute_return_six_months' => 'absolute_return_six_months',
        'absolute_return_three_months' => 'absolute_return_three_months',
        'absolute_return_one_month' => 'absolute_return_one_months',
        'sharpe_return_one_year' => 'sharpe_return_one_year',
        'sharpe_return_nine_months' => 'sharpe_return_nine_months',
        'sharpe_return_six_months' => 'sharpe_return_six_months',
        'sharpe_return_three_months' => 'sharpe_return_three_months',
        'sharpe_return_one_month' => 'sharpe_return_one_months',
        'rsi_one_year' => 'rsi_one_year',
        'rsi_nine_months' => 'rsi_nine_months',
        'rsi_six_months' => 'rsi_six_months',
        'rsi_three_months' => 'rsi_three_months',
        'rsi_one_month' => 'rsi_one_months',
        'volatility_one_year' => 'volatility_one_year',
        'volatility_nine_months' => 'volatility_nine_months',
        'volatility_six_months' => 'volatility_six_months',
        'volatility_three_months' => 'volatility_three_months',
        'volatility_one_month' => 'volatility_one_months',
        'beta' => 'beta',
        'circuits_one_year' => 'circuits_one_year',
        'circuits_nine_months' => 'circuits_nine_months',
        'circuits_six_months' => 'circuits_six_months',
        'circuits_three_months' => 'circuits_three_months',
        'circuits_one_month' => 'circuits_one_months',
        'positive_days_percent_one_year' => 'positive_days_percent_one_year',
        'positive_days_percent_nine_months' => 'positive_days_percent_nine_months',
        'positive_days_percent_six_months' => 'positive_days_percent_six_months',
        'positive_days_percent_three_months' => 'positive_days_percent_three_months',
        'positive_days_percent_one_month' => 'positive_days_percent_one_months',
        'high_one_year' => 'high_one_year',
        'high_all_time' => 'high_all_time',
        'away_from_high_one_year' => 'away_from_high_one_year',
        'away_from_high_all_time' => 'away_from_high_all_time',
        'ma_200' => 'ma_200',
        'ma_100' => 'ma_100',
        'ma_50' => 'ma_50',
        'ma_20' => 'ma_20',
        'median_volume_one_year' => 'median_volume_one_year',
        'is_nifty_50' => 'is_nifty_50',
        'is_nifty_next_50' => 'is_nifty_next_50',
        'is_nifty_100' => 'is_nifty_100',
        'is_nifty_200' => 'is_nifty_200',
        'is_nifty_midcap_100' => 'is_nifty_midcap_100',
        'is_nifty_midcap_150' => 'is_nifty_midcap_150',
        'is_nifty_500' => 'is_nifty_500',
        'is_nifty_total_market' => 'is_nifty_total_market',
        'is_nifty_smallcap_250' => 'is_nifty_smallcap_250',
        'is_nifty_microcap_250' => 'is_nifty_microcap_250',
        'is_nifty_largemidcap_250' => 'is_nifty_largemidcap_250',
        'is_nifty_midsmallcap_400' => 'is_nifty_midsmallcap_400',
        'is_nifty_allcap' => 'is_nifty_allcap',
        'is_nifty_fno' => 'is_nifty_fno',
        'is_etf' => 'is_etf',
    ];

    /**
     * @param  Collection<int, BacktestNseInstrumentPrice>  $results
     */
    public function execute(string $path, Collection $results, ?Screen $screen = null): void
    {
        $columns = self::COLUMNS;
        if ($screen !== null) {
            $columns = ['rank' => 'rank', ...$columns];
            $fields = array_merge($screen->columns ?? [], [$screen->sort_by]);
            if ($screen->apply_factor_two) {
                $fields[] = $screen->factor_two_sort_by;
                $fields[] = 'factor_two_rank';
            }
            if ($screen->apply_factor_three) {
                $fields[] = $screen->factor_three_sort_by;
                $fields[] = 'factor_three_rank';
            }
            if ($screen->apply_factor_two || $screen->apply_factor_three) {
                $fields[] = 'factor_one_rank';
            }
            foreach ($fields as $field) {
                $columns[$field] = $field;
            }
        }

        $writer = SimpleExcelWriter::create($path, 'csv');
        try {
            $writer->addHeader(array_keys($columns));
            $rank = 0;
            foreach ($results as $result) {
                $rank++;
                $writer->addRow(array_map(
                    fn (string $attribute): mixed => $attribute === 'rank' ? $rank : $result->{$attribute},
                    $columns,
                ));
            }
        } finally {
            $writer->close();
        }
    }
}
