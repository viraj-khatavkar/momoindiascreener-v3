export interface HomeScreen {
    id: number;
    name: string;
    index: string;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    apply_historical_date: boolean;
    historical_date: string | null;
    updated_at: string | null;
}

export interface HomeScreenPreview {
    screen_id: number;
    result_count: number;
    result_date: string | null;
    top_results: Array<{
        symbol: string;
        name: string | null;
    }>;
}

export interface HomeBacktestActivity {
    id: number;
    name: string;
    status: 'pending' | 'running' | 'failed';
    progress: number;
    started_at: string | null;
    updated_at: string | null;
}

export interface HomeRecentBacktest {
    id: number;
    name: string;
    status: 'completed';
    completed_at: string | null;
    summary_metrics: {
        cagr: number;
        max_drawdown: number;
    } | null;
}
