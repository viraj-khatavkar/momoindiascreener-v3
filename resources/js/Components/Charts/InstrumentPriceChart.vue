<template>
    <div class="min-w-0">
        <!-- Chart Controls -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Time Range Buttons -->
            <div class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1">
                <button
                    v-for="range in timeRanges"
                    :key="range.key"
                    type="button"
                    :aria-pressed="selectedTimeRange === range.key"
                    :aria-label="`Show ${range.description}`"
                    class="min-h-10 cursor-pointer rounded-md px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                    :class="selectedTimeRange === range.key ? 'bg-white text-purple-700 shadow-xs' : 'text-gray-700 hover:bg-gray-50'"
                    @click="selectedTimeRange = range.key"
                >
                    {{ range.label }}
                </button>
            </div>

            <!-- Indicator Picker -->
            <Popover class="relative">
                <PopoverButton
                    aria-label="Chart indicators"
                    class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-purple-600"
                >
                    Indicators
                    <span
                        v-if="activeIndicators.size > 0"
                        class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-purple-600 text-xs text-white"
                    >
                        {{ activeIndicators.size }}
                    </span>
                </PopoverButton>
                <PopoverPanel
                    class="absolute left-0 z-20 mt-2 max-h-[min(28rem,60vh)] w-64 overflow-y-auto overscroll-contain rounded-lg border border-gray-200 bg-white p-3 shadow-lg sm:right-0 sm:left-auto"
                >
                    <div class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">On the price chart</div>
                    <button
                        v-for="ind in overlayIndicators"
                        :key="ind.id"
                        type="button"
                        :aria-pressed="activeIndicators.has(ind.id)"
                        class="flex min-h-10 w-full cursor-pointer items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-gray-50"
                        @click="toggleIndicator(ind.id)"
                    >
                        <span
                            class="inline-block h-3 w-3 rounded-full border"
                            :style="{
                                backgroundColor: activeIndicators.has(ind.id) ? ind.plots[0].color : 'transparent',
                                borderColor: ind.plots[0].color,
                            }"
                        />
                        <span :class="activeIndicators.has(ind.id) ? 'font-medium text-gray-900' : 'text-gray-600'">
                            {{ ind.label }}
                        </span>
                    </button>
                    <div v-if="paneIndicators.length > 0" class="mt-3 mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">
                        Separate charts
                    </div>
                    <button
                        v-for="ind in paneIndicators"
                        :key="ind.id"
                        type="button"
                        :aria-pressed="activeIndicators.has(ind.id)"
                        class="flex min-h-10 w-full cursor-pointer items-center gap-2 rounded px-2 py-2 text-left text-sm hover:bg-gray-50"
                        @click="toggleIndicator(ind.id)"
                    >
                        <span
                            class="inline-block h-3 w-3 rounded-full border"
                            :style="{
                                backgroundColor: activeIndicators.has(ind.id) ? ind.plots[0].color : 'transparent',
                                borderColor: ind.plots[0].color,
                            }"
                        />
                        <span :class="activeIndicators.has(ind.id) ? 'font-medium text-gray-900' : 'text-gray-600'">
                            {{ ind.label }}
                        </span>
                    </button>
                </PopoverPanel>
            </Popover>
        </div>

        <!-- Active Indicator Pills -->
        <div v-if="activeIndicators.size > 0" class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-gray-500">Active:</span>
            <span
                v-for="id in activeIndicators"
                :key="id"
                class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700"
            >
                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: getIndicatorConfig(id)?.plots[0].color }" />
                {{ getIndicatorConfig(id)?.label }} {{ getIndicatorConfig(id) ? formatParamsLabel(getIndicatorConfig(id)!) : '' }}
                <button
                    type="button"
                    :aria-label="`Remove ${getIndicatorConfig(id)?.label}`"
                    class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    @click="toggleIndicator(id)"
                >
                    &times;
                </button>
            </span>
        </div>

        <!-- Main Chart -->
        <div class="relative mt-4 h-[300px] sm:h-[400px]">
            <div ref="chartContainer" class="h-full" />
            <div
                v-if="legendItems.length > 0"
                class="pointer-events-none absolute top-2 left-2 z-10 flex flex-col gap-0.5 rounded bg-white/80 px-2 py-1 text-xs backdrop-blur-sm"
            >
                <div v-for="item in legendItems" :key="item.label" class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: item.color }" />
                    <span class="font-medium text-gray-700">{{ item.label }}:</span>
                    <span class="text-gray-600">{{ item.value }}</span>
                </div>
            </div>
        </div>

        <!-- Pane Charts -->
        <div v-for="id in activePaneIndicatorIds" :key="id" :ref="(el) => setPaneRef(id, el as HTMLDivElement | null)" class="mt-1 h-[150px]" />
    </div>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { AreaSeries, ColorType, CrosshairMode, HistogramSeries, LineSeries, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, LineStyle, SeriesType, Time } from 'lightweight-charts';
