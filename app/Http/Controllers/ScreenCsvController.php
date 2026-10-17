<?php

namespace App\Http\Controllers;

use App\Actions\ApplyScreenFiltersAction;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScreenCsvController extends Controller
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
        'is_etf' => 'is_etf',
    ];

    public function __invoke(Screen $screen, Request $request, ApplyScreenFiltersAction $applyScreenFiltersAction): StreamedResponse
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $results = $applyScreenFiltersAction->execute($screen, $this->getLatestDate(), true);

        return response()->streamDownload(
            fn () => $this->writeResults($results),
            str($screen->name)->replace(' ', '_').'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @param  Collection<int, BacktestNseInstrumentPrice>  $results
     */
    protected function writeResults(Collection $results): void
    {
        $writer = SimpleExcelWriter::create('php://output', 'csv');

        try {
            $writer->addHeader(array_keys(self::COLUMNS));

            foreach ($results as $result) {
                $writer->addRow(array_map(
                    fn (string $attribute): mixed => $result->{$attribute},
                    self::COLUMNS,
                ));
            }
        } finally {
            $writer->close();
        }
    }

    protected function getLatestDate(): CarbonInterface
    {
        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first()
            ->date;
    }
}
