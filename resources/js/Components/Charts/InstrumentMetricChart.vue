<template>
    <div class="min-w-0" :data-metric="metric.key">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <ChartTimeRangeControl v-model="selectedTimeRange" @select="nextTick(applyTimeRange)" />
            <button
                type="button"
                :aria-pressed="showMedian"
                aria-label="Show median line"
                class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 self-start rounded-lg border px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-purple-600 sm:self-auto"
                :class="showMedian ? 'border-purple-200 bg-purple-50 text-purple-700' : 'border-gray-200 text-gray-500'"
                @click="showMedian = !showMedian"
            >
                <span aria-hidden="true" class="w-4 border-t border-dashed border-current" /> Median
            </button>
        </div>
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">{{ metric.title }}</h3>
                <p class="mt-1 max-w-lg text-xs leading-5 text-gray-500">{{ metric.description }}</p>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 sm:min-w-64 sm:text-right">
                <div>
                    <dt class="text-xs text-gray-500">{{ inspectedPoint ? 'Selected date' : 'Last value in view' }}</dt>
                    <dd data-testid="metric-value" class="mt-1 text-lg font-semibold tabular-nums" :style="{ color: metric.color }">
                        {{ formatValue(displayPoint?.value ?? null) }}
                    </dd>
                    <p v-if="displayPoint" class="mt-1 text-xs text-gray-500">{{ formatDate(displayPoint.time) }}</p>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Median · visible period</dt>
                    <dd data-testid="metric-median" class="mt-1 text-lg font-semibold text-gray-700 tabular-nums">{{ formatValue(median) }}</dd>
                </div>
            </dl>
        </div>
        <div v-if="availablePoints.length" class="relative mt-4 h-[300px] sm:h-[400px]">
            <div ref="chartContainer" role="img" :aria-label="`${metric.title} history chart`" class="h-full min-w-0" />
            <p
                v-if="!visiblePoints.length"
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/90 p-6 text-center text-sm text-gray-500"
            >
                No data in this period. Select a longer period or move the chart to an earlier date.
            </p>
        </div>
        <div v-else class="mt-4 flex h-[300px] flex-col items-center justify-center gap-2 rounded-lg bg-gray-50 p-6 text-center sm:h-[400px]">
            <p class="text-sm font-medium text-gray-600">No {{ metric.title.toLowerCase() }} history is available.</p>
            <p class="text-xs text-gray-500">The other chart tabs may have data for this instrument.</p>
        </div>
        <div class="mt-4 flex flex-wrap justify-between gap-2 border-t border-gray-100 pt-3 text-xs leading-5 text-gray-500">
            <p v-if="availablePoints.length">
                Data from {{ formatDate(availablePoints[0].time) }} · {{ visiblePoints.length.toLocaleString('en-IN') }} values in view
            </p>
            <p>Move over the chart to inspect a date. The median updates when you zoom or move the chart.</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import ChartTimeRangeControl from '@/Components/Charts/ChartTimeRangeControl.vue';
import type { InstrumentHistoryMetric, InstrumentHistoryRecord } from '@/types/InstrumentHistoryRecord';
import { formatDate } from '@/utils';
import { chartTimeRanges } from '@/utils/chartTimeRanges';
import type { ChartTimeRangeKey } from '@/utils/chartTimeRanges';
import { AreaSeries, ColorType, CrosshairMode, LineStyle, createChart } from 'lightweight-charts';
import type { IChartApi, IPriceLine, ISeriesApi, Time } from 'lightweight-charts';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

interface MetricPoint {
    time: string;
    value: number | null;
}

const props = defineProps<{ history: InstrumentHistoryRecord[]; metric: InstrumentHistoryMetric }>();
const selectedTimeRange = defineModel<ChartTimeRangeKey>('timeRange', { default: '1y' });
const showMedian = ref(true);
const chartContainer = ref<HTMLDivElement>();
const inspectedPoint = ref<MetricPoint | null>(null);
const visibleRange = ref<{ from: string; to: string } | null>(null);
let chart: IChartApi | null = null;
let series: ISeriesApi<'Area'> | null = null;
let medianLine: IPriceLine | null = null;

