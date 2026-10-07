export interface MarketCapAllocationPoint {
    date: string;
    large_cap: number;
    mid_cap: number;
    small_cap: number;
    etf: number;
    unclassified: number;
    cash: number;
}

export interface MarketCapAllocation {
    start_date: string | null;
    excluded_days: number;
    points: MarketCapAllocationPoint[];
}
