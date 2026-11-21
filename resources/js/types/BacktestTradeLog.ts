import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';

export interface TradeDayTotals {
    buys: number;
    sells: number;
    bought: number;
    sold: number;
    charges: number;
}

export interface PaginatedBacktestTrades {
    data: (BacktestTrade & { day_totals?: TradeDayTotals })[];
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
    symbol?: string | null;
    from?: string | null;
    through?: string | null;
}

export interface PositionTradeScope {
    symbol: string;
    from: string;
    through: string;
}

export interface TradeLogSummary {
    total: number;
    buys: number;
    sells: number;
    counts: Record<'all' | 'buy' | 'sell', number>;
    reasons: Record<string, number>;
    years: number[];
}