const points = computed<MetricPoint[]>(() =>
    props.history.map((row) => {
        const raw = row[props.metric.key];
        const value = raw === null || raw === undefined || String(raw).trim() === '' ? null : Number(raw);
        return { time: row.date.slice(0, 10), value: value !== null && Number.isFinite(value) ? value : null };
    }),
);
const availablePoints = computed(() => points.value.filter((point): point is MetricPoint & { value: number } => point.value !== null));
const visiblePoints = computed(() =>
    availablePoints.value.filter((point) => visibleRange.value && point.time >= visibleRange.value.from && point.time <= visibleRange.value.to),
);
const displayPoint = computed(() => inspectedPoint.value ?? visiblePoints.value.at(-1));
const median = computed(() => {
    const values = visiblePoints.value.map((point) => point.value).sort((first, second) => first - second);
    if (!values.length) return null;
    const middle = Math.floor(values.length / 2);
    return values.length % 2 ? values[middle] : (values[middle - 1] + values[middle]) / 2;
});

function formatValue(value: number | null): string {
    if (value === null) return '—';
    const formatted = value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (props.metric.unit === 'percent') return `${formatted}%`;
    if (props.metric.unit === 'crore') return `₹${formatted} cr`;
    return formatted;
}

function timeString(time: Time): string {
    if (typeof time === 'string') return time;
    if (typeof time === 'number') return new Date(time * 1000).toISOString().slice(0, 10);
    return `${time.year}-${String(time.month).padStart(2, '0')}-${String(time.day).padStart(2, '0')}`;
}

function updateMedianLine(): void {
    if (!series) return;
    if (!showMedian.value || median.value === null) {
        if (medianLine) series.removePriceLine(medianLine);
        medianLine = null;
        return;
    }
    if (medianLine) {
        medianLine.applyOptions({ price: median.value });
    } else {
        medianLine = series.createPriceLine({
            price: median.value,
            color: '#94a3b8',
            lineWidth: 1,
            lineStyle: LineStyle.Dashed,
            axisLabelVisible: true,
            title: 'Median',
        });
    }
}

function applyTimeRange(): void {
    if (!chart || !points.value.length) return;
    const days = chartTimeRanges.find((range) => range.key === selectedTimeRange.value)?.days ?? Infinity;
    const start = Math.max(0, points.value.length - days);
    visibleRange.value = { from: points.value[start].time, to: points.value.at(-1)!.time };
    inspectedPoint.value = null;
    const end = points.value.length - 1;
    chart.timeScale().setVisibleLogicalRange({ from: start, to: end > start ? end : start + 1 });
    updateMedianLine();
}

function updateData(): void {
    if (!series) return;
    series.setData(points.value.map((point) => (point.value === null ? { time: point.time } : { time: point.time, value: point.value })));
    applyTimeRange();
}

function destroyChart(): void {
    chart?.remove();
    chart = null;
    series = null;
    medianLine = null;
}

watch(chartContainer, (container) => {
    destroyChart();
    if (!container) return;
    chart = createChart(container, {
        autoSize: true,
        layout: { background: { type: ColorType.Solid, color: '#ffffff' }, textColor: '#6b7280', fontFamily: 'inherit' },
        grid: { vertLines: { visible: false }, horzLines: { color: '#f1f5f9' } },
        crosshair: { mode: CrosshairMode.Magnet },
        rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.12, bottom: 0.1 } },
        timeScale: { borderColor: '#e2e8f0' },
        handleScroll: { vertTouchDrag: false },
    });
    series = chart.addSeries(AreaSeries, {
        lineColor: props.metric.color,
        topColor: `${props.metric.color}22`,
        bottomColor: `${props.metric.color}00`,
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
        priceFormat: { type: 'custom', minMove: 0.01, formatter: (value: number) => value.toLocaleString('en-IN', { maximumFractionDigits: 2 }) },
    });
    chart.timeScale().subscribeVisibleTimeRangeChange((range) => {
        visibleRange.value = range ? { from: timeString(range.from), to: timeString(range.to) } : null;
    });
    chart.subscribeCrosshairMove((event) => {
        if (event.time === undefined || !series) {
            inspectedPoint.value = null;
            return;
        }
        const point = event.seriesData.get(series) as { value?: number } | undefined;
        inspectedPoint.value = { time: timeString(event.time), value: point?.value ?? null };
    });
    updateData();
});

watch(selectedTimeRange, applyTimeRange);
watch(points, updateData);
watch([median, showMedian], updateMedianLine);
onUnmounted(destroyChart);
</script>
