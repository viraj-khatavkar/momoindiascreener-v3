import type { PriceHistoryRecord } from '@/utils/chartDataConverter';

export interface InstrumentHistoryRecord extends PriceHistoryRecord {
    absolute_return_one_year: number | string | null;
    price_to_earnings: number | string | null;
    marketcap: number | string | null;
    rsi_one_year: number | string | null;
}

export interface InstrumentHistoryMetric {
    key: 'absolute_return_one_year' | 'price_to_earnings' | 'marketcap' | 'rsi_one_year';
    label: string;
    title: string;
    description: string;
    color: string;
    unit: 'percent' | 'crore' | 'number';
}
