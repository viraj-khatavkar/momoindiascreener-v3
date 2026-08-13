import type { MarketIndex } from '@/types/app/Models/MarketIndex';

export interface MarketIndexAlias {
    id: number;
    source_label: string;
    normalized_label: string;
    suggested_slug: string | null;
    status: 'pending' | 'approved' | 'ignored';
    sample_symbol: string | null;
    first_seen_on: string | null;
    last_seen_on: string | null;
    reviewed_at: string | null;
    instruments_count: number;
    market_index: MarketIndex | null;
    suggested_market_index: MarketIndex | null;
    reviewer: { id: number; name: string } | null;
}
