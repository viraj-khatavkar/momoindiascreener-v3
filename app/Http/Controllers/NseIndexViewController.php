<?php

namespace App\Http\Controllers;

use App\Models\NseIndex;
use Inertia\Inertia;
use Inertia\Response;

class NseIndexViewController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $index = NseIndex::query()
            ->where('slug', $slug)
            ->orderByDesc('date')
            ->first();

        abort_if($index === null, 404);

        return inertia('NseIndexView', [
            'index' => $index,
            'slug' => $slug,
            'availableIndices' => fn () => NseIndex::query()
                ->where('date', NseIndex::query()->selectRaw('MAX(date)'))
                ->orderBy('symbol')
                ->get(['slug', 'symbol']),
            'priceHistory' => Inertia::defer(fn () => $this->getPriceHistory($slug), 'chart'),
            'metricHistory' => Inertia::defer(fn () => $this->getMetricHistory($slug), 'metrics'),
            'monthlyReturns' => Inertia::defer(fn () => $this->getMonthlyReturns($slug), 'returns'),
        ]);
    }

    /**
     * @return array<int, array{time: string, value: float}>
     */
    private function getPriceHistory(string $slug): array
    {
        return NseIndex::query()
            ->where('slug', $slug)
            ->orderBy('date')
            ->get(['date', 'close'])
            ->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => (float) $row->close,
            ])
            ->all();
    }

    /**
     * @return array{pe: array, pb: array, dividendYield: array}
     */
    private function getMetricHistory(string $slug): array
    {
        $rows = NseIndex::query()
            ->where('slug', $slug)
            ->orderBy('date')
            ->get(['date', 'price_to_earnings', 'price_to_book', 'dividend_yield']);

        return [
            'pe' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => $row->price_to_earnings === null ? null : (float) $row->price_to_earnings,
            ])->all(),
            'pb' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => $row->price_to_book === null ? null : (float) $row->price_to_book,
            ])->all(),
            'dividendYield' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => $row->dividend_yield === null ? null : (float) $row->dividend_yield,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{year: int, months: array<int, float|null>, yearReturn: float|null, throughDate: string}>
     */
    private function getMonthlyReturns(string $slug): array
    {
        $monthEndDates = NseIndex::query()
            ->selectRaw('YEAR(date) as yr, MONTH(date) as mo, MAX(date) as max_date')
            ->where('slug', $slug)
            ->groupByRaw('YEAR(date), MONTH(date)');

        $monthEndPrices = NseIndex::query()
            ->joinSub($monthEndDates, 'me', function ($join) {
                $join->on('nse_indices.date', '=', 'me.max_date');
            })
            ->where('nse_indices.slug', $slug)
            ->orderBy('me.yr')
            ->orderBy('me.mo')
            ->select(['me.yr', 'me.mo', 'nse_indices.close', 'nse_indices.date'])
            ->get();

        $lookup = [];
        foreach ($monthEndPrices as $row) {
            $lookup[(int) $row->yr][(int) $row->mo] = [
                'close' => (float) $row->close,
                'date' => $row->date->format('Y-m-d'),
            ];
        }

        $years = array_keys($lookup);
        if (count($years) === 0) {
            return [];
        }

        sort($years);
        $result = [];

        foreach ($years as $year) {
            $months = [];
            $lastMonthClose = null;
            $throughDate = '';

            for ($m = 1; $m <= 12; $m++) {
                $currentClose = $lookup[$year][$m]['close'] ?? null;

                if ($currentClose === null) {
                    $months[$m] = null;

                    continue;
                }

                $previousYear = $m === 1 ? $year - 1 : $year;
                $previousMonth = $m === 1 ? 12 : $m - 1;
                $months[$m] = $this->percentageReturn($currentClose, $lookup[$previousYear][$previousMonth]['close'] ?? null);
                $lastMonthClose = $currentClose;
                $throughDate = $lookup[$year][$m]['date'];
            }

            $result[] = [
                'year' => $year,
                'months' => $months,
                'yearReturn' => $lastMonthClose === null ? null : $this->percentageReturn($lastMonthClose, $lookup[$year - 1][12]['close'] ?? null),
                'throughDate' => $throughDate,
            ];
        }

        return array_reverse($result);
    }

    private function percentageReturn(float $currentClose, ?float $previousClose): ?float
    {
        if ($previousClose === null || $previousClose <= 0) {
            return null;
        }

        $return = ($currentClose - $previousClose) / $previousClose * 100;

        return is_finite($return) ? round($return, 2) : null;
    }
}
