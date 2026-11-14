<template>
    <div :class="embedded ? 'min-w-0' : 'rounded-lg border border-gray-200 bg-white p-4 md:p-5'">
        <div v-if="!embedded" class="flex items-baseline justify-between">
            <h3 class="text-sm font-semibold text-gray-700">{{ title }}</h3>
            <span class="text-lg font-bold" :class="valueColorClass">
                {{ displayValue }}
            </span>
        </div>
        <p v-if="embedded" class="mb-2 min-h-5 text-xs text-[#706b7d]" data-testid="chart-inspection">
            <template v-if="inspectedPoint">{{ formatDate(inspectedPoint.time) }} · {{ formatValue(inspectedPoint.value) }}</template>
            <template v-else>Move over the chart or use the arrow keys to inspect a date.</template>
        </p>
        <div v-if="hasValues" class="relative">
            <div
                ref="chartContainer"
                tabindex="0"
                role="group"
                :aria-label="`${title} history chart. Use left and right arrow keys to inspect values.`"
                class="min-w-0 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                :class="embedded ? 'h-[250px] sm:h-[300px]' : tall ? 'mt-3 h-[450px]' : 'mt-3 h-[300px]'"
                @keydown="inspectWithKeyboard"
                @blur="clearInspection"
                @mouseleave="clearInspection"
            />
            <p
                v-if="!hasVisibleValues"
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-white/90 p-6 text-center text-sm text-gray-500"
            >
                No data in this view. Reset the view or select a longer period.
            </p>
        </div>
        <div v-else class="flex h-[250px] items-center justify-center px-6 text-center text-sm text-[#706b7d] sm:h-[300px]">
            No values are available for this metric and period.
        </div>
        <span class="sr-only" aria-live="polite">{{ keyboardAnnouncement }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, LineStyle, TickMarkType, createChart } from 'lightweight-charts';
import type { IChartApi, IPriceLine, ISeriesApi, Time } from 'lightweight-charts';

interface DataPoint {
    time: string;
    value: number | null;
}

const props = withDefaults(
    defineProps<{
        title: string;
        data: DataPoint[];
        color?: string;
        referenceLine?: number;
        tall?: boolean;
        embedded?: boolean;
        fitContent?: boolean;
        unit?: 'number' | 'percent' | 'ratio' | 'count' | 'points';
        fixedPercentScale?: boolean;
        decimals?: number;
    }>(),
    {
        color: '#7c3aed',
        unit: 'number',
        fixedPercentScale: true,
    },
);

const emit = defineEmits<{ rangeChange: [range: { from: string; to: string } | null] }>();
const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let series: ISeriesApi<'Area'> | null = null;
let referenceLine: IPriceLine | null = null;

const inspectedPoint = ref<DataPoint | null>(null);
const keyboardAnnouncement = ref('');
const visibleRange = ref<{ from: string; to: string } | null>(null);
const availablePoints = computed(() =>
    props.data.filter((point): point is DataPoint & { value: number } => point.value !== null && Number.isFinite(point.value)),
);
const hasValues = computed(() => availablePoints.value.length > 0);
const visiblePoints = computed(() =>
    availablePoints.value.filter((point) => !visibleRange.value || (point.time >= visibleRange.value.from && point.time <= visibleRange.value.to)),
);
const hasVisibleValues = computed(() => visiblePoints.value.length > 0);
const displayValue = computed(() => formatValue(inspectedPoint.value?.value ?? props.data.at(-1)?.value ?? null));

const valueColorClass = computed(() => {
    const value = inspectedPoint.value?.value ?? props.data.at(-1)?.value;
    if (value == null || props.referenceLine == null) {
        return 'text-gray-900';
    }
    return value >= props.referenceLine ? 'text-green-600' : 'text-red-600';
});

function timeString(time: Time): string {
    if (typeof time === 'string') {
        return time;
    }
    if (typeof time === 'number') {
        return new Date(time * 1000).toISOString().slice(0, 10);
    }
    return `${time.year}-${String(time.month).padStart(2, '0')}-${String(time.day).padStart(2, '0')}`;
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(`${value}T00:00:00Z`),
    );
}

function formatValue(value: number | null): string {
    if (value === null) {
        return '—';
    }
    if (props.unit === 'count') {
        return Math.round(value).toLocaleString('en-IN');
    }
    const decimals = props.decimals ?? (props.unit === 'percent' ? 1 : 2);
    const formatted = value.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    return `${formatted}${props.unit === 'percent' ? '%' : props.unit === 'ratio' ? '×' : props.unit === 'points' ? ' pts' : ''}`;
}

function formatTick(time: Time, type: TickMarkType): string {
    const value = new Date(`${timeString(time)}T00:00:00Z`);
    const options: Intl.DateTimeFormatOptions =
        type === TickMarkType.Year
            ? { year: 'numeric' }
            : type === TickMarkType.Month
              ? { month: 'short', year: '2-digit' }
              : { day: 'numeric', month: 'short' };
    return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: 'UTC' }).format(value);
}

