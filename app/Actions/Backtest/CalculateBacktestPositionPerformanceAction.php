<?php

namespace App\Actions\Backtest;

use App\Models\Backtest;
use App\Models\BacktestNseInstrumentPrice;
use App\Models\BacktestTrade;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\JoinClause;

/**
 * @phpstan-type Position array{entry_trade_id: int, symbol: string, name: string, entry_date: string, exit_date: ?string, holding_days: int, quantity: int, buy_value: float, purchase_cost: float, sell_value: float, unrealized_value: float, remaining_cost: float, charges: float, realized_pnl: float, unrealized_pnl: float, net_pnl: float, pnl_pct: float, still_held: bool}
 * @phpstan-type OpenPosition array{entry_trade_id: int, symbol: string, name: string, entry_date: CarbonInterface, quantity: int, buy_value: float, purchase_cost: float, sell_value: float, remaining_cost: float, charges: float, last_price: float}
 * @phpstan-type ClosedStatistics array{count: int, winners: int, losers: int, breakeven: int, total_profit: float, total_loss: float, net_pnl: float, average_win: ?float, average_loss: ?float, expectancy: ?float, average_holding_days: ?float, winners_percentage: ?float, profit_factor: ?float}
 * @phpstan-type Performance array{version: int, closed: ClosedStatistics, top_winners: array{net_pnl: list<Position>, pnl_pct: list<Position>}, top_losers: array{net_pnl: list<Position>, pnl_pct: list<Position>}, open_positions: list<Position>}
 */
class CalculateBacktestPositionPerformanceAction
{
    private const TOP_POSITION_LIMIT = 20;

    /**
     * Keep only active holdings, aggregate statistics, and four bounded top lists.
     * A full exit completes a position; a later purchase starts a new position.
     *
     * @return Performance
     */
    public function execute(Backtest $backtest, CarbonInterface $lastDate): array
    {
        $closed = [
            'count' => 0, 'winners' => 0, 'losers' => 0, 'breakeven' => 0,
            'total_profit' => 0.0, 'total_loss' => 0.0, 'net_pnl' => 0.0,
            'average_win' => null, 'average_loss' => null, 'expectancy' => null,
            'average_holding_days' => null, 'winners_percentage' => null, 'profit_factor' => null,
        ];
        $topWinners = ['net_pnl' => [], 'pnl_pct' => []];
        $topLosers = ['net_pnl' => [], 'pnl_pct' => []];
        $openPositions = [];
        $totalHoldingDays = 0;

        $trades = $backtest->trades()
            ->select(['id', 'date', 'symbol', 'name', 'trade_type', 'quantity', 'gross_amount', 'total_charges'])
            ->orderBy('date')
            ->orderBy('id')
            ->lazy(500);

        foreach ($trades as $trade) {
            $symbol = $trade->symbol;

            if ($trade->trade_type === 'buy') {
                $openPositions[$symbol] ??= $this->newPosition($trade);
                $position = &$openPositions[$symbol];
                $purchaseCost = (float) $trade->gross_amount + (float) $trade->total_charges;
                $position['quantity'] += $trade->quantity;
                $position['buy_value'] += (float) $trade->gross_amount;
                $position['purchase_cost'] += $purchaseCost;
                $position['remaining_cost'] += $purchaseCost;
                $position['charges'] += (float) $trade->total_charges;
                $position['last_price'] = (float) $trade->gross_amount / $trade->quantity;
                unset($position);

                continue;
            }

            if (! isset($openPositions[$symbol])) {
                continue;
            }

            $position = &$openPositions[$symbol];
            $soldCost = $position['remaining_cost'] * $trade->quantity / $position['quantity'];
            $position['remaining_cost'] -= $soldCost;
            $position['quantity'] -= $trade->quantity;
            $position['sell_value'] += (float) $trade->gross_amount;
            $position['charges'] += (float) $trade->total_charges;
            $position['last_price'] = (float) $trade->gross_amount / $trade->quantity;

            if ($position['quantity'] <= 0) {
                $position['remaining_cost'] = 0.0;
                $result = $this->finalizePosition($position, $trade->date, 0.0, false);
                $closed['count']++;
                $closed['net_pnl'] += $result['net_pnl'];
                $totalHoldingDays += $result['holding_days'];

                if ($result['net_pnl'] > 0) {
                    $closed['winners']++;
                    $closed['total_profit'] += $result['net_pnl'];
                    $this->retainTopPositions($topWinners, $result, true);
                } elseif ($result['net_pnl'] < 0) {
                    $closed['losers']++;
                    $closed['total_loss'] += abs($result['net_pnl']);
                    $this->retainTopPositions($topLosers, $result, false);
                } else {
                    $closed['breakeven']++;
                }

                unset($openPositions[$symbol]);
            }

            unset($position);
        }

        $closed['net_pnl'] = round($closed['net_pnl'], 2);
        $closed['total_profit'] = round($closed['total_profit'], 2);
        $closed['total_loss'] = round($closed['total_loss'], 2);
        $closed['average_win'] = $closed['winners'] > 0 ? round($closed['total_profit'] / $closed['winners'], 2) : null;
        $closed['average_loss'] = $closed['losers'] > 0 ? round(-$closed['total_loss'] / $closed['losers'], 2) : null;
        $closed['expectancy'] = $closed['count'] > 0 ? round($closed['net_pnl'] / $closed['count'], 2) : null;
        $closed['average_holding_days'] = $closed['count'] > 0 ? round($totalHoldingDays / $closed['count'], 2) : null;
        $closed['winners_percentage'] = $closed['count'] > 0 ? round($closed['winners'] / $closed['count'] * 100, 2) : null;
        $closed['profit_factor'] = $closed['total_loss'] > 0 ? round($closed['total_profit'] / $closed['total_loss'], 4) : null;

        $lastPrices = $this->lastPrices($backtest, array_keys($openPositions), $lastDate);
        $finalHoldings = [];
        foreach ($openPositions as $symbol => $position) {
            $marketValue = $position['quantity'] * ($lastPrices[$symbol] ?? $position['last_price']);
            $finalHoldings[] = $this->finalizePosition($position, $lastDate, $marketValue, true);
        }

        return [
            'version' => 2,
            'closed' => $closed,
            'top_winners' => $topWinners,
            'top_losers' => $topLosers,
            'open_positions' => $finalHoldings,
        ];
    }

