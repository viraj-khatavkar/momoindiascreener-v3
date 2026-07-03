<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div class="relative h-[250px]">
            <div
                ref="chartContainer"
                class="h-full"
                role="img"
                aria-label="Drawdown chart: percentage decline from the strategy's running peak NAV over the backtest period"
                @dblclick="resetView"
            />
            <div v-if="legend" class="pointer-events-none absolute top-2 left-2 z-10 rounded bg-white/80 px-2 py-1 text-xs backdrop-blur-sm">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-600" />
                    <span class="font-medium text-gray-700">{{ legend.date }}</span>
                    <span class="text-gray-600">Drawdown {{ legend.value }}</span>
                </div>
            </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-400">Scroll to zoom · drag to pan · double-click to reset</p>
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { BaselineSeries, ColorType, CrosshairMode, createChart, createSeriesMarkers } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, ISeriesMarkersPluginApi, SeriesMarker, SeriesType, Time } from 'lightweight-charts';
import { formatDate, formatPercent } from '@/utils/format';
import type { ChartSyncGroup } from '@/utils/chartSyncGroup';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

const props = defineProps<{
    dailySnapshots: BacktestDailySnapshot[];
    syncGroup?: ChartSyncGroup;
    maxDrawdownStartDate?: string | null;
    maxDrawdownEndDate?: string | null;
}>();

const chartContainer = ref<HTMLDivElement>();
const legend = ref<{ date: string; value: string } | null>(null);
let chart: IChartApi | null = null;
let series: ISeriesApi<SeriesType> | null = null;
let markersApi: ISeriesMarkersPluginApi<Time> | null = null;
let unregisterSync: (() => void) | null = null;
let lastPoint: { time: string; value: number } | null = null;

function initChart(container: HTMLDivElement): void {
    chart = createChart(container, {
        autoSize: true,
        layout: {
            background: { type: ColorType.Solid, color: '#f8fafc' },
            textColor: '#6b7280',
        },
        grid: {
            vertLines: { color: '#f1f5f9' },
            horzLines: { color: '#f1f5f9' },
        },
        crosshair: {
            mode: CrosshairMode.Magnet,
        },
        rightPriceScale: {
            borderColor: '#e2e8f0',
        },
        timeScale: {
            borderColor: '#e2e8f0',
        },
    });

    series = chart.addSeries(BaselineSeries, {
        baseValue: { type: 'price', price: 0 },
        topLineColor: 'rgba(220, 38, 38, 0)',
        topFillColor1: 'rgba(220, 38, 38, 0)',
        topFillColor2: 'rgba(220, 38, 38, 0)',
        bottomLineColor: '#dc2626',
        bottomFillColor1: 'rgba(220, 38, 38, 0.05)',
        bottomFillColor2: 'rgba(220, 38, 38, 0.25)',
        lineWidth: 2,
        lastValueVisible: true,
        priceLineVisible: false,
        title: 'Drawdown %',
    });

    chart.subscribeCrosshairMove((param) => {
        const data = param.time !== undefined && series ? (param.seriesData.get(series) as { value?: number } | undefined) : undefined;

        if (data?.value === undefined) {
            updateLegendDefault();
            return;
        }

        legend.value = {
            date: formatDate(String(param.time)),
            value: formatPercent(data.value / 100),
        };
    });

    setData();
    setMarkers();
    updateLegendDefault();
    chart.timeScale().fitContent();

    if (props.syncGroup) {
        unregisterSync = props.syncGroup.register(chart, series);
    }
}

function setData(): void {
    if (!series) return;

    let peak = 0;
    const data = props.dailySnapshots.map((s) => {
        const nav = Number(s.nav);
        if (nav > peak) peak = nav;
        const dd = peak > 0 ? ((nav - peak) / peak) * 100 : 0;
        return {
            time: s.date.substring(0, 10) as unknown as Time,
            value: Math.round(dd * 100) / 100,
        };
    });

    series.setData(data);

    const last = data[data.length - 1];
    lastPoint = last ? { time: last.time as unknown as string, value: last.value } : null;
}

function updateLegendDefault(): void {
    legend.value = lastPoint ? { date: formatDate(lastPoint.time), value: formatPercent(lastPoint.value / 100) } : null;
}

function resetView(): void {
    chart?.timeScale().fitContent();
}

function setMarkers(): void {
    if (!series) return;

    const snapshotDates = new Set(props.dailySnapshots.map((s) => s.date.substring(0, 10)));
    const start = props.maxDrawdownStartDate?.substring(0, 10);
    const end = props.maxDrawdownEndDate?.substring(0, 10);

    const markers: SeriesMarker<Time>[] = [];

    if (start && snapshotDates.has(start)) {
        markers.push({
            time: start as unknown as Time,
            position: 'aboveBar',
            color: '#64748b',
            shape: 'arrowDown',
            text: 'Peak',
        });
    }

    if (end && snapshotDates.has(end)) {
        markers.push({
            time: end as unknown as Time,
            position: 'belowBar',
            color: '#dc2626',
            shape: 'arrowUp',
            text: 'Max DD',
        });
    }

    if (markersApi) {
        markersApi.setMarkers(markers);
    } else if (markers.length > 0) {
        markersApi = createSeriesMarkers(series, markers);
    }
}

function destroyChart(): void {
    if (chart) {
        unregisterSync?.();
        unregisterSync = null;
        markersApi = null;
        chart.remove();
        chart = null;
        series = null;
        legend.value = null;
    }
}

watch(chartContainer, (el) => {
    if (!el) {
        destroyChart();
        return;
    }
    if (!chart) initChart(el);
});

watch(
    () => props.dailySnapshots,
    () => {
        if (chart) {
            setData();
            setMarkers();
            updateLegendDefault();
            chart.timeScale().fitContent();
        }
    },
);

onUnmounted(destroyChart);
</script>
