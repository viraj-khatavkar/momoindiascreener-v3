<template>
    <div>
        <div class="flex items-baseline gap-2">
            <h3 class="text-sm font-medium text-gray-700">{{ title }}</h3>
            <span class="text-xs text-gray-500">{{ displayValue }}</span>
        </div>
        <div ref="chartContainer" class="mt-1 h-[200px]" />
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

const props = withDefaults(
    defineProps<{
        title: string;
        data: DataPoint[];
        color?: string;
    }>(),
    {
        color: '#7c3aed',
    },
);

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let series: ISeriesApi<SeriesType> | null = null;

const displayValue = ref('');

function getLastValue(): string {
    if (props.data.length === 0) {
        return '';
    }
    return props.data[props.data.length - 1].value.toFixed(2);
}

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
        lineColor: props.color,
        topColor: hexToRgba(props.color, 0.15),
        bottomColor: hexToRgba(props.color, 0),
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

    chart.timeScale().fitContent();

    displayValue.value = getLastValue();

    chart.subscribeCrosshairMove((param) => {
        if (!param.time || !param.seriesData.size || !series) {
            displayValue.value = getLastValue();
            return;
        }

        const data = param.seriesData.get(series) as { value?: number } | undefined;
        if (data?.value !== undefined) {
            displayValue.value = data.value.toFixed(2);
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

function hexToRgba(hex: string, alpha: number): string {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
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
            chart.timeScale().fitContent();
            displayValue.value = getLastValue();
        }
    },
);

onUnmounted(destroyChart);
</script>
