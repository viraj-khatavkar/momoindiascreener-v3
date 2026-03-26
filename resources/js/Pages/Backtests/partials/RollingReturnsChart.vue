<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <div ref="chartContainer" class="h-[350px]" />
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { LineSeries, ColorType, CrosshairMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';

const props = defineProps<{
    oneYear: Array<{ date: string; return: number }> | null;
    threeYear: Array<{ date: string; return: number }> | null;
    fiveYear: Array<{ date: string; return: number }> | null;
}>();

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let oneYearSeries: ISeriesApi<SeriesType> | null = null;
let threeYearSeries: ISeriesApi<SeriesType> | null = null;
let fiveYearSeries: ISeriesApi<SeriesType> | null = null;

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
            color: '#7c3aed',
            lineWidth: 2,
            title: '1Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    if (props.threeYear && props.threeYear.length > 0) {
        threeYearSeries = chart.addSeries(LineSeries, {
            color: '#2563eb',
            lineWidth: 2,
            title: '3Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    if (props.fiveYear && props.fiveYear.length > 0) {
        fiveYearSeries = chart.addSeries(LineSeries, {
            color: '#059669',
            lineWidth: 2,
            title: '5Y',
            lastValueVisible: true,
            priceLineVisible: false,
        });
    }

    setData();
    chart.timeScale().fitContent();
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
        chart.remove();
        chart = null;
        oneYearSeries = null;
        threeYearSeries = null;
        fiveYearSeries = null;
    }
}

watch(chartContainer, (el) => {
    if (!el) { destroyChart(); return; }
    if (!chart) initChart(el);
});

onUnmounted(destroyChart);
</script>
