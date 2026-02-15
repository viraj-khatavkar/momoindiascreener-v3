<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * @mixin \App\Models\BacktestNseInstrumentPrice
 */
class BacktestNseInstrumentPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'volatility_one_year' => round($this->volatility_one_year * 100, 2),
            'volatility_nine_months' => round($this->volatility_nine_months * 100, 2),
            'volatility_six_months' => round($this->volatility_six_months * 100, 2),
            'volatility_three_months' => round($this->volatility_three_months * 100, 2),
            'volatility_one_months' => round($this->volatility_one_months * 100, 2),
            'beta' => round($this->beta, 2),
            'marketcap' => is_null($this->marketcap) ? '-' : Number::format($this->marketcap),
            'median_volume_one_year' => round($this->median_volume_one_year / 10000000, 2),
        ]);
    }
}
