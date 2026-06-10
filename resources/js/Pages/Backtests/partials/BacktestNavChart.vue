<template>
    <div class="rounded-lg bg-slate-50 p-4 md:p-6">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <!-- Range presets -->
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

            <div class="flex items-center gap-2">
                <!-- Scale mode -->
                <div class="inline-flex rounded-md border border-gray-300 bg-white">
                    <button
                        v-for="mode in scaleModes"
                        :key="mode.key"
                        type="button"
                        class="cursor-pointer px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                        :class="
                            scaleMode === mode.key
                                ? 'bg-purple-600 text-white'
                                : 'text-gray-700 hover:bg-gray-50'
                        "
                        :title="mode.hint"
                        @click="scaleMode = mode.key"
                    >
                        {{ mode.label }}
                    </button>
                </div>

                <!-- Indicators popover -->
                <Popover class="relative">
                    <PopoverButton
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none"
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
                        class="absolute right-0 z-10 mt-2 w-64 rounded-lg border border-gray-200 bg-white p-3 shadow-lg"
                    >
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Overlays on strategy NAV
                        </div>
                        <button
                            v-for="ind in overlayIndicators"
                            :key="ind.id"
                            type="button"
                            class="flex w-full cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-gray-50"
                            @click="toggleIndicator(ind.id)"
                        >
                            <span
                                class="inline-block h-3 w-3 rounded-full border"
                                :style="{
                                    backgroundColor: activeIndicators.has(ind.id)
                                        ? ind.plots[0].color
                                        : 'transparent',
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
        </div>

        <!-- Active indicator pills -->
        <div v-if="activeIndicators.size > 0" class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-gray-500">Active:</span>
            <span
                v-for="id in activeIndicators"
                :key="id"
                class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700"
            >
                <span
                    class="inline-block h-2 w-2 rounded-full"
                    :style="{ backgroundColor: getIndicatorConfig(id)?.plots[0].color }"
                />
                {{ getIndicatorConfig(id)?.label }}
                <button
                    type="button"
                    class="ml-0.5 cursor-pointer text-gray-400 hover:text-gray-600"
                    @click="toggleIndicator(id)"
                >
                    &times;
                </button>
            </span>
        </div>

        <!-- Chart + crosshair legend -->
        <div class="relative mt-4 h-[400px]">
            <div ref="chartContainer" class="h-full" @dblclick="resetView" />
            <div
                v-if="legendItems.length > 0"
                class="pointer-events-none absolute left-2 top-2 z-10 flex flex-col gap-0.5 rounded bg-white/80 px-2 py-1 text-xs backdrop-blur-sm"
            >
                <div v-for="item in legendItems" :key="item.label" class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: item.color }" />
                    <span class="font-medium text-gray-700">{{ item.label }}:</span>
                    <span class="text-gray-600">{{ item.value }}</span>
                </div>
            </div>
        </div>

        <p class="mt-2 text-[11px] text-gray-400">
            Scroll to zoom · drag to pan · double-click to reset
        </p>
    </div>
</template>

<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { ColorType, CrosshairMode, LineSeries, PriceScaleMode, createChart } from 'lightweight-charts';
import type { IChartApi, ISeriesApi, LineStyle, SeriesType, Time } from 'lightweight-charts';
import type { Bar } from 'oakscriptjs';
import { computeIndicator } from '@/utils/indicatorCalculator';
import { getIndicatorConfig, getOverlayIndicators } from '@/utils/indicatorRegistry';
import type { ChartSyncGroup } from '@/utils/chartSyncGroup';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

interface BenchmarkPoint {
    date: string;
    nav: number;
}

const props = defineProps<{
    dailySnapshots: BacktestDailySnapshot[];
    benchmarkData: BenchmarkPoint[];
    syncGroup?: ChartSyncGroup;
}>();

const STRATEGY_COLOR = '#7c3aed';
const BENCHMARK_COLOR = '#9ca3af';

// --- Toolbar state ---

