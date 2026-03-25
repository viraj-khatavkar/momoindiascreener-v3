<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div ref="chartContainer" class="h-[300px]" />
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

const props = defineProps<{
    dailySnapshots: BacktestDailySnapshot[];
}>();

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
        lineColor: '#3b82f6',
        topColor: 'rgba(59, 130, 246, 0.2)',
        bottomColor: 'rgba(59, 130, 246, 0)',
        lineWidth: 2,
        lastValueVisible: true,
        priceLineVisible: false,
        title: 'Cash %',
    });

    setData();
    chart.timeScale().fitContent();
}

function setData(): void {
    if (!series) {
        return;
    }

    series.setData(
        props.dailySnapshots.map((s) => {
            const total = Number(s.total_value);
            const cashPct = total > 0 ? (Number(s.cash) / total) * 100 : 0;
            return {
                time: s.date.substring(0, 10) as unknown as Time,
                value: Math.round(cashPct * 100) / 100,
            };
        }),
    );
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
    () => props.dailySnapshots,
    () => {
        if (chart) {
            setData();
            chart.timeScale().fitContent();
        }
    },
);

onUnmounted(destroyChart);
</script>
