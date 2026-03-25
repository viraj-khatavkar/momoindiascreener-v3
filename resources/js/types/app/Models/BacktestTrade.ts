export interface BacktestTrade {
    id: number;
    backtest_id: number;
    symbol: string;
    name: string | null;
    trade_type: 'buy' | 'sell';
    reason: 'entry' | 'exit_rank_exceeded' | 'exit_not_in_universe' | 'exit_cash_call' | 'rebalance_weight_adjustment';
    date: string;
    quantity: number;
    price: number;
    raw_price: number;
    gross_amount: number;
    stt: number;
    transaction_charges: number;
    sebi_charges: number;
    gst: number;
    stamp_charges: number;
    total_charges: number;
    net_amount: number;
}
