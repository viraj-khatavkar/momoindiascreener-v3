<?php

namespace App\Http\Controllers;

use App\Http\Resources\BacktestNseInstrumentViewResource;
use App\Models\BacktestNseInstrumentPrice;
use Inertia\Inertia;

class BacktestNseInstrumentViewController extends Controller
{
    public function __invoke(string $symbol)
    {
        $latestDate = $this->getLatestDate();

        $instrument = BacktestNseInstrumentPrice::query()
            ->where('symbol', $symbol)
            ->where('date', $latestDate)
            ->firstOrFail();

        return inertia('InstrumentView', [
            'instrument' => BacktestNseInstrumentViewResource::make($instrument),
            'pros' => $this->generatePros($instrument),
            'cons' => $this->generateCons($instrument),
            'priceHistory' => Inertia::defer(fn () => BacktestNseInstrumentPrice::query()
                ->where('symbol', $symbol)
                ->select('date', 'open_adjusted', 'high_adjusted', 'low_adjusted', 'close_adjusted', 'volume_adjusted')
                ->orderBy('date', 'asc')
                ->get()
                ->toArray(), 'chart'),
            'dividends' => Inertia::defer(fn () => BacktestNseInstrumentPrice::query()
                ->where('symbol', $symbol)
                ->whereNotNull('corporate_actions')
                ->whereNotNull('dividend_adjustment_factor')
                ->select('date', 'corporate_actions', 'dividend')
                ->orderBy('date', 'desc')
                ->get()
                ->flatMap(function ($record) {
                    return collect($record->corporate_actions)->map(function ($action) use ($record) {
                        return [
                            'date' => $record->date->format('Y-m-d'),
                            'description' => $action,
                            'dividend' => $record->dividend,
                        ];
                    });
                })
                ->toArray(), 'extras'),
            'corporateActions' => Inertia::defer(fn () => BacktestNseInstrumentPrice::query()
                ->where('symbol', $symbol)
                ->whereNotNull('corporate_actions')
                ->whereNotNull('price_adjustment_factor')
                ->select('date', 'corporate_actions')
                ->orderBy('date', 'desc')
                ->get()
                ->flatMap(function ($record) {
                    return collect($record->corporate_actions)->map(function ($action) use ($record) {
                        return [
                            'date' => $record->date->format('Y-m-d'),
                            'description' => $action,
                        ];
                    });
                })
                ->toArray(), 'extras'),
        ]);
    }

    /**
     * @return string[]
     */
    protected function generatePros(BacktestNseInstrumentPrice $instrument): array
    {
        $pros = [];

        if ($instrument->close_adjusted > $instrument->ma_200) {
            $pros[] = 'The close is above 200-day moving average.';
        }

        if ($instrument->close_adjusted > $instrument->ma_100) {
            $pros[] = 'The close is above 100-day moving average.';
        }

        if ($instrument->close_adjusted > $instrument->ma_50) {
            $pros[] = 'The close is above 50-day moving average.';
        }

        if ($instrument->close_adjusted > $instrument->ma_20) {
            $pros[] = 'The close is above 20-day moving average.';
        }

        if ($instrument->away_from_high_all_time > -25) {
            $pros[] = 'The close is within 25% of all time high.';
        }

        if ($instrument->beta < 1.25) {
            $pros[] = 'The beta is less than 1.25';
        }

        if ($instrument->rsi_one_months > 80) {
            $pros[] = 'One month RSI is above 80';
        }

        return $pros;
    }

    /**
     * @return string[]
     */
    protected function generateCons(BacktestNseInstrumentPrice $instrument): array
    {
        $cons = [];

        if ($instrument->close_adjusted < $instrument->ma_200) {
            $cons[] = 'The close is below 200-day moving average.';
        }

        if ($instrument->close_adjusted < $instrument->ma_100) {
            $cons[] = 'The close is below 100-day moving average.';
        }

        if ($instrument->close_adjusted < $instrument->ma_50) {
            $cons[] = 'The close is below 50-day moving average.';
        }

        if ($instrument->close_adjusted < $instrument->ma_20) {
            $cons[] = 'The close is below 20-day moving average.';
        }

        if ($instrument->away_from_high_all_time < -25) {
            $cons[] = 'The close is away more than 25% of all time high.';
        }

        if ($instrument->circuits_one_year > 20) {
            $cons[] = 'Stock closed at circuit for more than 20 times in past one year.';
        }

        if ($instrument->rsi_one_months < 20) {
            $cons[] = 'One month RSI is below 20';
        }

        return $cons;
    }

    protected function getLatestDate(): mixed
    {
        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->orderBy('date', 'desc')
            ->limit(1)
            ->first()
            ->date;
    }
}
