import type { LineStyle } from 'lightweight-charts';

export type IndicatorCategory = 'overlay' | 'pane';

export type SeriesKind = 'line' | 'histogram';

export interface PlotStyle {
    plotKey: string;
    label: string;
    color: string;
    seriesKind: SeriesKind;
    lineStyle?: LineStyle;
    lineWidth?: number;
}

export interface IndicatorConfig {
    id: string;
    label: string;
    category: IndicatorCategory;
    libraryId: string;
    params: Record<string, unknown>;
    plots: PlotStyle[];
}

export function formatParamsLabel(config: IndicatorConfig): string {
    const nums = Object.entries(config.params)
        .filter(([, val]) => typeof val === 'number')
        .map(([, val]) => val);

    if (nums.length === 0) {
        return '';
    }

    return `(${nums.join(', ')})`;
}

export const indicatorCatalog: IndicatorConfig[] = [
    {
        id: 'sma-20',
        label: 'SMA 20',
        category: 'overlay',
        libraryId: 'sma',
        params: { len: 20 },
        plots: [{ plotKey: 'plot0', label: 'SMA 20', color: '#f59e0b', seriesKind: 'line', lineWidth: 1 }],
    },
    {
        id: 'sma-50',
        label: 'SMA 50',
        category: 'overlay',
        libraryId: 'sma',
        params: { len: 50 },
        plots: [{ plotKey: 'plot0', label: 'SMA 50', color: '#f97316', seriesKind: 'line', lineWidth: 1 }],
    },
    {
        id: 'sma-100',
        label: 'SMA 100',
        category: 'overlay',
        libraryId: 'sma',
        params: { len: 100 },
        plots: [{ plotKey: 'plot0', label: 'SMA 100', color: '#ef4444', seriesKind: 'line', lineWidth: 1 }],
    },
    {
        id: 'sma-200',
        label: 'SMA 200',
        category: 'overlay',
        libraryId: 'sma',
        params: { len: 200 },
        plots: [{ plotKey: 'plot0', label: 'SMA 200', color: '#10b981', seriesKind: 'line', lineWidth: 2 }],
    },
    {
        id: 'ema-20',
        label: 'EMA 20',
        category: 'overlay',
        libraryId: 'ema',
        params: { length: 20 },
        plots: [{ plotKey: 'plot0', label: 'EMA 20', color: '#818cf8', seriesKind: 'line', lineWidth: 1, lineStyle: 2 as LineStyle }],
    },
    {
        id: 'ema-50',
        label: 'EMA 50',
        category: 'overlay',
        libraryId: 'ema',
        params: { length: 50 },
        plots: [{ plotKey: 'plot0', label: 'EMA 50', color: '#a78bfa', seriesKind: 'line', lineWidth: 1, lineStyle: 2 as LineStyle }],
    },
    {
        id: 'ema-100',
        label: 'EMA 100',
        category: 'overlay',
        libraryId: 'ema',
        params: { length: 100 },
        plots: [{ plotKey: 'plot0', label: 'EMA 100', color: '#c084fc', seriesKind: 'line', lineWidth: 1, lineStyle: 2 as LineStyle }],
    },
    {
        id: 'ema-200',
        label: 'EMA 200',
        category: 'overlay',
        libraryId: 'ema',
        params: { length: 200 },
        plots: [{ plotKey: 'plot0', label: 'EMA 200', color: '#06b6d4', seriesKind: 'line', lineWidth: 2, lineStyle: 2 as LineStyle }],
    },
];

export function getIndicatorConfig(id: string): IndicatorConfig | undefined {
    return indicatorCatalog.find((c) => c.id === id);
}

export function getOverlayIndicators(): IndicatorConfig[] {
    return indicatorCatalog.filter((c) => c.category === 'overlay');
}

export function getPaneIndicators(): IndicatorConfig[] {
    return indicatorCatalog.filter((c) => c.category === 'pane');
}
