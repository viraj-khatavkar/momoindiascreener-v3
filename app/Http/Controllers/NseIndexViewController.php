<?php

namespace App\Http\Controllers;

use App\Models\NseIndex;
use Illuminate\Support\Facades\DB;
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
            ->whereNotNull('price_to_earnings')
            ->orderBy('date')
            ->get(['date', 'price_to_earnings', 'price_to_book', 'dividend_yield']);

        return [
            'pe' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => (float) $row->price_to_earnings,
            ])->all(),
            'pb' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => (float) $row->price_to_book,
            ])->all(),
            'dividendYield' => $rows->map(fn (NseIndex $row) => [
                'time' => $row->date->format('Y-m-d'),
                'value' => (float) $row->dividend_yield,
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{year: int, months: array<int, float|null>, yearReturn: float|null}>
     */
    private function getMonthlyReturns(string $slug): array
    {
        $monthEndDates = DB::query()
            ->selectRaw('YEAR(date) as yr, MONTH(date) as mo, MAX(date) as max_date')
            ->from('nse_indices')
            ->where('slug', $slug)
            ->groupByRaw('YEAR(date), MONTH(date)');

        $monthEndPrices = NseIndex::query()
            ->joinSub($monthEndDates, 'me', function ($join) {
                $join->on('nse_indices.date', '=', 'me.max_date');
            })
            ->where('nse_indices.slug', $slug)
            ->orderBy('me.yr')
            ->orderBy('me.mo')
            ->select(['me.yr', 'me.mo', 'nse_indices.close'])
            ->get();

        // Build lookup: year → month → close
        $lookup = [];
        foreach ($monthEndPrices as $row) {
            $lookup[(int) $row->yr][(int) $row->mo] = (float) $row->close;
        }

        $years = array_keys($lookup);
        if (count($years) === 0) {
            return [];
        }

        sort($years);
        $result = [];

        foreach ($years as $year) {
            $months = [];
            $firstMonthClose = null;
            $lastMonthClose = null;

            for ($m = 1; $m <= 12; $m++) {
                $currentClose = $lookup[$year][$m] ?? null;

                if ($currentClose === null) {
                    $months[$m] = null;

                    continue;
                }

                if ($firstMonthClose === null) {
                    // Find previous month's close for first month return
                    $prevClose = $this->getPreviousClose($lookup, $year, $m);
                    $months[$m] = $prevClose !== null
                        ? round(($currentClose - $prevClose) / $prevClose * 100, 2)
                        : null;
                    $firstMonthClose = $prevClose ?? $currentClose;
                } else {
                    // Get previous month's close
                    $prevClose = $this->getPreviousClose($lookup, $year, $m);
                    $months[$m] = $prevClose !== null
                        ? round(($currentClose - $prevClose) / $prevClose * 100, 2)
                        : null;
                }

                $lastMonthClose = $currentClose;
            }

            // Year return: Dec close vs previous Dec close (or first available)
            $prevYearDec = $lookup[$year - 1][12] ?? null;
            $yearReturn = ($lastMonthClose !== null && $prevYearDec !== null)
                ? round(($lastMonthClose - $prevYearDec) / $prevYearDec * 100, 2)
                : null;

            $result[] = [
                'year' => $year,
                'months' => $months,
                'yearReturn' => $yearReturn,
            ];
        }

        return array_reverse($result);
    }

    private function getPreviousClose(array $lookup, int $year, int $month): ?float
    {
        if ($month > 1) {
            // Look backwards in same year
            for ($m = $month - 1; $m >= 1; $m--) {
                if (isset($lookup[$year][$m])) {
                    return $lookup[$year][$m];
                }
            }
        }

        // Look at previous year's December, then backwards
        if (isset($lookup[$year - 1])) {
            for ($m = 12; $m >= 1; $m--) {
                if (isset($lookup[$year - 1][$m])) {
                    return $lookup[$year - 1][$m];
                }
            }
        }

        return null;
    }
}
