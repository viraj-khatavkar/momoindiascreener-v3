<template>
    <section class="rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-200 sm:p-6" aria-labelledby="market-cap-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="market-cap-heading" class="text-sm font-semibold tracking-wide text-gray-500 uppercase">Market Cap Allocation Over Time</h2>
                <p class="mt-1 text-sm text-gray-600">Daily allocation as a percentage of total portfolio value.</p>
            </div>
            <div v-if="points.length > 1" class="flex gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="Allocation chart period">
                <button
                    v-for="range in visibleRanges"
                    :key="range.label"
                    type="button"
                    :aria-pressed="selectedRange === range.days"
                    class="cursor-pointer rounded-md px-3 py-1 text-xs font-medium"
                    :class="selectedRange === range.days ? 'bg-white text-purple-700 shadow-xs' : 'text-gray-600 hover:bg-white'"
                    @click="selectedRange = range.days"
                >
                    {{ range.label }}
                </button>
            </div>
        </div>

        <div v-if="selectedPoint" class="mt-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                <p>Data from {{ formatDate(allocation.start_date!) }}. Earlier dates are excluded.</p>
                <p class="font-semibold text-gray-700">
                    {{ selectedIndex === points.length - 1 ? 'Latest:' : 'Selected:' }} {{ formatDate(selectedPoint.date) }}
                </p>
            </div>

            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3" :class="hasUnclassified ? 'xl:grid-cols-6' : 'lg:grid-cols-5'">
                <div v-for="category in categories" :key="category.key" class="rounded-lg border border-gray-200 p-3">
                    <dt class="flex items-center gap-2 text-sm font-medium text-gray-600">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: category.color }" />
                        {{ category.label }}
                    </dt>
                    <dd class="mt-2 text-2xl font-semibold text-gray-900 tabular-nums">{{ selectedPoint[category.key].toFixed(1) }}%</dd>
                    <dd class="mt-1 text-xs text-gray-500">{{ category.description }}</dd>
                </div>
            </dl>

            <div class="rounded-lg bg-slate-50 p-2 sm:p-4">
                <ul
                    class="mb-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs font-medium text-gray-700"
                    aria-label="Market cap chart colour legend"
                >
                    <li v-for="category in categories" :key="category.key" class="flex items-center gap-2">
                        <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: category.color }" aria-hidden="true" />
                        {{ category.label }}
                    </li>
                </ul>
                <div
                    ref="chartContainer"
                    class="h-[300px]"
                    role="img"
                    aria-label="Stacked area chart of portfolio allocation, from zero to 100 percent"
                    @dblclick="resetView"
                />
                <label for="allocation-date" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-600">
                    <span>Move across the chart or use the slider to select a date.</span>
                    <span class="font-medium">{{ formatDate(selectedPoint.date) }}</span>
                </label>
                <input
                    id="allocation-date"
                    v-model.number="selectedIndex"
                    type="range"
                    min="0"
                    :max="points.length - 1"
                    step="1"
                    :disabled="points.length < 2"
                    :aria-valuetext="formatDate(selectedPoint.date)"
                    class="mt-2 w-full cursor-pointer accent-purple-600"
                    @focus="dateSliderFocused = true"
                    @blur="dateSliderFocused = false"
                    @input="showSelectedDate"
                />
                <p class="mt-2 text-xs text-gray-500">Scroll to zoom · drag to pan · double-click to reset</p>
            </div>

            <p class="text-xs leading-relaxed text-gray-500">
                Stock groups use index membership on each date. Stocks outside these two indices count as small caps. ETFs, including gold ETFs,
                are shown separately. If a quote is missing, the last known price and group are used.
                <span v-if="hasUnclassified">Holdings without a known group are shown as Unclassified. Their value is included in the total.</span>
                <span v-if="allocation.excluded_days > 0">{{ allocation.excluded_days }} dates without sufficient data are excluded.</span>
            </p>
        </div>

        <p v-else class="mt-5 rounded-lg bg-slate-50 p-5 text-sm leading-relaxed text-gray-600">
            No allocation data is available for this backtest period. The analysis needs historical membership data for both Nifty 100 and Nifty
            Midcap 150, plus prices for the holdings. Earlier dates are excluded.
        </p>
    </section>
</template>

<script setup lang="ts">
import { AreaSeries, ColorType, CrosshairMode, LineType, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, Time } from 'lightweight-charts';
import { computed, onUnmounted, ref, watch } from 'vue';
import type { MarketCapAllocation, MarketCapAllocationPoint } from '@/types/MarketCapAllocation';
import { formatDate } from '@/utils/format';

const props = defineProps<{ allocation: MarketCapAllocation }>();

type CategoryKey = Exclude<keyof MarketCapAllocationPoint, 'date'>;