function initChart(container: HTMLDivElement): void {
    chart = createChart(container, {
        autoSize: true,
        layout: {
            background: { type: ColorType.Solid, color: '#ffffff' },
            textColor: props.embedded ? '#706b7d' : '#6b7280',
            fontFamily: 'inherit',
            fontSize: 11,
        },
        grid: {
            vertLines: { color: '#f1f5f9', visible: !props.embedded },
            horzLines: { color: props.embedded ? '#eeebf2' : '#e2e8f0' },
        },
        crosshair: {
            mode: CrosshairMode.Magnet,
        },
        rightPriceScale: {
            borderColor: '#e2e8f0',
            visible: !props.embedded,
        },
        leftPriceScale: { visible: props.embedded, borderVisible: false, scaleMargins: { top: 0.05, bottom: 0.05 } },
        timeScale: {
            borderColor: '#e2e8f0',
            borderVisible: !props.embedded,
            rightOffset: 5,
            minBarSpacing: 0.001,
            lockVisibleTimeRangeOnResize: props.embedded,
            tickMarkFormatter: props.embedded ? formatTick : undefined,
        },
        handleScroll: { vertTouchDrag: false },
    });

    series = chart.addSeries(AreaSeries, {
        lineColor: props.color,
        topColor: hexToRgba(props.color, props.embedded ? 0.12 : 0.25),
        bottomColor: hexToRgba(props.color, 0.02),
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
        priceFormat: {
            type: 'custom',
            minMove: props.unit === 'count' ? 1 : 10 ** -(props.decimals ?? (props.unit === 'percent' ? 1 : 2)),
            formatter: (value: number) =>
                props.unit === 'percent'
                    ? `${Number(value.toFixed(props.decimals ?? 1))}%`
                    : props.unit === 'points'
                      ? value.toLocaleString('en-IN', { maximumFractionDigits: 0 })
                      : formatValue(value),
        },
        autoscaleInfoProvider:
            props.unit === 'percent' && props.fixedPercentScale ? () => ({ priceRange: { minValue: 0, maxValue: 100 } }) : undefined,
    });

    chart.timeScale().subscribeVisibleTimeRangeChange((range) => {
        visibleRange.value = range ? { from: timeString(range.from), to: timeString(range.to) } : null;
        emit('rangeChange', visibleRange.value);
    });
    chart.subscribeCrosshairMove((param) => {
        if (!param.time || !param.seriesData.size || !series) {
            inspectedPoint.value = null;
            return;
        }

        const data = param.seriesData.get(series) as { value?: number } | undefined;
        inspectedPoint.value = { time: timeString(param.time), value: data?.value ?? null };
    });
    updateData();
}

function resetView(): void {
    if (!chart || props.data.length === 0) {
        return;
    }

    clearInspection();
    if (props.fitContent || props.data.length < 2) {
        chart.timeScale().fitContent();
        return;
    }
    const lastDate = props.data.at(-1)!.time;
    const from = new Date(`${lastDate}T00:00:00Z`);
    from.setUTCFullYear(from.getUTCFullYear() - 1);
    chart.timeScale().setVisibleRange({ from: from.toISOString().slice(0, 10), to: lastDate });
}

function updateData(): void {
    if (!series) {
        return;
    }
    series.setData(props.data.map((point) => (point.value === null ? { time: point.time } : { time: point.time, value: point.value })));
    if (referenceLine) {
        series.removePriceLine(referenceLine);
        referenceLine = null;
    }
    if (props.referenceLine != null) {
        referenceLine = series.createPriceLine({
            price: props.referenceLine,
            color: '#b4a8c6',
            lineStyle: LineStyle.Dashed,
            lineWidth: 1,
            axisLabelVisible: false,
        });
    }
    resetView();
}

function clearInspection(): void {
    inspectedPoint.value = null;
    keyboardAnnouncement.value = '';
    chart?.clearCrosshairPosition();
}

function inspectWithKeyboard(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        clearInspection();
        return;
    }
    if (!chart || !series || !visiblePoints.value.length || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }
    event.preventDefault();
    const points = visiblePoints.value;
    const currentIndex = inspectedPoint.value ? points.findIndex((point) => point.time === inspectedPoint.value?.time) : points.length - 1;
    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? points.length - 1 : currentIndex + (event.key === 'ArrowLeft' ? -1 : 1);
    const point = points[Math.max(0, Math.min(points.length - 1, nextIndex))];
    chart.setCrosshairPosition(point.value, point.time, series);
    inspectedPoint.value = point;
    keyboardAnnouncement.value = `${formatDate(point.time)}: ${formatValue(point.value)}`;
}

function destroyChart(): void {
    if (chart) {
        chart.remove();
        chart = null;
        series = null;
        referenceLine = null;
    }
    inspectedPoint.value = null;
    visibleRange.value = null;
    emit('rangeChange', null);
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

watch(() => props.data, updateData);

onUnmounted(destroyChart);
defineExpose({ resetView });
</script>
