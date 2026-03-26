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

                    <!-- Indicator Picker -->
                    <Popover class="relative">
                        <PopoverButton
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
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
                                Overlays
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
                            <div
                                v-if="paneIndicators.length > 0"
                                class="mb-2 mt-3 text-xs font-semibold uppercase tracking-wide text-gray-400"
                            >
                                Pane
                            </div>
                            <button
                                v-for="ind in paneIndicators"
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

                <!-- Active Indicator Pills -->
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
                        {{ getIndicatorConfig(id)?.label }} {{ getIndicatorConfig(id) ? formatParamsLabel(getIndicatorConfig(id)!) : '' }}
                        <button
                            type="button"
                            class="ml-0.5 cursor-pointer text-gray-400 hover:text-gray-600"
                            @click="toggleIndicator(id)"
                        >
                            &times;
                        </button>
                    </span>
                </div>

                <!-- Main Chart -->
                <div class="relative mt-4 h-[400px]">
                    <div ref="chartContainer" class="h-full" />
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

                <!-- Pane Charts -->
                <div
                    v-for="id in activePaneIndicatorIds"
                    :key="id"
                    :ref="(el) => setPaneRef(id, el as HTMLDivElement | null)"
                    class="mt-1 h-[150px]"
                />
            </div>
        </Deferred>

        <!-- Price Levels -->
        <div class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Price Levels</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">1-Year High</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ instrument.high_one_year }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">Away from 1Y High</div>
                    <div class="mt-1 text-lg font-semibold" :class="Number(instrument.away_from_high_one_year) >= 0 ? 'text-green-700' : 'text-red-700'">{{ instrument.away_from_high_one_year }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">All Time High</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ instrument.high_all_time }}</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">Away from ATH</div>
                    <div class="mt-1 text-lg font-semibold" :class="Number(instrument.away_from_high_all_time) >= 0 ? 'text-green-700' : 'text-red-700'">{{ instrument.away_from_high_all_time }}%</div>
                </div>
            </div>
        </div>

        <!-- Moving Averages -->
        <div class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Moving Averages</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="pb-2 text-left font-medium text-gray-500"></th>
                            <th class="pb-2 text-right font-medium text-gray-500">200</th>
                            <th class="pb-2 text-right font-medium text-gray-500">100</th>
                            <th class="pb-2 text-right font-medium text-gray-500">50</th>
                            <th class="pb-2 text-right font-medium text-gray-500">20</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2.5 font-medium text-gray-700">SMA</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ma_200) ? 'text-green-700' : 'text-red-700'">{{ instrument.ma_200 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ma_100) ? 'text-green-700' : 'text-red-700'">{{ instrument.ma_100 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ma_50) ? 'text-green-700' : 'text-red-700'">{{ instrument.ma_50 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ma_20) ? 'text-green-700' : 'text-red-700'">{{ instrument.ma_20 }}</td>
                        </tr>
                        <tr>
                            <td class="py-2.5 font-medium text-gray-700">EMA</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ema_200) ? 'text-green-700' : 'text-red-700'">{{ instrument.ema_200 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ema_100) ? 'text-green-700' : 'text-red-700'">{{ instrument.ema_100 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ema_50) ? 'text-green-700' : 'text-red-700'">{{ instrument.ema_50 }}</td>
                            <td class="py-2.5 text-right" :class="Number(instrument.close_adjusted) > Number(instrument.ema_20) ? 'text-green-700' : 'text-red-700'">{{ instrument.ema_20 }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-2 text-xs text-gray-400">Green = price above MA, Red = price below MA</p>
            </div>
        </div>

        <!-- Momentum & Volume -->
        <div class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Momentum & Volume</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">12M - 1M Return</div>
                    <div class="mt-1 text-lg font-semibold" :class="Number(instrument.return_twelve_minus_one_months) >= 0 ? 'text-green-700' : 'text-red-700'">{{ instrument.return_twelve_minus_one_months }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">12M - 2M Return</div>
                    <div class="mt-1 text-lg font-semibold" :class="Number(instrument.return_twelve_minus_two_months) >= 0 ? 'text-green-700' : 'text-red-700'">{{ instrument.return_twelve_minus_two_months }}%</div>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-xs ring-1 ring-gray-100">
                    <div class="text-xs text-gray-500">Median Daily Vol 1Y</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">&#8377;{{ instrument.median_volume_one_year }} cr</div>
                </div>
            </div>
        </div>

        <!-- Period-wise Stats Table -->
        <div class="mt-8">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Period-wise Stats</h2>
            <div class="overflow-x-auto rounded-lg ring-1 ring-gray-100">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-3 text-left font-medium text-gray-500"></th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">1M</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">3M</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">6M</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">9M</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-500">1Y</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <tr v-for="row in periodStatsRows" :key="row.label" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">
                                {{ row.label }}
                            </td>
                            <td
                                v-for="(val, i) in row.values"
                                :key="i"
                                class="whitespace-nowrap px-4 py-3 text-right"
                                :class="row.colorize && String(val).includes('-') ? 'text-red-700' : row.colorize ? 'text-green-700' : 'text-gray-700'"
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
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import {
    AreaSeries,
    ColorType,
    CrosshairMode,
    HistogramSeries,
    LineSeries,
    createChart,
} from 'lightweight-charts';
import type { IChartApi, ISeriesApi, LineStyle, SeriesType, Time } from 'lightweight-charts';
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline';
import type { Bar } from 'oakscriptjs';
import PageHeader from '@/Components/PageHeader.vue';
import type { BacktestNseInstrumentViewResource } from '@/types/app/Resources/BacktestNseInstrumentViewResource';
import type { PriceHistoryRecord } from '@/utils/chartDataConverter';
import { pricesToBars } from '@/utils/chartDataConverter';
import { computeIndicator } from '@/utils/indicatorCalculator';
import { formatParamsLabel, getIndicatorConfig, getOverlayIndicators, getPaneIndicators } from '@/utils/indicatorRegistry';
import type { IndicatorConfig } from '@/utils/indicatorRegistry';

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

const periodStatsRows = computed(() => {
    const i = props.instrument;
    return [
        { label: 'Returns', colorize: true, values: [i.absolute_return_one_months + '%', i.absolute_return_three_months + '%', i.absolute_return_six_months + '%', i.absolute_return_nine_months + '%', i.absolute_return_one_year + '%'] },
        { label: 'Volatility', colorize: false, values: [i.volatility_one_months, i.volatility_three_months, i.volatility_six_months, i.volatility_nine_months, i.volatility_one_year] },
        { label: 'Sharpe Returns', colorize: true, values: [i.sharpe_return_one_months, i.sharpe_return_three_months, i.sharpe_return_six_months, i.sharpe_return_nine_months, i.sharpe_return_one_year] },
        { label: 'RSI', colorize: false, values: [i.rsi_one_months, i.rsi_three_months, i.rsi_six_months, i.rsi_nine_months, i.rsi_one_year] },
        { label: 'Positive Days %', colorize: false, values: [i.positive_days_percent_one_months + '%', i.positive_days_percent_three_months + '%', i.positive_days_percent_six_months + '%', i.positive_days_percent_nine_months + '%', i.positive_days_percent_one_year + '%'] },
        { label: 'Circuits', colorize: false, values: [i.circuits_one_months, i.circuits_three_months, i.circuits_six_months, i.circuits_nine_months, i.circuits_one_year] },
    ];
});

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

    for (const [, meta] of seriesMeta) {
        items.push({ label: meta.label, color: meta.color, value: '-' });
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
