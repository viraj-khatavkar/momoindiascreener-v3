import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';

export interface PaginatedBacktestTrades {
    data: BacktestTrade[];
    current_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
}

export interface TradeLogFilters {
    search: string;
    type: 'all' | 'buy' | 'sell';
    reason: string | null;
    sort: 'asc' | 'desc';
    year: number | null;
}

export interface TradeLogSummary {
    total: number;
    buys: number;
    sells: number;
    counts: Record<'all' | 'buy' | 'sell', number>;
    reasons: Record<string, number>;
    years: number[];
}
