<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div class="relative h-[300px]">
            <div
                ref="chartContainer"
                class="h-full"
                role="img"
                aria-label="Cash allocation chart: percentage of the portfolio held in cash over the backtest period"
                @dblclick="resetView"
            />
            <div v-if="legend" class="pointer-events-none absolute top-2 left-2 z-10 rounded bg-white/80 px-2 py-1 text-xs backdrop-blur-sm">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500" />
                    <span class="font-medium text-gray-700">{{ legend.date }}</span>
                    <span class="text-gray-600">Cash {{ legend.value }}</span>
                </div>
            </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-400">Scroll to zoom · drag to pan · double-click to reset</p>
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';
import { formatDate, formatPercent } from '@/utils/format';
import type { ChartSyncGroup } from '@/utils/chartSyncGroup';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

const props = defineProps<{
    dailySnapshots: BacktestDailySnapshot[];
    syncGroup?: ChartSyncGroup;
}>();

const chartContainer = ref<HTMLDivElement>();
const legend = ref<{ date: string; value: string } | null>(null);
let chart: IChartApi | null = null;
let series: ISeriesApi<SeriesType> | null = null;
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

    series = chart.addSeries(AreaSeries, {
        lineColor: '#3b82f6',
        topColor: 'rgba(59, 130, 246, 0.2)',
        bottomColor: 'rgba(59, 130, 246, 0)',
        lineWidth: 2,
        lastValueVisible: true,
        priceLineVisible: false,
        title: 'Cash %',
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
    updateLegendDefault();
    chart.timeScale().fitContent();

    if (props.syncGroup) {
        unregisterSync = props.syncGroup.register(chart, series);
    }
}

function setData(): void {
    if (!series) {
        return;
    }

    const data = props.dailySnapshots.map((s) => {
        const total = Number(s.total_value);
        const cashPct = total > 0 ? (Number(s.cash) / total) * 100 : 0;
        return {
            time: s.date.substring(0, 10) as unknown as Time,
            value: Math.round(cashPct * 100) / 100,
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

function destroyChart(): void {
    if (chart) {
        unregisterSync?.();
        unregisterSync = null;
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
    if (!chart) {
        initChart(el);
    }
});

watch(
    () => props.dailySnapshots,
    () => {
        if (chart) {
            setData();
            updateLegendDefault();
            chart.timeScale().fitContent();
        }
    },
);

onUnmounted(destroyChart);
</script>
