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
    rolling_returns_one_year: Array<{ date: string; return: number }> | null;
    rolling_returns_three_year: Array<{ date: string; return: number }> | null;
    rolling_returns_five_year: Array<{ date: string; return: number }> | null;
    stock_performance: Array<{
        symbol: string;
        name: string;
        buy_value: number;
        sell_value: number;
        unrealized_value: number;
        charges: number;
        net_pnl: number;
        pnl_pct: number;
        still_held: boolean;
    }> | null;
}
