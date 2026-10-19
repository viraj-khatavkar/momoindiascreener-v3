<?php

namespace App\Actions;

use App\Enums\BacktestStatusEnum;
use App\Models\Backtest;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildHomePageDataAction
{
    private const RECENT_LIMIT = 6;

    /**
     * @return array{screens: array, screenCount: int, backtests: array, backtestCount: int}
     */
    public function execute(?User $user): array
    {
        $screenColumns = ['id', 'name', 'index', 'sort_by', 'sort_direction'];

        if ($user) {
            $screens = $user->screens()->latest('updated_at')->latest('id')
                ->limit(self::RECENT_LIMIT)->get($screenColumns);
            $screenCount = $user->screens()->count();
        } else {
            $publicScreenOrder = array_flip(Screen::PUBLIC_SCREENS);
            $screens = Screen::query()->whereKey(Screen::PUBLIC_SCREENS)->get($screenColumns)
                ->sortBy(fn (Screen $screen): int => $publicScreenOrder[$screen->getKey()])->values();
            $screenCount = $screens->count();
        }

        $backtestsQuery = $user ? $user->backtests() : Backtest::query()->publiclyVisible();
        $backtestCount = (clone $backtestsQuery)->count();

        if ($user) {
            $backtestsQuery->limit(self::RECENT_LIMIT);
        }

        $backtests = $backtestsQuery
            ->with('summaryMetrics:id,backtest_id,cagr,max_drawdown')
            ->latest('updated_at')->latest('id')
            ->get(['id', 'name', 'index', 'status', 'progress', 'updated_at']);

        return [
            'screens' => $this->serializeScreens($screens),
            'screenCount' => $screenCount,
            'backtests' => $this->serializeBacktests($backtests, $user !== null),
            'backtestCount' => $backtestCount,
        ];
    }

    /**
     * @param  Collection<int, Screen>  $screens
     * @return array<int, array{id: int, name: string, index: string, sort_by: string, sort_direction: string}>
     */
    private function serializeScreens(Collection $screens): array
    {
        return $screens->map(fn (Screen $screen): array => [
            'id' => $screen->getKey(),
            'name' => $screen->name,
            'index' => $screen->index->value,
            'sort_by' => $screen->sort_by,
            'sort_direction' => $screen->sort_direction,
        ])->all();
    }

    /**
     * @param  Collection<int, Backtest>  $backtests
     * @return array<int, array{id: int, name: string, index: string, status: string, progress: int, updated_at: ?string, url: string, summary_metrics: ?array{cagr: float, max_drawdown: float}}>
     */
    private function serializeBacktests(Collection $backtests, bool $personal): array
    {
        return $backtests->map(fn (Backtest $backtest): array => [
            'id' => $backtest->getKey(),
            'name' => $backtest->name,
            'index' => $backtest->index->value,
            'status' => $backtest->status->value,
            'progress' => (int) $backtest->progress,
            'updated_at' => $backtest->updated_at?->toIso8601String(),
            'url' => route($personal ? 'backtests.show' : 'public-backtests.show', $backtest),
            'summary_metrics' => $backtest->status === BacktestStatusEnum::Completed && $backtest->summaryMetrics ? [
                'cagr' => (float) $backtest->summaryMetrics->cagr,
                'max_drawdown' => (float) $backtest->summaryMetrics->max_drawdown,
            ] : null,
        ])->all();
    }
}
