import type { IChartApi, ISeriesApi, MouseEventParams, SeriesType, Time } from 'lightweight-charts';

interface SyncEntry {
    chart: IChartApi;
    series: ISeriesApi<SeriesType>;
}

/**
 * Keeps multiple lightweight-charts instances in lockstep: panning/zooming any
 * chart mirrors its visible range to the others, and the crosshair's vertical
 * (time) line follows the cursor across all registered charts.
 */
export class ChartSyncGroup {
    private entries: SyncEntry[] = [];

    private syncingRange = false;

    register(chart: IChartApi, series: ISeriesApi<SeriesType>): () => void {
        const entry: SyncEntry = { chart, series };
        this.entries.push(entry);

        const onRangeChange = (range: { from: Time; to: Time } | null): void => {
            if (this.syncingRange || !range) {
                return;
            }
            this.syncingRange = true;
            try {
                for (const other of this.entries) {
                    if (other.chart !== chart && other.chart.timeScale().getVisibleRange() !== null) {
                        other.chart.timeScale().setVisibleRange(range);
                    }
                }
            } finally {
                this.syncingRange = false;
            }
        };

        const onCrosshairMove = (param: MouseEventParams): void => {
            const point = param.time !== undefined ? (param.seriesData.get(series) as { value?: number } | undefined) : undefined;

            for (const other of this.entries) {
                if (other.chart === chart) {
                    continue;
                }
                if (param.time !== undefined && point?.value !== undefined) {
                    // The price anchor is only used for the horizontal line; the
                    // point of syncing is the shared vertical time line.
                    // lightweight-charts throws when the target chart's visible
                    // window contains no data for its series — skip quietly.
                    try {
                        other.chart.setCrosshairPosition(point.value, param.time, other.series);
                    } catch {
                        other.chart.clearCrosshairPosition();
                    }
                } else {
                    other.chart.clearCrosshairPosition();
                }
            }
        };

        chart.timeScale().subscribeVisibleTimeRangeChange(onRangeChange);
        chart.subscribeCrosshairMove(onCrosshairMove);

        return () => {
            chart.timeScale().unsubscribeVisibleTimeRangeChange(onRangeChange);
            chart.unsubscribeCrosshairMove(onCrosshairMove);
            this.entries = this.entries.filter((e) => e !== entry);
        };
    }
}
