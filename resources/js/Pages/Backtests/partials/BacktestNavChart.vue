<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div ref="chartContainer" class="h-[400px]" />
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { LineSeries, ColorType, CrosshairMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

interface BenchmarkPoint {
    date: string;
    nav: number;
}

const props = defineProps<{
    dailySnapshots: BacktestDailySnapshot[];
    benchmarkData: BenchmarkPoint[];
}>();

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let strategySeries: ISeriesApi<SeriesType> | null = null;
let benchmarkSeries: ISeriesApi<SeriesType> | null = null;

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

    strategySeries = chart.addSeries(LineSeries, {
        color: '#7c3aed',
        lineWidth: 2,
        title: 'Strategy',
        lastValueVisible: true,
        priceLineVisible: false,
    });

    benchmarkSeries = chart.addSeries(LineSeries, {
        color: '#9ca3af',
        lineWidth: 2,
        title: 'Benchmark',
        lastValueVisible: true,
        priceLineVisible: false,
    });

    setStrategyData();
    setBenchmarkData();
    chart.timeScale().fitContent();
}

function setStrategyData(): void {
    if (!strategySeries) return;
    strategySeries.setData(
        props.dailySnapshots.map((s) => ({
            time: s.date.substring(0, 10) as unknown as Time,
            value: Number(s.nav),
        })),
    );
}

function setBenchmarkData(): void {
    if (!benchmarkSeries) return;
    benchmarkSeries.setData(
        props.benchmarkData.map((s) => ({
            time: s.date.substring(0, 10) as unknown as Time,
            value: Number(s.nav),
        })),
    );
}

function destroyChart(): void {
    if (chart) {
        chart.remove();
        chart = null;
        strategySeries = null;
        benchmarkSeries = null;
    }
}

watch(chartContainer, (el) => {
    if (!el) { destroyChart(); return; }
    if (!chart) initChart(el);
});

watch(() => props.dailySnapshots, () => {
    if (chart) { setStrategyData(); chart.timeScale().fitContent(); }
});

watch(() => props.benchmarkData, () => {
    if (chart) { setBenchmarkData(); chart.timeScale().fitContent(); }
}, { immediate: true, deep: true });

onUnmounted(destroyChart);
</script>
