<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestTrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class LoadBacktestTradeLogAction
{
    public const REASON_CATEGORIES = [
        'rank-exit', 'cash-call', 'demerger', 'be-exit', 'gold-rotation',
        'new-entry', 'replacement', 'rebalance', 'filter-exit', 'stop-loss', 'assumed-delisting',
    ];

    /** Gold rotation takes priority over the rank or filter reason in its prefix. */
    private const REASON_CATEGORY_SQL = <<<'SQL'
        CASE
            WHEN reason LIKE 'Assumed delisting%' THEN 'assumed-delisting'
            WHEN LOWER(reason) LIKE '%gold%' OR reason LIKE 'Index recovered%' THEN 'gold-rotation'
            WHEN reason LIKE 'Rank exceeded%' THEN 'rank-exit'
            WHEN reason LIKE '%Cash call%' THEN 'cash-call'
            WHEN reason LIKE 'Demerger ex-date%' THEN 'demerger'
            WHEN reason LIKE 'Series changed to BE%' THEN 'be-exit'
            WHEN reason LIKE 'New entry%' THEN 'new-entry'
            WHEN reason LIKE 'Replacement after%' THEN 'replacement'
            WHEN reason LIKE 'Weight rebalance adjustment%' OR reason LIKE 'No volatility data%' THEN 'rebalance'
            WHEN reason LIKE 'Stop loss%' OR reason LIKE 'Trailing stop loss%' THEN 'stop-loss'
            ELSE 'filter-exit'
        END
        SQL;

    /**
     * @param  array{search: string, type: string, reason: ?string, sort: string, year: ?int}  $filters
     * @return LengthAwarePaginator<int, BacktestTrade>
     */
    public function execute(Backtest $backtest, array $filters): LengthAwarePaginator
    {
        $query = $this->query($backtest, $filters);

        if ($filters['type'] !== 'all') {
            $query->where('trade_type', $filters['type']);
        }

        if ($filters['reason'] !== null) {
            $query->whereRaw('('.self::REASON_CATEGORY_SQL.') = ?', [$filters['reason']]);
        }

        return $query->select('backtest_trades.*')
            ->selectRaw(self::REASON_CATEGORY_SQL.' AS reason_category')
            ->orderBy('date', $filters['sort'])
            ->orderBy('trade_type')
            ->orderBy('id')
            ->paginate(100, pageName: 'trades_page')
            ->withQueryString();
    }

    /**
     * @param  array{search: string, type: string, reason: ?string, sort: string, year: ?int}  $filters
     * @return array{total: int, buys: int, sells: int, counts: array<string, int>, reasons: array<string, int>, years: list<int>}
     */
    public function summary(Backtest $backtest, array $filters): array
    {
        $totals = $backtest->trades()->toBase()
            ->selectRaw('trade_type, COUNT(*) AS total')->groupBy('trade_type')->pluck('total', 'trade_type');
        $counts = ['all' => 0, 'buy' => 0, 'sell' => 0];
        $reasons = [];
        $groups = $this->query($backtest, $filters)->toBase()
            ->selectRaw('trade_type, '.self::REASON_CATEGORY_SQL.' AS reason_category, COUNT(*) AS total')
            ->groupBy('trade_type', 'reason_category')->get();

        foreach ($groups as $group) {
            $total = (int) $group->total;
            $counts['all'] += $total;
            $counts[$group->trade_type] += $total;

            if ($filters['type'] === 'all' || $filters['type'] === $group->trade_type) {
                $reasons[$group->reason_category] = ($reasons[$group->reason_category] ?? 0) + $total;
            }
        }

        $years = $backtest->trades()->toBase()->selectRaw('YEAR(date) AS year')
            ->distinct()->orderByDesc('year')->pluck('year')->map(fn ($year): int => (int) $year)->all();

        return [
            'total' => (int) $totals->sum(),
            'buys' => (int) ($totals['buy'] ?? 0),
            'sells' => (int) ($totals['sell'] ?? 0),
            'counts' => $counts,
            'reasons' => $reasons,
            'years' => $years,
        ];
    }

    /**
     * @param  array{search: string, type: string, reason: ?string, sort: string, year: ?int}  $filters
     * @return Builder<BacktestTrade>
     */
    private function query(Backtest $backtest, array $filters): Builder
    {
        $query = BacktestTrade::query()->where('backtest_id', $backtest->id);

        if ($filters['search'] !== '') {
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['search'])).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereRaw("LOWER(symbol) LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$search]);
            });
        }

        if ($filters['year'] !== null) {
            $query->whereBetween('date', [$filters['year'].'-01-01', $filters['year'].'-12-31']);
        }

        return $query;
    }
}