const allCategories: { key: CategoryKey; label: string; description: string; color: string }[] = [
    { key: 'large_cap', label: 'Large cap', description: 'Nifty 100', color: '#3b82f6' },
    { key: 'mid_cap', label: 'Mid cap', description: 'Nifty Midcap 150', color: '#8b5cf6' },
    { key: 'small_cap', label: 'Small cap', description: 'All other stocks', color: '#14b8a6' },
    { key: 'etf', label: 'ETFs', description: 'Includes gold ETFs', color: '#f59e0b' },
    { key: 'unclassified', label: 'Unclassified', description: 'Membership unavailable', color: '#f43f5e' },
    { key: 'cash', label: 'Cash', description: 'Uninvested balance', color: '#94a3b8' },
];

const ranges = [
    { label: '1Y', days: 252 },
    { label: '3Y', days: 756 },
    { label: '5Y', days: 1260 },
    { label: 'All', days: Infinity },
];

const points = computed(() => props.allocation.points);
const hasUnclassified = computed(() => points.value.some((point) => point.unclassified > 0));
const categories = computed(() => allCategories.filter((category) => category.key !== 'unclassified' || hasUnclassified.value));
const pointIndices = computed(() => new Map(points.value.map((point, index) => [point.date, index])));
const visibleRanges = computed(() => ranges.filter((range) => range.days < points.value.length || range.days === Infinity));
const selectedRange = ref(Infinity);
const selectedIndex = ref(Math.max(0, points.value.length - 1));
const dateSliderFocused = ref(false);
const selectedPoint = computed(() => points.value[selectedIndex.value]);
const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
const series = new Map<CategoryKey, ISeriesApi<'Area'>>();

function initChart(container: HTMLDivElement): void {
    chart = createChart(container, {
        autoSize: true,
        layout: { background: { type: ColorType.Solid, color: '#f8fafc' }, textColor: '#6b7280' },
        grid: { vertLines: { visible: false }, horzLines: { color: '#e2e8f0' } },
        crosshair: { mode: CrosshairMode.Normal, horzLine: { visible: false, labelVisible: false } },
        rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.03, bottom: 0 } },
        timeScale: { borderColor: '#e2e8f0' },
        handleScale: { axisPressedMouseMove: { price: false, time: true } },
        localization: { priceFormatter: (value: number) => `${value.toFixed(0)}%` },
    });

    // Draw cumulative, opaque areas from the total down to the first category.
    for (const category of [...categories.value].reverse()) {
        series.set(
            category.key,
            chart.addSeries(AreaSeries, {
                topColor: category.color,
                bottomColor: category.color,
                lineColor: category.color,
                lineType: LineType.WithSteps,
                lineVisible: false,
                lastValueVisible: false,
                priceLineVisible: false,
                crosshairMarkerVisible: false,
                autoscaleInfoProvider: () => ({ priceRange: { minValue: 0, maxValue: 100 } }),
            }),
        );
    }

    chart.subscribeCrosshairMove((event) => {
        if (dateSliderFocused.value || event.time === undefined || !event.sourceEvent) return;
        const index = pointIndices.value.get(String(event.time));
        if (index !== undefined) selectedIndex.value = index;
    });

    setData();
    applyRange();
}

function setData(): void {
    for (const [index, category] of categories.value.entries()) {
        series.get(category.key)?.setData(
            points.value.map((point) => ({
                time: point.date as Time,
                value: categories.value.slice(0, index + 1).reduce((total, item) => total + point[item.key], 0),
            })),
        );
    }
}

function applyRange(): void {
    if (!chart || !points.value.length) return;
    if (selectedRange.value === Infinity) {
        chart.timeScale().fitContent();
        return;
    }
    chart.timeScale().setVisibleRange({
        from: points.value[Math.max(0, points.value.length - selectedRange.value)].date as Time,
        to: points.value[points.value.length - 1].date as Time,
    });
}

function resetView(): void {
    selectedRange.value = Infinity;
    applyRange();
}

function showSelectedDate(): void {
    const anchor = series.get('cash');
    if (anchor && selectedPoint.value) chart?.setCrosshairPosition(100, selectedPoint.value.date as Time, anchor);
}

function destroyChart(): void {
    chart?.remove();
    chart = null;
    series.clear();
}

watch(chartContainer, (container) => {
    destroyChart();
    if (container) initChart(container);
});

watch(points, () => {
    selectedIndex.value = Math.max(0, points.value.length - 1);
    if (chart) {
        if (series.size !== categories.value.length && chartContainer.value) {
            destroyChart();
            initChart(chartContainer.value);
            return;
        }
        setData();
        applyRange();
    }
});

watch(selectedRange, applyRange);
onUnmounted(destroyChart);
</script>