import type { Bar } from 'oakscriptjs';
import type { PriceHistoryRecord } from '@/utils/chartDataConverter';
import { pricesToBars } from '@/utils/chartDataConverter';
import { computeIndicator } from '@/utils/indicatorCalculator';
import { formatParamsLabel, getIndicatorConfig, getOverlayIndicators, getPaneIndicators } from '@/utils/indicatorRegistry';
import type { IndicatorConfig } from '@/utils/indicatorRegistry';

const props = defineProps<{
    priceHistory: PriceHistoryRecord[];
}>();

const timeRanges = [
    { key: '1m', description: '1 month', label: '1M', days: 22 },
    { key: '6m', description: '6 months', label: '6M', days: 126 },
    { key: '1y', description: '1 year', label: '1Yr', days: 252 },
    { key: '3y', description: '3 years', label: '3Yr', days: 756 },
    { key: '5y', description: '5 years', label: '5Yr', days: 1260 },
    { key: '10y', description: '10 years', label: '10Yr', days: 2520 },
    { key: 'max', description: 'all available history', label: 'Max', days: Infinity },
] as const;

type TimeRangeKey = (typeof timeRanges)[number]['key'];

const selectedTimeRange = ref<TimeRangeKey>('1y');

// --- Indicator state ---
const activeIndicators = ref<Set<string>>(new Set(['sma-200']));
const overlayIndicators = getOverlayIndicators();
const paneIndicators = getPaneIndicators();

const activePaneIndicatorIds = computed(() =>
    [...activeIndicators.value].filter((id) => {
        const config = getIndicatorConfig(id);
        return config?.category === 'pane';
    }),
);