    /** @return OpenPosition */
    private function newPosition(BacktestTrade $trade): array
    {
        return [
            'entry_trade_id' => $trade->id,
            'symbol' => $trade->symbol,
            'name' => $trade->name ?? $trade->symbol,
            'entry_date' => $trade->date,
            'quantity' => 0,
            'buy_value' => 0.0,
            'purchase_cost' => 0.0,
            'sell_value' => 0.0,
            'remaining_cost' => 0.0,
            'charges' => 0.0,
            'last_price' => 0.0,
        ];
    }

    /**
     * @param  OpenPosition  $position
     * @return Position
     */
    private function finalizePosition(array $position, CarbonInterface $endDate, float $marketValue, bool $stillHeld): array
    {
        $netPnl = round($position['sell_value'] + $marketValue - $position['buy_value'] - $position['charges'], 2);
        $realizedPnl = round($position['sell_value'] + $position['remaining_cost'] - $position['buy_value'] - $position['charges'], 2);

        return [
            'entry_trade_id' => $position['entry_trade_id'],
            'symbol' => $position['symbol'],
            'name' => $position['name'],
            'entry_date' => $position['entry_date']->toDateString(),
            'exit_date' => $stillHeld ? null : $endDate->toDateString(),
            'holding_days' => (int) $position['entry_date']->diffInDays($endDate),
            'quantity' => $position['quantity'],
            'buy_value' => round($position['buy_value'], 2),
            'purchase_cost' => round($position['purchase_cost'], 2),
            'sell_value' => round($position['sell_value'], 2),
            'unrealized_value' => round($marketValue, 2),
            'remaining_cost' => round($position['remaining_cost'], 2),
            'charges' => round($position['charges'], 2),
            'realized_pnl' => $realizedPnl,
            'unrealized_pnl' => round($netPnl - $realizedPnl, 2),
            'net_pnl' => $netPnl,
            'pnl_pct' => $position['purchase_cost'] > 0 ? round($netPnl / $position['purchase_cost'] * 100, 2) : 0.0,
            'still_held' => $stillHeld,
        ];
    }

    /**
     * @param  array{net_pnl: list<Position>, pnl_pct: list<Position>}  $lists
     * @param  Position  $position
     */
    private function retainTopPositions(array &$lists, array $position, bool $descending): void
    {
        foreach (['net_pnl', 'pnl_pct'] as $sortKey) {
            $lists[$sortKey][] = $position;
            usort($lists[$sortKey], fn (array $left, array $right): int => ($descending ? $right[$sortKey] <=> $left[$sortKey] : $left[$sortKey] <=> $right[$sortKey])
                ?: strcmp($left['entry_date'], $right['entry_date'])
                ?: $left['entry_trade_id'] <=> $right['entry_trade_id']);

            if (count($lists[$sortKey]) > self::TOP_POSITION_LIMIT) {
                array_pop($lists[$sortKey]);
            }
        }
    }

    /**
     * Match the engine's last known close when a final-day quote is absent.
     *
     * @param  list<string>  $symbols
     * @return array<string, float>
     */
    private function lastPrices(Backtest $backtest, array $symbols, CarbonInterface $lastDate): array
    {
        if ($symbols === []) {
            return [];
        }

        $table = (new BacktestNseInstrumentPrice)->getTable();
        $lastDates = BacktestNseInstrumentPrice::query()
            ->select('symbol')->selectRaw('MAX(date) as last_date')
            ->where('close_adjusted', '>', 0)
            ->whereIn('symbol', $symbols)
            ->where('date', '<=', $lastDate->toDateString())
            ->whereIn('date', $backtest->dailySnapshots()->select('date'))
            ->groupBy('symbol');

        return BacktestNseInstrumentPrice::query()
            ->joinSub($lastDates, 'last_quotes', function (JoinClause $join) use ($table): void {
                $join->on($table.'.symbol', '=', 'last_quotes.symbol')
                    ->on($table.'.date', '=', 'last_quotes.last_date');
            })
            ->pluck($table.'.close_adjusted', $table.'.symbol')
            ->map(fn ($price): float => (float) $price)
            ->all();
    }
}
