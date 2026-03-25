export interface BacktestDailySnapshot {
    id: number;
    backtest_id: number;
    date: string;
    nav: number;
    portfolio_value: number;
    cash: number;
    total_value: number;
    holdings_count: number;
    benchmark_close: number;
    benchmark_nav: number;
}
