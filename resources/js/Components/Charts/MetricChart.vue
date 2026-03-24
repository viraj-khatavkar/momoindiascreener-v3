<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 md:p-5">
        <div class="flex items-baseline justify-between">
            <h3 class="text-sm font-semibold text-gray-700">{{ title }}</h3>
            <span class="text-lg font-bold" :class="valueColorClass">
                {{ displayValue }}
            </span>
        </div>
        <div ref="chartContainer" class="mt-3" :class="tall ? 'h-[450px]' : 'h-[300px]'" />
    </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, LineSeries, createChart } from 'lightweight-charts';
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
        referenceLine?: number;
        tall?: boolean;
    }>(),
    {
        color: '#7c3aed',
    },
);

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let series: ISeriesApi<SeriesType> | null = null;

const displayValue = ref('');

const valueColorClass = computed(() => {
    if (!displayValue.value || props.referenceLine == null) {
        return 'text-gray-900';
    }
    return parseFloat(displayValue.value) >= props.referenceLine
        ? 'text-green-600'
        : 'text-red-600';
});

function toSeriesData(data: DataPoint[]): { time: Time; value: number }[] {
    return data.map((d) => ({ time: d.time as Time, value: d.value }));
}

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
            background: { type: ColorType.Solid, color: '#ffffff' },
            textColor: '#6b7280',
            fontFamily: 'inherit',
        },
        grid: {
            vertLines: { color: '#f1f5f9' },
            horzLines: { color: '#e2e8f0' },
        },
        crosshair: {
            mode: CrosshairMode.Magnet,
        },
        rightPriceScale: {
            borderColor: '#e2e8f0',
        },
        timeScale: {
            borderColor: '#e2e8f0',
            rightOffset: 5,
        },
    });

    series = chart.addSeries(AreaSeries, {
        lineColor: props.color,
        topColor: hexToRgba(props.color, 0.25),
        bottomColor: hexToRgba(props.color, 0.02),
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
    });

    series.setData(toSeriesData(props.data));

    if (props.referenceLine != null && props.data.length >= 2) {
        const refSeries = chart.addSeries(LineSeries, {
            color: '#9ca3af',
            lineWidth: 1,
            lineStyle: 2,
            lastValueVisible: false,
            priceLineVisible: false,
            crosshairMarkerVisible: false,
        });
        refSeries.setData([
            { time: props.data[0].time as Time, value: props.referenceLine },
            { time: props.data[props.data.length - 1].time as Time, value: props.referenceLine },
        ]);
    }

    showLast12Months();

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

function showLast12Months(): void {
    if (!chart || props.data.length === 0) {
        return;
    }

    const lastDate = props.data[props.data.length - 1].time;
    const [year, month, day] = lastDate.split('-').map(Number);
    const fromDate = `${year - 1}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    try {
        chart.timeScale().setVisibleRange({
            from: fromDate as Time,
            to: lastDate as Time,
        });
    } catch {
        chart.timeScale().fitContent();
    }
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
            series.setData(toSeriesData(props.data));
            showLast12Months();
            displayValue.value = getLastValue();
        }
    },
);

onUnmounted(destroyChart);
</script>