function toggleIndicator(id: string): void {
    const next = new Set(activeIndicators.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    activeIndicators.value = next;
}

function toDateString(iso: string): string {
    return iso.slice(0, 10);
}

// --- Lightweight Charts ---
const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let mainSeries: ISeriesApi<SeriesType> | null = null;
const overlaySeriesMap = new Map<string, ISeriesApi<SeriesType>[]>();
const seriesMeta = new Map<ISeriesApi<SeriesType>, { label: string; color: string }>();
const legendItems = ref<{ label: string; color: string; value: string }[]>([]);
const paneCharts = new Map<string, { chart: IChartApi; series: ISeriesApi<SeriesType>[] }>();
const paneRefs = new Map<string, HTMLDivElement>();
let cachedBars: Bar[] | null = null;
let isSyncing = false;

const chartOptions = {
    layout: {
        background: { type: ColorType.Solid, color: '#ffffff' },
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
} as const;

function setPaneRef(id: string, el: HTMLDivElement | null): void {
    if (el) {
        paneRefs.set(id, el);
    } else {
        paneRefs.delete(id);
    }
}

function getBars(): Bar[] {
    if (!cachedBars && props.priceHistory) {
        cachedBars = pricesToBars(props.priceHistory);
    }
    return cachedBars ?? [];
}

function updateLegendDefaults(): void {
    if (!props.priceHistory || props.priceHistory.length === 0) {
        legendItems.value = [];
        return;
    }

    const items: { label: string; color: string; value: string }[] = [];
    const lastPrice = props.priceHistory[props.priceHistory.length - 1];
    items.push({ label: 'Price', color: '#7c3aed', value: Number(lastPrice.close_adjusted).toFixed(2) });

    for (const [series, meta] of seriesMeta) {
        const latest = series.data().at(-1);
        items.push({
            label: meta.label,
            color: meta.color,
            value: latest && 'value' in latest ? latest.value.toFixed(2) : '—',
        });
    }

    legendItems.value = items;
}

function initChart(container: HTMLDivElement): void {
    chart = createChart(container, {
        autoSize: true,
        ...chartOptions,
    });

    chart.subscribeCrosshairMove((param) => {
        if (!param.time || !param.seriesData.size) {
            updateLegendDefaults();
            return;
        }

        const items: { label: string; color: string; value: string }[] = [];

        if (mainSeries) {
            const data = param.seriesData.get(mainSeries) as { value?: number } | undefined;
            if (data?.value !== undefined) {
                items.push({ label: 'Price', color: '#7c3aed', value: data.value.toFixed(2) });
            }
        }

        for (const [series, meta] of seriesMeta) {
            const data = param.seriesData.get(series) as { value?: number } | undefined;
            if (data?.value !== undefined) {
                items.push({ label: meta.label, color: meta.color, value: data.value.toFixed(2) });
            }
        }

        legendItems.value = items;
    });

    updateMainSeries();
    syncIndicators();
    applyTimeRange();
    updateLegendDefaults();
}

function updateMainSeries(): void {
    if (!chart || !props.priceHistory) {
        return;
    }

    if (mainSeries) {
        chart.removeSeries(mainSeries);
        mainSeries = null;
    }

    mainSeries = chart.addSeries(AreaSeries, {
        lineColor: '#7c3aed',
        topColor: 'rgba(124, 58, 237, 0.15)',
        bottomColor: 'rgba(124, 58, 237, 0)',
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
    });
    mainSeries.setData(
        props.priceHistory.map((p) => ({
            time: toDateString(p.date) as Time,
            value: Number(p.close_adjusted),
        })),
    );
}

function syncIndicators(): void {
    if (!chart) {
        return;
    }

    const bars = getBars();
    if (bars.length === 0) {
        return;
    }

    const activeIds = activeIndicators.value;

    // --- Overlays: remove stale, add new ---
    for (const [id, seriesList] of overlaySeriesMap) {
        if (!activeIds.has(id)) {
            for (const s of seriesList) {
                chart.removeSeries(s);
                seriesMeta.delete(s);
            }
            overlaySeriesMap.delete(id);
        }
    }

    for (const id of activeIds) {
        const config = getIndicatorConfig(id);
        if (!config || config.category !== 'overlay') {
            continue;
        }
        if (overlaySeriesMap.has(id)) {
            continue;
        }

        const computed = computeIndicator(bars, config);
        const seriesList: ISeriesApi<SeriesType>[] = [];

        for (let i = 0; i < config.plots.length; i++) {
            const plotStyle = config.plots[i];
            const plotData = computed.plots[i]?.data ?? [];

            const series = chart.addSeries(LineSeries, {
                color: plotStyle.color,
                lineWidth: (plotStyle.lineWidth ?? 1) as 1 | 2 | 3 | 4,
                lineStyle: plotStyle.lineStyle as LineStyle | undefined,
                lastValueVisible: false,
                priceLineVisible: false,
            });
            series.setData(plotData);
            seriesMeta.set(series, { label: plotStyle.label, color: plotStyle.color });
            seriesList.push(series);
        }

        overlaySeriesMap.set(id, seriesList);
    }

    // --- Pane indicators: remove stale, add new ---
    for (const [id, pane] of paneCharts) {
        if (!activeIds.has(id)) {
            pane.chart.remove();
            paneCharts.delete(id);
        }
    }

    for (const id of activeIds) {
        const config = getIndicatorConfig(id);
        if (!config || config.category !== 'pane') {
            continue;
        }
        if (paneCharts.has(id)) {
            continue;
        }

        const el = paneRefs.get(id);
        if (!el) {
            continue;
        }

        createPaneChart(id, config, bars, el);
    }
}

function createPaneChart(id: string, config: IndicatorConfig, bars: Bar[], container: HTMLDivElement): void {
    const paneChart = createChart(container, {
        autoSize: true,
        ...chartOptions,
        timeScale: {
            ...chartOptions.timeScale,
            visible: false,
        },
    });

    const computed = computeIndicator(bars, config);
    const seriesList: ISeriesApi<SeriesType>[] = [];

    for (let i = 0; i < config.plots.length; i++) {
        const plotStyle = config.plots[i];
        const plotData = computed.plots[i]?.data ?? [];

        if (plotStyle.seriesKind === 'histogram') {
            const series = paneChart.addSeries(HistogramSeries, {
                color: plotStyle.color,
                lastValueVisible: false,
                priceLineVisible: false,
            });
            series.setData(plotData);
            seriesList.push(series);
        } else {
            const series = paneChart.addSeries(LineSeries, {
                color: plotStyle.color,
                lineWidth: (plotStyle.lineWidth ?? 1) as 1 | 2 | 3 | 4,
                lastValueVisible: false,
                priceLineVisible: false,
            });
            series.setData(plotData);
            seriesList.push(series);
        }
    }

    paneCharts.set(id, { chart: paneChart, series: seriesList });

    // Sync time scale from main chart
    if (chart) {
        const mainRange = chart.timeScale().getVisibleLogicalRange();
        if (mainRange) {
            paneChart.timeScale().setVisibleLogicalRange(mainRange);
        }
    }

    // Subscribe pane to sync back
    paneChart.timeScale().subscribeVisibleLogicalRangeChange((range) => {
        if (isSyncing || !range) {
            return;
        }
        isSyncing = true;
        if (chart) {
            chart.timeScale().setVisibleLogicalRange(range);
        }
        for (const [paneId, pane] of paneCharts) {
            if (paneId !== id) {
                pane.chart.timeScale().setVisibleLogicalRange(range);
            }
        }
        isSyncing = false;
    });
}

function setupMainTimeScaleSync(): void {
    if (!chart) {
        return;
    }

    chart.timeScale().subscribeVisibleLogicalRangeChange((range) => {
        if (isSyncing || !range) {
            return;
        }
        isSyncing = true;
        for (const [, pane] of paneCharts) {
            pane.chart.timeScale().setVisibleLogicalRange(range);
        }
        isSyncing = false;
    });
}

function applyTimeRange(): void {
    if (!chart || !props.priceHistory || props.priceHistory.length === 0) {
        return;
    }

    const data = props.priceHistory;
    const range = timeRanges.find((r) => r.key === selectedTimeRange.value);

    if (!range || range.days === Infinity) {
        chart.timeScale().fitContent();
        for (const [, pane] of paneCharts) {
            pane.chart.timeScale().fitContent();
        }
        return;
    }

    const startIndex = Math.max(0, data.length - range.days);
    const from = toDateString(data[startIndex].date);
    const to = toDateString(data[data.length - 1].date);

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

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function destroyChart(): void {
    for (const [, pane] of paneCharts) {
        pane.chart.remove();
    }
    paneCharts.clear();

    if (chart) {
        chart.remove();
        chart = null;
        mainSeries = null;
        overlaySeriesMap.clear();
        seriesMeta.clear();
        legendItems.value = [];
    }
}

// Create/destroy chart when container appears/disappears
watch(chartContainer, (el) => {
    if (!el) {
        destroyChart();
        return;
    }
    if (!chart) {
        initChart(el);
        setupMainTimeScaleSync();
    }
});

// Rebuild chart when priceHistory changes (Inertia navigation between instruments)
watch(
    () => props.priceHistory,
    () => {
        cachedBars = null;
        if (chart) {
            updateMainSeries();
            // Remove all indicator series so they get recomputed
            for (const [, seriesList] of overlaySeriesMap) {
                for (const s of seriesList) {
                    chart.removeSeries(s);
                }
            }
            overlaySeriesMap.clear();
            seriesMeta.clear();
            for (const [, pane] of paneCharts) {
                pane.chart.remove();
            }
            paneCharts.clear();
            syncIndicators();
            applyTimeRange();
        }
    },
);

watch(selectedTimeRange, () => {
    if (!chart) {
        return;
    }
    applyTimeRange();
});

watch(
    activeIndicators,
    async () => {
        if (!chart) {
            return;
        }
        // Wait for pane DOM elements to render
        await nextTick();
        syncIndicators();
        applyTimeRange();
        updateLegendDefaults();
    },
    { deep: true },
);

onUnmounted(destroyChart);
</script>
