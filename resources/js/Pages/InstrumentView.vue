<template>
    <div>
        <Head :title="instrument.symbol">
            <meta
                head-key="og-title"
                property="og:title"
                :content="`${instrument.symbol} (${instrument.name})`"
            />
            <meta
                head-key="og-description"
                property="og:description"
                :content="`Explore details like momentum, volatility, all-time-high, rolling returns and many more for ${instrument.symbol}`"
            />
        </Head>

        <div class="grid grid-cols-1 md:flex md:items-start md:justify-between">
            <PageHeader :description="instrument.name">
                <div class="flex items-end gap-x-4">
                    {{ instrument.symbol }}
                    <span
                        class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-yellow-600/20 ring-inset"
                    >
                        &#8377; {{ instrument.close_adjusted }}
                    </span>
                </div>
            </PageHeader>
            <a
                class="text-sm font-semibold leading-6 text-purple-600 hover:text-purple-500 hover:underline md:text-lg"
                :href="`https://www.nseindia.com/get-quotes/equity?symbol=${instrument.symbol}`"
                target="_blank"
            >
                <div class="flex hover:underline">
                    <ArrowTopRightOnSquareIcon class="h-6 w-6 shrink-0" />
                    <div class="ml-1 flex-1 md:flex md:justify-between">
                        NSE: {{ instrument.symbol }}
                    </div>
                </div>
            </a>
        </div>

        <!-- Index Badges -->
        <div
            v-if="instrument.indices.length > 0"
            class="mb-8 mt-4 flex flex-wrap gap-x-4 gap-y-4 md:mt-0"
        >
            <span
                v-for="index in instrument.indices"
                :key="index"
                class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-purple-700/10 ring-inset"
            >
                {{ index }}
            </span>
        </div>

        <hr />

        <!-- Key Stats -->
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">Price to Earnings</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.price_to_earnings ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Marketcap (in crs)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.marketcap }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Series</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.series }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Beta</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.beta }}</dd>
            </div>
        </div>

        <hr class="mt-8" />

        <!-- Pros & Cons -->
        <div class="mt-8 grid grid-cols-1 gap-8 text-gray-700 lg:grid-cols-2">
            <div class="rounded border-2 border-green-500 px-4 py-2">
                <h2 class="font-semibold">PROS:</h2>
                <ul class="ml-3 mt-4 list-disc">
                    <li v-for="(pro, i) in pros" :key="i">{{ pro }}</li>
                </ul>
                <p v-if="pros.length === 0" class="mt-4 text-sm text-gray-500">No pros identified.</p>
            </div>
            <div class="rounded border-2 border-red-500 px-4 py-2">
                <h2 class="font-semibold">CONS:</h2>
                <ul class="ml-3 mt-4 list-disc">
                    <li v-for="(con, i) in cons" :key="i">{{ con }}</li>
                </ul>
                <p v-if="cons.length === 0" class="mt-4 text-sm text-gray-500">No cons identified.</p>
            </div>
        </div>

        <!-- Chart (Deferred) -->
        <Deferred data="priceHistory">
            <template #fallback>
                <div class="mt-8 rounded-lg bg-slate-50 p-4 md:p-6">
                    <div class="animate-pulse">
                        <div class="flex gap-2">
                            <div
                                v-for="n in 7"
                                :key="n"
                                class="h-8 w-12 rounded bg-gray-200"
                            />
                        </div>
                        <div class="mt-4 h-[400px] rounded bg-gray-200" />
                    </div>
                </div>
            </template>

            <div v-if="priceHistory && priceHistory.length > 0" class="mt-8 rounded-lg bg-slate-50 p-4 md:p-6">
                <!-- Chart Controls -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Time Range Buttons -->
                    <div class="inline-flex rounded-md border border-gray-300 bg-white">
                        <button
                            v-for="range in timeRanges"
                            :key="range.key"
                            type="button"
                            class="cursor-pointer px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                            :class="
                                selectedTimeRange === range.key
                                    ? 'bg-purple-600 text-white'
                                    : 'text-gray-700 hover:bg-gray-50'
                            "
                            @click="selectedTimeRange = range.key"
                        >
                            {{ range.label }}
                        </button>
                    </div>

                    <!-- Chart View Tabs -->
                    <div class="inline-flex rounded-md border border-gray-300 bg-white">
                        <button
                            v-for="view in chartViews"
                            :key="view.key"
                            type="button"
                            class="cursor-pointer px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                            :class="
                                selectedChartView === view.key
                                    ? 'text-purple-600 font-bold'
                                    : 'text-gray-700 hover:bg-gray-50'
                            "
                            @click="selectedChartView = view.key"
                        >
                            {{ view.label }}
                        </button>
                    </div>
                </div>

                <!-- Overlay Toggles (Price view only) -->
                <div v-if="selectedChartView === 'price'" class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500">Overlays:</span>
                    <button
                        v-for="overlay in overlayOptions"
                        :key="overlay.key"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                        :class="
                            overlays[overlay.key]
                                ? 'border-transparent text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'
                        "
                        :style="overlays[overlay.key] ? { backgroundColor: overlay.color } : {}"
                        @click="overlays[overlay.key] = !overlays[overlay.key]"
                    >
                        {{ overlay.label }}
                    </button>
                </div>

                <!-- Chart -->
                <div ref="chartContainer" class="mt-4 h-[400px]" />
            </div>
        </Deferred>

        <hr class="mt-8" />

        <!-- Price Levels & Moving Averages -->
        <dl class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">1-Year High</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.high_one_year }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Away from 1Y High</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.away_from_high_one_year }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">All Time High</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.high_all_time }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Away from ATH</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.away_from_high_all_time }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">200 DMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ma_200 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">100 DMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ma_100 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">50 DMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ma_50 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">20 DMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ma_20 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">200 EMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ema_200 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">100 EMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ema_100 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">50 EMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ema_50 }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">20 EMA</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.ema_20 }}</dd>
            </div>
        </dl>

        <!-- Momentum Returns & Volume -->
        <dl class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">12M - 1M Return</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.return_twelve_minus_one_months }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">12M - 2M Return</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.return_twelve_minus_two_months }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Median Daily Vol 1Y (&#8377; cr)</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ instrument.median_volume_one_year }}</dd>
            </div>
        </dl>

        <hr class="mt-8" />

        <!-- Time-Period Stats Table -->
        <div class="mt-8">
            <h2 class="text-lg font-semibold leading-6 text-gray-900">Period-wise Stats</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300 text-sm">
                    <thead>
                        <tr>
                            <th class="py-3 pr-4 text-left font-semibold text-gray-900" />
                            <th class="px-4 py-3 text-right font-semibold text-gray-900">1M</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900">3M</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900">6M</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900">9M</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-900">1Y</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="row in periodStatsRows" :key="row.label">
                            <td class="whitespace-nowrap py-3 pr-4 font-medium text-gray-700">
                                {{ row.label }}
                            </td>
                            <td
                                v-for="(val, i) in row.values"
                                :key="i"
                                class="whitespace-nowrap px-4 py-3 text-right text-gray-600"
                            >
                                {{ val }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Corporate Actions (Deferred) -->
        <Deferred :data="['dividends', 'corporateActions']">
            <template #fallback>
                <div class="mt-8">
                    <div class="h-6 w-48 animate-pulse rounded bg-gray-200" />
                    <div class="mt-4 animate-pulse space-y-3">
                        <div v-for="n in 5" :key="n" class="flex gap-4">
                            <div class="h-4 w-24 rounded bg-gray-200" />
                            <div class="h-4 flex-1 rounded bg-gray-200" />
                        </div>
                    </div>
                </div>
            </template>

            <div
                v-if="
                    (dividends && dividends.length > 0) ||
                    (corporateActions && corporateActions.length > 0)
                "
            >
                <h2 class="mt-8 text-lg font-semibold leading-6 text-gray-900">Corporate Actions</h2>

                <!-- Tabs -->
                <div class="mt-4 border-b border-gray-200">
                    <nav class="-mb-px flex gap-x-6">
                        <button
                            type="button"
                            class="cursor-pointer whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium"
                            :class="
                                activeCorpActionTab === 'dividends'
                                    ? 'border-purple-500 text-purple-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            "
                            @click="activeCorpActionTab = 'dividends'"
                        >
                            Dividends
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium"
                            :class="
                                activeCorpActionTab === 'rightsBonus'
                                    ? 'border-purple-500 text-purple-600'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                            "
                            @click="activeCorpActionTab = 'rightsBonus'"
                        >
                            Rights / Bonus / Split
                        </button>
                    </nav>
                </div>

                <!-- Dividends Tab -->
                <div v-if="activeCorpActionTab === 'dividends'" class="mt-4 overflow-x-auto">
                    <table
                        v-if="dividends && dividends.length > 0"
                        class="min-w-full divide-y divide-gray-300"
                    >
                        <thead>
                            <tr>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Date
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Description
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Dividend (&#8377;)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(action, i) in dividends" :key="i">
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ formatDate(action.date) }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500">
                                    {{ action.description }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ action.dividend }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-sm text-gray-500">No dividend actions found.</p>
                </div>

                <!-- Rights / Bonus / Split Tab -->
                <div v-if="activeCorpActionTab === 'rightsBonus'" class="mt-4 overflow-x-auto">
                    <table
                        v-if="corporateActions && corporateActions.length > 0"
                        class="min-w-full divide-y divide-gray-300"
                    >
                        <thead>
                            <tr>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Date
                                </th>
                                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Description
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(action, i) in corporateActions" :key="i">
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    {{ formatDate(action.date) }}
                                </td>
                                <td class="px-3 py-4 text-sm text-gray-500">
                                    {{ action.description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-4 text-sm text-gray-500">
                        No rights, bonus, or split actions found.
                    </p>
                </div>
            </div>
        </Deferred>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import {
    AreaSeries,
    ColorType,
    CrosshairMode,
    LineSeries,
    LineStyle,
    createChart,
} from 'lightweight-charts';
import type { IChartApi, ISeriesApi, SeriesType, Time } from 'lightweight-charts';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import PageHeader from '@/Components/PageHeader.vue';
import type { BacktestNseInstrumentViewResource } from '@/types/app/Resources/BacktestNseInstrumentViewResource';

interface PriceHistoryRecord {
    date: string;
    close_adjusted: number;
    ma_50: number | null;
    ma_200: number | null;
    ema_50: number | null;
    ema_200: number | null;
    price_to_earnings: number | null;
    marketcap: number | null;
    absolute_return_one_year: number | null;
    rsi_one_year: number | null;
}

interface CorporateAction {
    date: string;
    description: string;
}

interface DividendAction {
    date: string;
    description: string;
    dividend: string | null;
}

const props = defineProps<{
    instrument: BacktestNseInstrumentViewResource;
    priceHistory?: PriceHistoryRecord[];
    dividends?: DividendAction[];
    corporateActions?: CorporateAction[];
    pros: string[];
    cons: string[];
}>();

const activeCorpActionTab = ref<'dividends' | 'rightsBonus'>('dividends');

const timeRanges = [
    { key: '1m', label: '1M', days: 22 },
    { key: '6m', label: '6M', days: 126 },
    { key: '1y', label: '1Yr', days: 252 },
    { key: '3y', label: '3Yr', days: 756 },
    { key: '5y', label: '5Yr', days: 1260 },
    { key: '10y', label: '10Yr', days: 2520 },
    { key: 'max', label: 'Max', days: Infinity },
] as const;

type TimeRangeKey = (typeof timeRanges)[number]['key'];

const chartViews = [
    { key: 'price', label: 'Price' },
    { key: 'returns', label: '1Yr Returns' },
    { key: 'rsi', label: 'RSI' },
] as const;

type ChartViewKey = (typeof chartViews)[number]['key'];

const selectedTimeRange = ref<TimeRangeKey>('1y');
const selectedChartView = ref<ChartViewKey>('price');

const overlayOptions = [
    { key: 'ma200' as const, label: '200 DMA', color: '#10b981', field: 'ma_200' as const, dash: 0 },
    { key: 'ma50' as const, label: '50 DMA', color: '#f97316', field: 'ma_50' as const, dash: 0 },
    { key: 'ema200' as const, label: '200 EMA', color: '#06b6d4', field: 'ema_200' as const, dash: 5 },
    { key: 'ema50' as const, label: '50 EMA', color: '#f59e0b', field: 'ema_50' as const, dash: 5 },
];

type OverlayKey = (typeof overlayOptions)[number]['key'];

const overlays = ref<Record<OverlayKey, boolean>>({
    ma200: true,
    ma50: false,
    ema200: false,
    ema50: false,
});

const periodStatsRows = computed(() => {
    const i = props.instrument;
    return [
        { label: 'Returns', values: [i.absolute_return_one_months, i.absolute_return_three_months, i.absolute_return_six_months, i.absolute_return_nine_months, i.absolute_return_one_year] },
        { label: 'Volatility', values: [i.volatility_one_months, i.volatility_three_months, i.volatility_six_months, i.volatility_nine_months, i.volatility_one_year] },
        { label: 'Sharpe Returns', values: [i.sharpe_return_one_months, i.sharpe_return_three_months, i.sharpe_return_six_months, i.sharpe_return_nine_months, i.sharpe_return_one_year] },
        { label: 'RSI', values: [i.rsi_one_months, i.rsi_three_months, i.rsi_six_months, i.rsi_nine_months, i.rsi_one_year] },
        { label: 'Positive Days %', values: [i.positive_days_percent_one_months, i.positive_days_percent_three_months, i.positive_days_percent_six_months, i.positive_days_percent_nine_months, i.positive_days_percent_one_year] },
        { label: 'Circuits', values: [i.circuits_one_months, i.circuits_three_months, i.circuits_six_months, i.circuits_nine_months, i.circuits_one_year] },
    ];
});

function toDateString(iso: string): string {
    return iso.slice(0, 10);
}

// --- Lightweight Charts (plain variables, NOT refs) ---
const chartContainer = ref<HTMLDivElement>();
let chart: IChartApi | null = null;
let mainSeries: ISeriesApi<SeriesType> | null = null;
const overlaySeriesMap = new Map<string, ISeriesApi<SeriesType>>();

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

    updateSeries();
    applyTimeRange();
}

function updateSeries(): void {
    if (!chart || !props.priceHistory) {
        return;
    }

    // Remove existing series
    if (mainSeries) {
        chart.removeSeries(mainSeries);
        mainSeries = null;
    }
    for (const [, series] of overlaySeriesMap) {
        chart.removeSeries(series);
    }
    overlaySeriesMap.clear();

    const data = props.priceHistory;

    if (selectedChartView.value === 'price') {
        mainSeries = chart.addSeries(AreaSeries, {
            lineColor: '#7c3aed',
            topColor: 'rgba(124, 58, 237, 0.15)',
            bottomColor: 'rgba(124, 58, 237, 0)',
            lineWidth: 2,
            lastValueVisible: false,
            priceLineVisible: false,
        });
        mainSeries.setData(
            data.map((p) => ({
                time: toDateString(p.date) as Time,
                value: Number(p.close_adjusted),
            })),
        );

        addActiveOverlays();
    } else {
        const fieldMap: Record<string, keyof PriceHistoryRecord> = {
            returns: 'absolute_return_one_year',
            rsi: 'rsi_one_year',
        };
        const field = fieldMap[selectedChartView.value];

        mainSeries = chart.addSeries(LineSeries, {
            color: '#7c3aed',
            lineWidth: 2,
            lastValueVisible: false,
            priceLineVisible: false,
        });
        mainSeries.setData(
            data.map((p) => {
                const val = p[field];
                return val !== null
                    ? { time: toDateString(p.date) as Time, value: Number(val) }
                    : { time: toDateString(p.date) as Time };
            }),
        );
    }
}

function addActiveOverlays(): void {
    if (!chart || !props.priceHistory) {
        return;
    }

    for (const overlay of overlayOptions) {
        if (overlays.value[overlay.key] && !overlaySeriesMap.has(overlay.key)) {
            const series = chart.addSeries(LineSeries, {
                color: overlay.color,
                lineWidth: 1,
                lineStyle: overlay.dash > 0 ? LineStyle.Dashed : LineStyle.Solid,
                lastValueVisible: false,
                priceLineVisible: false,
            });
            series.setData(
                props.priceHistory.map((p) => {
                    const val = p[overlay.field];
                    return val !== null
                        ? { time: toDateString(p.date) as Time, value: Number(val) }
                        : { time: toDateString(p.date) as Time };
                }),
            );
            overlaySeriesMap.set(overlay.key, series);
        }
    }
}

function updateOverlays(): void {
    if (!chart || !props.priceHistory) {
        return;
    }

    // Remove overlays that were turned off
    for (const [key, series] of overlaySeriesMap) {
        if (!overlays.value[key as OverlayKey]) {
            chart.removeSeries(series);
            overlaySeriesMap.delete(key);
        }
    }

    // Add overlays that were turned on
    addActiveOverlays();
}

function applyTimeRange(): void {
    if (!chart || !props.priceHistory || props.priceHistory.length === 0) {
        return;
    }

    const data = props.priceHistory;
    const range = timeRanges.find((r) => r.key === selectedTimeRange.value);

    if (!range || range.days === Infinity) {
        chart.timeScale().fitContent();
        return;
    }

    const startIndex = Math.max(0, data.length - range.days);
    const from = toDateString(data[startIndex].date);
    const to = toDateString(data[data.length - 1].date);

    // LC needs a frame to index data before setVisibleRange works
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
    if (chart) {
        chart.remove();
        chart = null;
        mainSeries = null;
        overlaySeriesMap.clear();
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
    }
});

// Rebuild chart when priceHistory changes (Inertia navigation between instruments)
watch(() => props.priceHistory, () => {
    if (chart) {
        updateSeries();
        applyTimeRange();
    }
});

watch(selectedChartView, () => {
    if (!chart) {
        return;
    }
    updateSeries();
    applyTimeRange();
});

watch(selectedTimeRange, () => {
    if (!chart) {
        return;
    }
    applyTimeRange();
});

watch(overlays, () => {
    if (!chart || selectedChartView.value !== 'price') {
        return;
    }
    updateOverlays();
}, { deep: true });

onUnmounted(destroyChart);
</script>
