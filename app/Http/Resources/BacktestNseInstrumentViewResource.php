<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin \App\Models\BacktestNseInstrumentPrice
 */
class BacktestNseInstrumentViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'name' => $this->name,
            'close_adjusted' => $this->close_adjusted,
            'high_one_year' => $this->high_one_year,
            'away_from_high_one_year' => $this->away_from_high_one_year.'%',
            'high_all_time' => $this->high_all_time,
            'away_from_high_all_time' => $this->away_from_high_all_time.'%',
            'volatility_one_year' => round($this->volatility_one_year * 100, 2),
            'volatility_nine_months' => round($this->volatility_nine_months * 100, 2),
            'volatility_six_months' => round($this->volatility_six_months * 100, 2),
            'volatility_three_months' => round($this->volatility_three_months * 100, 2),
            'volatility_one_months' => round($this->volatility_one_months * 100, 2),
            'absolute_return_one_year' => $this->absolute_return_one_year.'%',
            'absolute_return_nine_months' => $this->absolute_return_nine_months.'%',
            'absolute_return_six_months' => $this->absolute_return_six_months.'%',
            'absolute_return_three_months' => $this->absolute_return_three_months.'%',
            'absolute_return_one_months' => $this->absolute_return_one_months.'%',
            'return_twelve_minus_one_months' => $this->return_twelve_minus_one_months.'%',
            'return_twelve_minus_two_months' => $this->return_twelve_minus_two_months.'%',
            'sharpe_return_one_year' => $this->sharpe_return_one_year.'%',
            'sharpe_return_nine_months' => $this->sharpe_return_nine_months.'%',
            'sharpe_return_six_months' => $this->sharpe_return_six_months.'%',
            'sharpe_return_three_months' => $this->sharpe_return_three_months.'%',
            'sharpe_return_one_months' => $this->sharpe_return_one_months.'%',
            'rsi_one_year' => $this->rsi_one_year ? round($this->rsi_one_year, 2) : '-',
            'rsi_nine_months' => $this->rsi_nine_months ? round($this->rsi_nine_months, 2) : '-',
            'rsi_six_months' => $this->rsi_six_months ? round($this->rsi_six_months, 2) : '-',
            'rsi_three_months' => $this->rsi_three_months ? round($this->rsi_three_months, 2) : '-',
            'rsi_one_months' => $this->rsi_one_months ? round($this->rsi_one_months, 2) : '-',
            'ma_200' => $this->ma_200,
            'ma_100' => $this->ma_100,
            'ma_50' => $this->ma_50,
            'ma_20' => $this->ma_20,
            'ema_200' => $this->ema_200,
            'ema_100' => $this->ema_100,
            'ema_50' => $this->ema_50,
            'ema_20' => $this->ema_20,
            'positive_days_percent_one_year' => $this->positive_days_percent_one_year.'%',
            'positive_days_percent_nine_months' => $this->positive_days_percent_nine_months.'%',
            'positive_days_percent_six_months' => $this->positive_days_percent_six_months.'%',
            'positive_days_percent_three_months' => $this->positive_days_percent_three_months.'%',
            'positive_days_percent_one_months' => $this->positive_days_percent_one_months.'%',
            'circuits_one_year' => $this->circuits_one_year,
            'circuits_nine_months' => $this->circuits_nine_months,
            'circuits_six_months' => $this->circuits_six_months,
            'circuits_three_months' => $this->circuits_three_months,
            'circuits_one_months' => $this->circuits_one_months,
            'price_to_earnings' => $this->price_to_earnings,
            'marketcap' => is_null($this->marketcap) ? '-' : Number::format($this->marketcap),
            'series' => $this->series ?? '-',
            'beta' => round($this->beta, 2),
            'median_volume_one_year' => round($this->median_volume_one_year / 10000000, 2),
            'indices' => array_values(array_filter([
                $this->is_nifty_50 ? 'Nifty 50' : null,
                $this->is_nifty_next_50 ? 'Nifty Next 50' : null,
                $this->is_nifty_100 ? 'Nifty 100' : null,
                $this->is_nifty_200 ? 'Nifty 200' : null,
                $this->is_nifty_midcap_100 ? 'Nifty Midcap 100' : null,
                $this->is_nifty_500 ? 'Nifty 500' : null,
                $this->is_nifty_smallcap_250 ? 'Nifty Smallcap 250' : null,
                $this->is_nifty_allcap ? 'Nifty Allcap' : null,
            ])),
        ];
    }
}
