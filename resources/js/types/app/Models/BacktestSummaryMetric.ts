export interface BacktestPosition {
    entry_trade_id: number;
    symbol: string;
    name: string;
    entry_date: string;
    exit_date: string | null;
    holding_days: number;
    quantity: number;
    buy_value: number;
    purchase_cost: number;
    sell_value: number;
    unrealized_value: number;
    remaining_cost: number;
    charges: number;
    realized_pnl: number;
    unrealized_pnl: number;
    net_pnl: number;
    pnl_pct: number;
    still_held: boolean;
}

export interface BacktestPositionPerformance {
    version: 2;
    closed: {
        count: number;
        winners: number;
        losers: number;
        breakeven: number;
        total_profit: number;
        total_loss: number;
        net_pnl: number;
        average_win: number | null;
        average_loss: number | null;
        expectancy: number | null;
        average_holding_days: number | null;
        winners_percentage: number | null;
        profit_factor: number | null;
    };
    top_winners: Record<'net_pnl' | 'pnl_pct', BacktestPosition[]>;
    top_losers: Record<'net_pnl' | 'pnl_pct', BacktestPosition[]>;
    open_positions: BacktestPosition[];
}

export interface BacktestSummaryMetric {
    id: number;
    backtest_id: number;
    cagr: number;
    max_drawdown: number;
    max_drawdown_start_date: string | null;
    max_drawdown_end_date: string | null;
    sharpe_ratio: number | null;
    winners_percentage: number | null;
    ulcer_index: number | null;
    k_ratio: number | null;
    profit_factor: number | null;
    total_trades: number;
    total_charges_paid: number;
    final_value: number;
    start_date: string | null;
    end_date: string | null;
    rolling_returns_one_year: Array<{ date: string; return: number }> | null;
    rolling_returns_three_year: Array<{ date: string; return: number }> | null;
    rolling_returns_five_year: Array<{ date: string; return: number }> | null;
    stock_performance: BacktestPositionPerformance | null;
}
