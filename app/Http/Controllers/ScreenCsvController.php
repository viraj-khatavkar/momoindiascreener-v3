<?php

namespace App\Http\Controllers;

use App\Actions\ApplyScreenFiltersAction;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ScreenCsvController extends Controller
{
    public function __invoke(Screen $screen, Request $request, ApplyScreenFiltersAction $applyScreenFiltersAction)
    {
        if ($request->user()->cannot('update', $screen)) {
            abort(404);
        }

        $date = $this->getLatestDate();
        $results = $applyScreenFiltersAction->execute($screen, $date, true);

        $results = $results->map(function (BacktestNseInstrumentPrice $result) {
            return [
                'name' => $result->name,
                'symbol' => $result->symbol,
                'series' => $result->series,
                'date' => $result->date,
                'close_adjusted' => $result->close_adjusted,
                'close_raw' => $result->close_raw,
                'volume_adjusted' => $result->volume_adjusted,
                'marketcap' => $result->marketcap,
                'absolute_return_one_year' => $result->absolute_return_one_year,
                'absolute_return_nine_months' => $result->absolute_return_nine_months,
                'absolute_return_six_months' => $result->absolute_return_six_months,
                'absolute_return_three_months' => $result->absolute_return_three_months,
                'absolute_return_one_month' => $result->absolute_return_one_months,
                'sharpe_return_one_year' => $result->sharpe_return_one_year,
                'sharpe_return_nine_months' => $result->sharpe_return_nine_months,
                'sharpe_return_six_months' => $result->sharpe_return_six_months,
                'sharpe_return_three_months' => $result->sharpe_return_three_months,
                'sharpe_return_one_month' => $result->sharpe_return_one_months,
                'rsi_one_year' => $result->rsi_one_year,
                'rsi_nine_months' => $result->rsi_nine_months,
                'rsi_six_months' => $result->rsi_six_months,
                'rsi_three_months' => $result->rsi_three_months,
                'rsi_one_month' => $result->rsi_one_months,
                'volatility_one_year' => $result->volatility_one_year,
                'volatility_nine_months' => $result->volatility_nine_months,
                'volatility_six_months' => $result->volatility_six_months,
                'volatility_three_months' => $result->volatility_three_months,
                'volatility_one_month' => $result->volatility_one_months,
                'beta' => $result->beta,
                'circuits_one_year' => $result->circuits_one_year,
                'circuits_nine_months' => $result->circuits_nine_months,
                'circuits_six_months' => $result->circuits_six_months,
                'circuits_three_months' => $result->circuits_three_months,
                'circuits_one_month' => $result->circuits_one_months,
                'positive_days_percent_one_year' => $result->positive_days_percent_one_year,
                'positive_days_percent_nine_months' => $result->positive_days_percent_nine_months,
                'positive_days_percent_six_months' => $result->positive_days_percent_six_months,
                'positive_days_percent_three_months' => $result->positive_days_percent_three_months,
                'positive_days_percent_one_month' => $result->positive_days_percent_one_months,
                'high_one_year' => $result->high_one_year,
                'high_all_time' => $result->high_all_time,
                'away_from_high_one_year' => $result->away_from_high_one_year,
                'away_from_high_all_time' => $result->away_from_high_all_time,
                'ma_200' => $result->ma_200,
                'ma_100' => $result->ma_100,
                'ma_50' => $result->ma_50,
                'ma_20' => $result->ma_20,
                'median_volume_one_year' => $result->median_volume_one_year,
                'is_nifty_50' => $result->is_nifty_50,
                'is_nifty_next_50' => $result->is_nifty_next_50,
                'is_nifty_100' => $result->is_nifty_100,
                'is_nifty_200' => $result->is_nifty_200,
                'is_nifty_midcap_100' => $result->is_nifty_midcap_100,
                'is_nifty_500' => $result->is_nifty_500,
                'is_nifty_smallcap_250' => $result->is_nifty_smallcap_250,
                'is_nifty_allcap' => $result->is_nifty_allcap,
                'is_etf' => $result->is_etf,
            ];
        });

        $columns = $this->getColumns($results->first());

        $writer = SimpleExcelWriter::streamDownload(str($screen->name)->replace(' ', '_').'.csv');
        $writer->addHeader($columns);

        $i = 0;
        foreach ($results as $result) {
            $writer->addRow(array_values($result));
            $i++;

            if ($i % 100 === 0) {
                flush();
            }
        }

        $writer->toBrowser();
    }

    protected function getColumns(array $results): array
    {
        $columns = [];

        foreach ($results as $key => $value) {
            $columns[] = $key;
        }

        return $columns;
    }

    protected function getLatestDate()
    {
        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first()
            ->date;
    }
}
