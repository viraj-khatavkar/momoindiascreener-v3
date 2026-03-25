export interface BacktestSummaryMetric {
    id: number;
    backtest_id: number;
    cagr: number;
    benchmark_cagr: number;
    max_drawdown: number;
    max_drawdown_start_date: string | null;
    max_drawdown_end_date: string | null;
    benchmark_max_drawdown: number;
    total_trades: number;
    total_charges_paid: number;
    final_value: number;
    rolling_returns_one_year: Array<{ date: string; return: number }> | null;
    rolling_returns_three_year: Array<{ date: string; return: number }> | null;
    rolling_returns_five_year: Array<{ date: string; return: number }> | null;
}