const timeRanges = [
    { key: '1y', label: '1Y', days: 252 },
    { key: '3y', label: '3Y', days: 756 },
    { key: '5y', label: '5Y', days: 1260 },
    { key: '10y', label: '10Y', days: 2520 },
    { key: 'max', label: 'Max', days: Infinity },
] as const;

type TimeRangeKey = (typeof timeRanges)[number]['key'];

const selectedRange = ref<TimeRangeKey>('max');

const scaleModes = [
    { key: 'linear', label: 'Lin', hint: 'Linear scale', mode: PriceScaleMode.Normal },
    { key: 'log', label: 'Log', hint: 'Logarithmic scale — equal vertical distance for equal % moves', mode: PriceScaleMode.Logarithmic },
    { key: 'percent', label: '%', hint: 'Percent change from the start of the visible window', mode: PriceScaleMode.Percentage },
] as const;

type ScaleModeKey = (typeof scaleModes)[number]['key'];

const scaleMode = ref<ScaleModeKey>('linear');

const overlayIndicators = getOverlayIndicators();
const activeIndicators = ref<Set<string>>(new Set());

function toggleIndicator(id: string): void {
    const next = new Set(activeIndicators.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    activeIndicators.value = next;
}

// --- Chart state ---

const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let strategySeries: ISeriesApi<SeriesType> | null = null;
let benchmarkSeries: ISeriesApi<SeriesType> | null = null;
const overlaySeriesMap = new Map<string, ISeriesApi<SeriesType>[]>();
const seriesMeta = new Map<ISeriesApi<SeriesType>, { label: string; color: string; lastValue: number | null }>();
const legendItems = ref<{ label: string; color: string; value: string }[]>([]);
let cachedNavBars: Bar[] | null = null;
let unregisterSync: (() => void) | null = null;

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
        color: STRATEGY_COLOR,
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
    });

    benchmarkSeries = chart.addSeries(LineSeries, {
        color: BENCHMARK_COLOR,
        lineWidth: 2,
        lastValueVisible: false,
        priceLineVisible: false,
    });

    chart.subscribeCrosshairMove((param) => {
        if (!param.time || !param.seriesData.size) {
            updateLegendDefaults();
            return;
        }

        const items: { label: string; color: string; value: string }[] = [];

        if (strategySeries) {
            const data = param.seriesData.get(strategySeries) as { value?: number } | undefined;
            if (data?.value !== undefined) {
                items.push({ label: 'Strategy', color: STRATEGY_COLOR, value: data.value.toFixed(2) });
            }
        }

        if (benchmarkSeries) {
            const data = param.seriesData.get(benchmarkSeries) as { value?: number } | undefined;
            if (data?.value !== undefined) {
                items.push({ label: 'Benchmark', color: BENCHMARK_COLOR, value: data.value.toFixed(2) });
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

    setStrategyData();
    setBenchmarkData();
    syncIndicators();
    applyScaleMode();
    applyTimeRange();
    updateLegendDefaults();

    if (props.syncGroup && strategySeries) {
        unregisterSync = props.syncGroup.register(chart, strategySeries);
    }
}

function navBars(): Bar[] {
    if (!cachedNavBars) {
        cachedNavBars = props.dailySnapshots.map((s) => {
            const nav = Number(s.nav);
            return {
                time: Math.floor(new Date(s.date.substring(0, 10)).getTime() / 1000),
                open: nav,
                high: nav,
                low: nav,
                close: nav,
                volume: 0,
            };
        });
    }
    return cachedNavBars;
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

function syncIndicators(): void {
    if (!chart) return;

    const bars = navBars();
    const activeIds = activeIndicators.value;

    // Remove stale overlays
    for (const [id, seriesList] of overlaySeriesMap) {
        if (!activeIds.has(id)) {
            for (const s of seriesList) {
                chart.removeSeries(s);
                seriesMeta.delete(s);
            }
            overlaySeriesMap.delete(id);
        }
    }

    if (bars.length === 0) return;

    // Add new overlays
    for (const id of activeIds) {
        const config = getIndicatorConfig(id);
        if (!config || config.category !== 'overlay' || overlaySeriesMap.has(id)) {
            continue;
        }

        const result = computeIndicator(bars, config);
        const seriesList: ISeriesApi<SeriesType>[] = [];

        for (let i = 0; i < config.plots.length; i++) {
            const plotStyle = config.plots[i];
            const plotData = result.plots[i]?.data ?? [];

            const series = chart.addSeries(LineSeries, {
                color: plotStyle.color,
                lineWidth: (plotStyle.lineWidth ?? 1) as 1 | 2 | 3 | 4,
                lineStyle: plotStyle.lineStyle as LineStyle | undefined,
                lastValueVisible: false,
                priceLineVisible: false,
            });
            series.setData(plotData);
            seriesMeta.set(series, {
                label: plotStyle.label,
                color: plotStyle.color,
                lastValue: plotData.length > 0 ? plotData[plotData.length - 1].value : null,
            });
            seriesList.push(series);
        }

        overlaySeriesMap.set(id, seriesList);
    }
}

function updateLegendDefaults(): void {
    const items: { label: string; color: string; value: string }[] = [];

    if (props.dailySnapshots.length > 0) {
        const last = props.dailySnapshots[props.dailySnapshots.length - 1];
        items.push({ label: 'Strategy', color: STRATEGY_COLOR, value: Number(last.nav).toFixed(2) });
    }

    if (props.benchmarkData.length > 0) {
        const last = props.benchmarkData[props.benchmarkData.length - 1];
        items.push({ label: 'Benchmark', color: BENCHMARK_COLOR, value: Number(last.nav).toFixed(2) });
    }

    for (const [, meta] of seriesMeta) {
        items.push({ label: meta.label, color: meta.color, value: meta.lastValue !== null ? meta.lastValue.toFixed(2) : '-' });
    }

    legendItems.value = items;
}

function applyTimeRange(): void {
    if (!chart || props.dailySnapshots.length === 0) {
        return;
    }

    const range = timeRanges.find((r) => r.key === selectedRange.value);

    if (!range || range.days === Infinity) {
        chart.timeScale().fitContent();
        return;
    }

    const startIndex = Math.max(0, props.dailySnapshots.length - range.days);
    const from = props.dailySnapshots[startIndex].date.substring(0, 10);
    const to = props.dailySnapshots[props.dailySnapshots.length - 1].date.substring(0, 10);

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

function applyScaleMode(): void {
    if (!chart) return;
    const mode = scaleModes.find((m) => m.key === scaleMode.value);
    chart.priceScale('right').applyOptions({ mode: mode?.mode ?? PriceScaleMode.Normal });
}

function resetView(): void {
    if (selectedRange.value === 'max') {
        chart?.timeScale().fitContent();
    } else {
        selectedRange.value = 'max';
    }
}

function destroyChart(): void {
    if (chart) {
        unregisterSync?.();
        unregisterSync = null;
        chart.remove();
        chart = null;
        strategySeries = null;
        benchmarkSeries = null;
        overlaySeriesMap.clear();
        seriesMeta.clear();
        legendItems.value = [];
    }
}

watch(chartContainer, (el) => {
    if (!el) { destroyChart(); return; }
    if (!chart) initChart(el);
});

watch(() => props.dailySnapshots, () => {
    if (!chart) return;
    cachedNavBars = null;
    setStrategyData();
    // Recompute overlays against the fresh NAV series
    for (const [id, seriesList] of overlaySeriesMap) {
        for (const s of seriesList) {
            chart.removeSeries(s);
            seriesMeta.delete(s);
        }
        overlaySeriesMap.delete(id);
    }
    syncIndicators();
    applyTimeRange();
    updateLegendDefaults();
});

// Benchmark switches keep the user's zoom — only the series data changes.
watch(() => props.benchmarkData, () => {
    if (!chart) return;
    setBenchmarkData();
    updateLegendDefaults();
}, { immediate: true, deep: true });

watch(activeIndicators, () => {
    if (!chart) return;
    syncIndicators();
    updateLegendDefaults();
});

watch(selectedRange, applyTimeRange);

watch(scaleMode, applyScaleMode);

onUnmounted(destroyChart);
</script>
