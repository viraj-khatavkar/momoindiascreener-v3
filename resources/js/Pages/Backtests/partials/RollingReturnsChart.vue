<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div v-if="legendEntries.length > 0" class="mb-3 flex flex-wrap items-center gap-4">
            <div v-for="entry in legendEntries" :key="entry.label" class="flex items-center gap-1.5 text-xs">
                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: entry.color }" />
                <span class="font-medium text-gray-700">{{ entry.label }}</span>
            </div>
        </div>

        <div
            ref="chartContainer"
            class="h-[350px]"
            role="img"
            aria-label="Rolling returns chart: annualized 1-year, 3-year and 5-year rolling returns of the strategy over the backtest period"
            @dblclick="resetView"
        />

        <p class="mt-2 text-[11px] text-gray-400">Scroll to zoom · drag to pan · double-click to reset</p>
    </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { LineSeries, ColorType, CrosshairMode, LineStyle, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';
import type { ChartSyncGroup } from '@/utils/chartSyncGroup';

const props = defineProps<{
    oneYear: Array<{ date: string; return: number }> | null;
    threeYear: Array<{ date: string; return: number }> | null;
    fiveYear: Array<{ date: string; return: number }> | null;
    syncGroup?: ChartSyncGroup;
}>();

const ONE_YEAR_COLOR = '#f59e0b';
const THREE_YEAR_COLOR = '#0ea5e9';
const FIVE_YEAR_COLOR = '#64748b';

const legendEntries = computed<{ label: string; color: string }[]>(() => {
    const entries: { label: string; color: string }[] = [];
    if (props.oneYear && props.oneYear.length > 0) entries.push({ label: '1Y', color: ONE_YEAR_COLOR });
    if (props.threeYear && props.threeYear.length > 0) entries.push({ label: '3Y', color: THREE_YEAR_COLOR });
    if (props.fiveYear && props.fiveYear.length > 0) entries.push({ label: '5Y', color: FIVE_YEAR_COLOR });
    return entries;
});

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let oneYearSeries: ISeriesApi<SeriesType> | null = null;
let threeYearSeries: ISeriesApi<SeriesType> | null = null;
let fiveYearSeries: ISeriesApi<SeriesType> | null = null;
let unregisterSync: (() => void) | null = null;

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

    if (props.oneYear && props.oneYear.length > 0) {
        oneYearSeries = chart.addSeries(LineSeries, {
            color: ONE_YEAR_COLOR,
            lineWidth: 2,
            title: '1Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    if (props.threeYear && props.threeYear.length > 0) {
        threeYearSeries = chart.addSeries(LineSeries, {
            color: THREE_YEAR_COLOR,
            lineWidth: 2,
            title: '3Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    if (props.fiveYear && props.fiveYear.length > 0) {
        fiveYearSeries = chart.addSeries(LineSeries, {
            color: FIVE_YEAR_COLOR,
            lineWidth: 2,
            title: '5Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    setData();

    // Zero reference line — rolling returns oscillate around it
    const anchorSeries = oneYearSeries ?? threeYearSeries ?? fiveYearSeries;
    anchorSeries?.createPriceLine({
        price: 0,
        color: '#9ca3af',
        lineWidth: 1,
        lineStyle: LineStyle.Dashed,
        axisLabelVisible: false,
        title: '',
    });

    chart.timeScale().fitContent();

    if (props.syncGroup && anchorSeries) {
        unregisterSync = props.syncGroup.register(chart, anchorSeries);
    }
}

function resetView(): void {
    chart?.timeScale().fitContent();
}

function toSeriesData(data: Array<{ date: string; return: number }> | null) {
    if (!data) return [];
    return data.map((d) => ({
        time: d.date.substring(0, 10) as unknown as Time,
        value: Math.round(d.return * 10000) / 100, // convert to percentage
    }));
}

function setData(): void {
    if (oneYearSeries) oneYearSeries.setData(toSeriesData(props.oneYear));
    if (threeYearSeries) threeYearSeries.setData(toSeriesData(props.threeYear));
    if (fiveYearSeries) fiveYearSeries.setData(toSeriesData(props.fiveYear));
}

function destroyChart(): void {
    if (chart) {
        unregisterSync?.();
        unregisterSync = null;
        chart.remove();
        chart = null;
        oneYearSeries = null;
        threeYearSeries = null;
        fiveYearSeries = null;
    }
}

watch(chartContainer, (el) => {
    if (!el) {
        destroyChart();
        return;
    }
    if (!chart) initChart(el);
});

onUnmounted(destroyChart);
</script>
