import type { Backtest } from '@/types/app/Models/Backtest';
import type { Screen } from '@/types/app/Models/Screen';

export type HomeScreen = Pick<Screen, 'id' | 'name' | 'index' | 'sort_by' | 'sort_direction'>;

export interface HomeBacktest {
    id: number;
    name: string;
    index: string;
    status: Backtest['status'];
    progress: number;
    updated_at: string | null;
    url: string;
    summary_metrics: {
        cagr: number;
        max_drawdown: number;
    } | null;
}
