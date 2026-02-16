export interface NseIndex {
    id: number;
    symbol: string;
    slug: string;
    date: string;
    open: number | null;
    high: number | null;
    low: number | null;
    close: number;
    points_change: number | null;
    percentage_change: number | null;
    volume: number | null;
    turnover: number | null;
    price_to_earnings: number | null;
    price_to_book: number | null;
    dividend_yield: number | null;
}
