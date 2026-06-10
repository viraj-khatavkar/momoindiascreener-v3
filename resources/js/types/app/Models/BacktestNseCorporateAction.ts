export interface BacktestNseCorporateAction {
    id: number;
    date: string;
    symbol: string;
    series: string | null;
    type: 'bonus' | 'split' | 'dividend' | 'rights' | 'demerger' | null;
    description: string | null;
    ratio: string | null;
    dividend: string | null;
    dividend_adjustment_factor: string | null;
    price_adjustment_factor: string | null;
    dividend_adjustment_applied_at: string | null;
    price_adjustment_applied_at: string | null;
}
