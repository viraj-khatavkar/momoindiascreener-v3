<?php

namespace App\Actions;

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class BuildHomePageDataAction
{
    public function __construct(private ApplyScreenFiltersAction $applyScreenFilters) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(?User $user): array
    {
        $isPaid = (bool) $user?->is_paid;
        $latestMarketDate = BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->latest('date')
            ->first(['date'])
            ?->date
            ?->toDateString();

        $publicScreenOrder = array_flip(Screen::PUBLIC_SCREENS);
        $publicScreens = Screen::query()
            ->whereKey(Screen::PUBLIC_SCREENS)
            ->get()
            ->sortBy(fn (Screen $screen): int => $publicScreenOrder[$screen->getKey()])
            ->values();

        $personalScreens = $user
            ? $user->screens()
                ->latest('updated_at')
                ->limit(3)
                ->get()
            : collect();

        $personalScreenCount = $user
            ? $user->screens()->count()
            : 0;

        return [
            'publicScreens' => $this->serializeScreens($publicScreens),
            'publicScreenPreviews' => Inertia::defer(
                fn (): array => $this->buildScreenPreviews($publicScreens, $latestMarketDate),
                'public-screens',
            ),
            'personalScreens' => $this->serializeScreens($personalScreens),
            'personalScreenCount' => $personalScreenCount,
            'personalScreenPreviews' => $user
                ? Inertia::defer(
                    fn (): array => $this->buildScreenPreviews($personalScreens, $latestMarketDate),
                    'personal-screens',
                )
                : [],
            'backtestActivity' => $isPaid ? $this->backtestActivity($user->backtests()) : [],
            'recentBacktests' => $isPaid ? $this->recentBacktests($user->backtests()) : [],
        ];
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @return array<int, array<string, mixed>>
     */
    private function serializeScreens(Collection $screens): array
    {
        return $screens->map(fn (Screen $screen): array => [
            'id' => $screen->getKey(),
            'name' => $screen->name,
            'index' => $screen->index->value,
            'sort_by' => $screen->sort_by,
            'sort_direction' => $screen->sort_direction,
            'apply_historical_date' => $screen->apply_historical_date,
            'historical_date' => $screen->historical_date?->toDateString(),
            'updated_at' => $screen->updated_at?->toIso8601String(),
        ])->all();
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @return array<int, array<string, mixed>>
     */
    private function buildScreenPreviews(Collection $screens, ?string $latestMarketDate): array
    {
        return $screens->map(function (Screen $screen) use ($latestMarketDate): array {
            $resultDate = $this->resolveScreenDate($screen, $latestMarketDate);

            if ($resultDate === null) {
                return [
                    'screen_id' => $screen->getKey(),
                    'result_count' => 0,
                    'result_date' => null,
                    'top_results' => [],
                ];
            }

            $results = $this->applyScreenFilters->execute($screen, $resultDate);

            return [
                'screen_id' => $screen->getKey(),
                'result_count' => $results->count(),
                'result_date' => $resultDate,
                'top_results' => $results->take(4)->map(fn (BacktestNseInstrumentPrice $result): array => [
                    'symbol' => $result->symbol,
                    'name' => $result->name,
                ])->values()->all(),
            ];
        })->all();
    }

    private function resolveScreenDate(Screen $screen, ?string $latestMarketDate): ?string
    {
        if (! $screen->apply_historical_date) {
            return $latestMarketDate;
        }

        return BacktestNseInstrumentPrice::query()
            ->where('is_nifty_allcap', true)
            ->where('date', '<=', $screen->historical_date)
            ->latest('date')
            ->first(['date'])
            ?->date
            ?->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function backtestActivity(HasMany $query): array
    {
        return $query
            ->whereIn('status', [
                BacktestStatusEnum::Failed->value,
                BacktestStatusEnum::Running->value,
                BacktestStatusEnum::Pending->value,
            ])
            ->orderByRaw("CASE status WHEN 'failed' THEN 1 WHEN 'running' THEN 2 ELSE 3 END")
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'name', 'status', 'progress', 'started_at', 'updated_at'])
            ->map(fn (Backtest $backtest): array => [
                'id' => $backtest->getKey(),
                'name' => $backtest->name,
                'status' => $backtest->status->value,
                'progress' => (int) $backtest->progress,
                'started_at' => $backtest->started_at?->toIso8601String(),
                'updated_at' => $backtest->updated_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentBacktests(HasMany $query): array
    {
        return $query
            ->where('status', BacktestStatusEnum::Completed->value)
            ->with('summaryMetrics:id,backtest_id,cagr,max_drawdown')
            ->latest('completed_at')
            ->limit(3)
            ->get(['id', 'name', 'status', 'completed_at'])
            ->map(fn (Backtest $backtest): array => [
                'id' => $backtest->getKey(),
                'name' => $backtest->name,
                'status' => $backtest->status->value,
                'completed_at' => $backtest->completed_at?->toIso8601String(),
                'summary_metrics' => $backtest->summaryMetrics ? [
                    'cagr' => (float) $backtest->summaryMetrics->cagr,
                    'max_drawdown' => (float) $backtest->summaryMetrics->max_drawdown,
                ] : null,
            ])->all();
    }
}
