import type { Bar } from 'oakscriptjs';
import { indicatorRegistry as libraryRegistry } from 'lightweight-charts-indicators';
import type { Time } from 'lightweight-charts';
import { unixToDateString } from './chartDataConverter';
import type { IndicatorConfig } from './indicatorRegistry';

export interface ComputedPlot {
    plotKey: string;
    data: { time: Time; value: number }[];
}

export interface ComputedIndicator {
    config: IndicatorConfig;
    plots: ComputedPlot[];
}

export function computeIndicator(bars: Bar[], config: IndicatorConfig): ComputedIndicator {
    const entry = libraryRegistry.find((e) => e.id === config.libraryId);
    if (!entry) {
        throw new Error(`Indicator "${config.libraryId}" not found in library registry`);
    }

    const result = entry.calculate(bars, config.params);

    const plots: ComputedPlot[] = config.plots.map((plotStyle) => {
        const rawData = result.plots[plotStyle.plotKey] ?? [];
        return {
            plotKey: plotStyle.plotKey,
            data: rawData
                .filter((d: { time: number; value: number }) => d.value === d.value) // filter NaN
                .map((d: { time: number; value: number }) => ({
                    time: unixToDateString(d.time) as Time,
                    value: d.value,
                })),
        };
    });

    return { config, plots };
}
