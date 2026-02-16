<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div class="inline-flex rounded-md border border-gray-300 bg-white">
            <button
                v-for="range in timeRanges"
                :key="range.key"
                type="button"
                class="cursor-pointer px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                :class="
                    selectedRange === range.key
                        ? 'bg-purple-600 text-white'
                        : 'text-gray-700 hover:bg-gray-50'
                "
                @click="selectedRange = range.key"
            >
                {{ range.label }}
            </button>
        </div>

        <div ref="chartContainer" class="mt-4 h-[400px]" />
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';

interface DataPoint {
    time: string;
    value: number;
}

const props = defineProps<{
    data: DataPoint[];
}>();

const timeRanges = [
    { key: '1y', label: '1Y', days: 252 },
    { key: '3y', label: '3Y', days: 756 },
    { key: '5y', label: '5Y', days: 1260 },
    { key: '10y', label: '10Y', days: 2520 },
    { key: 'max', label: 'Max', days: Infinity },
] as const;

type TimeRangeKey = (typeof timeRanges)[number]['key'];

const selectedRange = ref<TimeRangeKey>('max');
const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let series: ISeriesApi<SeriesType> | null = null;

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
        lineColor: '#7c3aed',
        topColor: 'rgba(124, 58, 237, 0.15)',
        bottomColor: 'rgba(124, 58, 237, 0)',
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
    });

    series.setData(
        props.data.map((d) => ({
            time: d.time as Time,
            value: d.value,
        })),
    );

    applyTimeRange();
}

function applyTimeRange(): void {
    if (!chart || props.data.length === 0) {
        return;
    }

    const range = timeRanges.find((r) => r.key === selectedRange.value);

    if (!range || range.days === Infinity) {
        chart.timeScale().fitContent();
        return;
    }

    const startIndex = Math.max(0, props.data.length - range.days);
    const from = props.data[startIndex].time;
    const to = props.data[props.data.length - 1].time;

    requestAnimationFrame(() => {
        if (!chart) {
            return;
        }
        try {
            chart.timeScale().setVisibleRange({
                from: from as Time,
                to: to as Time,
            });
        } catch {
            chart.timeScale().fitContent();
        }
    });
}

function destroyChart(): void {
    if (chart) {
        chart.remove();
        chart = null;
        series = null;
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
    () => props.data,
    () => {
        if (chart && series) {
            series.setData(
                props.data.map((d) => ({
                    time: d.time as Time,
                    value: d.value,
                })),
            );
            applyTimeRange();
        }
    },
);

watch(selectedRange, () => {
    applyTimeRange();
});

onUnmounted(destroyChart);
</script>
