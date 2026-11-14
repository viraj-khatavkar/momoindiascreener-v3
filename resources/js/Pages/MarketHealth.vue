<template>
    <div class="-mx-4 -my-10 bg-white px-4 py-8 text-[#252135] sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
        <Head :title="`Market Health - ${indexName}`">
            <meta head-key="og-title" property="og:title" :content="`Market Health - ${indexName}`" />
            <meta head-key="og-description" property="og:description" :content="`Market breadth and health metrics for ${indexName}`" />
        </Head>
        <div class="mx-auto max-w-[1240px]">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Market Health</h1>
                    <p class="mt-1 text-sm text-[#706b7d]">See how many stocks support the trend.</p>
                </div>
                <div class="flex items-center gap-3">
                    <label for="market-health-index" class="text-[10px] font-semibold tracking-[0.15em] text-[#706b7d] uppercase">Index</label>
                    <div class="relative min-w-0 flex-1 sm:w-56">
                        <select
                            id="market-health-index"
                            v-model="chosenIndex"
                            :disabled="isChangingIndex"
                            class="min-h-11 w-full cursor-pointer appearance-none rounded-md border border-[#d3cedd] bg-white py-2.5 pr-10 pl-3.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-wait disabled:opacity-60"
                            @change="changeIndex"
                        >
                            <option v-for="slug in availableIndices" :key="slug" :value="slug">{{ formatIndexLabel(slug) }}</option>
                        </select>
                        <ChevronDownIcon class="pointer-events-none absolute top-3.5 right-3 h-4 w-4 text-[#706b7d]" aria-hidden="true" />
                    </div>
                </div>
            </header>
            <div :aria-busy="isChangingIndex" :class="{ 'opacity-50': isChangingIndex }">
                <section
                    class="my-6 grid gap-5 border-y border-[#e5e2ea] py-6 md:grid-cols-[minmax(0,1fr)_280px] md:items-center md:gap-10 md:py-8 lg:grid-cols-[minmax(0,1fr)_340px]"
                    aria-labelledby="market-brief-title"
                >
                    <div>
                        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[#706b7d]">
                            <span class="inline-flex items-center gap-2 font-medium" :class="sessionState.color"
                                ><span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />{{ sessionState.label }}</span
                            >
                            <span aria-hidden="true">·</span>
                            <time v-if="latestHeartbeat" :datetime="latestHeartbeat.date.slice(0, 10)">As of {{ latestDateLabel }}</time>
                            <span v-else>No data available</span>
                        </div>
                        <h2
                            id="market-brief-title"
                            class="max-w-2xl font-[Georgia,serif] text-[32px] leading-[1.15] tracking-[-0.025em] lg:text-[43px]"
                        >
                            {{ sessionState.headline }}
                        </h2>
                        <p class="mt-3 max-w-xl text-sm leading-7 text-[#706b7d]">{{ briefDescription }}</p>
                    </div>
                    <div>
                        <div
                            class="hidden items-center justify-between gap-3 text-[10px] font-semibold tracking-[0.14em] text-[#706b7d] uppercase md:flex"
                        >
                            <h3>The latest session</h3>
                            <span>{{ indexName }}</span>
                        </div>
                        <dl class="flex justify-between gap-4 md:mt-4">
                            <div class="flex items-baseline gap-2 md:flex-col md:gap-0">
                                <dt class="order-2 text-xs text-[#706b7d]">Advances</dt>
                                <dd data-testid="market-advances" class="text-2xl font-medium tracking-tight text-[#24745e] tabular-nums md:text-3xl">
                                    {{ formatValue(latestValue('advances'), 'count') }}
                                </dd>
                            </div>
                            <div class="flex items-baseline gap-2 text-right md:flex-col md:items-end md:gap-0">
                                <dt class="order-2 text-xs text-[#706b7d]">Declines</dt>
                                <dd data-testid="market-declines" class="text-2xl font-medium tracking-tight text-[#b44854] tabular-nums md:text-3xl">
                                    {{ formatValue(latestValue('declines'), 'count') }}
                                </dd>
                            </div>
                        </dl>
                        <div class="my-3 flex h-2 gap-1 overflow-hidden rounded-xs bg-[#e5e2ea]" aria-hidden="true">
                            <span class="bg-[#438774]" :style="{ width: `${advanceShare}%` }" /><span
                                class="bg-[#bd6771]"
                                :style="{ width: `${declineShare}%` }"
                            />
                        </div>
                        <button
                            type="button"
                            class="flex min-h-10 w-full cursor-pointer items-center justify-between gap-3 border-t border-[#e5e2ea] pt-2 text-left text-xs hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                            aria-label="Show advance / decline ratio history"
                            :aria-pressed="selectedMetric.key === 'advance_decline_ratio'"
                            @click="selectMetric('advance_decline_ratio', true)"
                        >
                            <span>Advance / decline ratio</span
                            ><span class="inline-flex items-center gap-1.5 font-semibold tabular-nums"
                                ><span data-testid="market-ratio">{{ formatValue(latestValue('advance_decline_ratio'), 'ratio') }}</span
                                ><ArrowUpRightIcon class="h-3.5 w-3.5" aria-hidden="true"
                            /></span>
                        </button>
                    </div>
                </section>

                <section
                    ref="chartSection"
                    class="grid scroll-mt-6 overflow-hidden rounded-xl border border-[#e5e2ea] bg-white lg:grid-cols-[minmax(0,1fr)_296px]"
                    aria-label="Market breadth history and current moving averages"
                >
                    <div class="min-w-0 p-4 sm:p-6">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <h2 class="text-base font-semibold tracking-tight">Participation over time</h2>
                            <div class="flex flex-wrap gap-0.5" role="group" aria-label="Chart period">
                                <button
                                    v-for="period in periods"
                                    :key="period.key"
                                    type="button"
                                    :aria-label="`Show ${period.description}`"
                                    :aria-pressed="selection.period === period.key"
                                    class="min-h-10 cursor-pointer rounded-md px-2.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:px-3"
                                    :class="selection.period === period.key ? 'bg-[#f1e9fb] text-[#7515d9]' : 'text-[#706b7d] hover:bg-[#f7f6f9]'"
                                    @click="selectPeriod(period.key)"
                                >
                                    {{ period.label }}
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <label for="market-health-metric" class="sr-only">Chart metric</label>
                                <div class="relative w-fit max-w-full">
                                    <select
                                        id="market-health-metric"
                                        ref="metricSelector"
                                        v-model="selection.metric"
                                        class="min-h-9 max-w-full cursor-pointer appearance-none rounded-none border-0 border-b border-dashed border-[#c1b1d2] bg-transparent py-1 pr-7 text-xs text-[#706b7d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                    >
                                        <optgroup v-for="group in metricGroups" :key="group.name" :label="group.name">
                                            <option v-for="metric in group.metrics" :key="metric.key" :value="metric.key">{{ metric.label }}</option>
                                        </optgroup>
                                    </select>
                                    <ChevronDownIcon class="pointer-events-none absolute top-2.5 right-1 h-3.5 w-3.5" aria-hidden="true" />
                                </div>
                                <div class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                    <p data-testid="market-metric-value" class="text-[33px] leading-snug font-medium tracking-tight tabular-nums">
                                        {{ formatValue(latestValue(selectedMetric.key), selectedMetric.unit) }}
                                    </p>
                                    <p data-testid="market-metric-change" class="text-xs text-[#706b7d]">{{ metricChange }}</p>
                                </div>
                            </div>
                            <p class="hidden shrink-0 text-right text-xs leading-5 text-[#706b7d] sm:block">
                                Latest value<br />{{ latestDateLabel }}
                            </p>
                        </div>
                        <MetricChart
                            :key="selectedMetric.key"
                            ref="metricChart"
                            class="mt-4"
                            :title="selectedMetric.label"
                            :data="selectedChartData"
                            :unit="selectedMetric.unit"
                            :reference-line="selectedMetric.referenceLine"
                            color="#7515d9"
                            embedded
                            fit-content
                            @range-change="visibleChartRange = $event"
                        />
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs leading-5 text-[#706b7d]">
                            <p>{{ chartContext }}</p>
                            <button
                                type="button"
                                class="min-h-8 cursor-pointer underline decoration-dotted underline-offset-4 hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-purple-600"
                                @click="metricChart?.resetView()"
                            >
                                Reset view
                            </button>
                        </div>
                        <p v-if="chartRangeLabel" data-testid="market-chart-range" class="mt-1 text-xs text-[#706b7d]">{{ chartRangeLabel }}</p>
                    </div>
                    <aside
                        class="flex min-w-0 flex-col border-t border-[#e5e2ea] p-5 lg:border-t-0 lg:border-l lg:p-6"
                        aria-labelledby="market-trend-title"
                    >
                        <h2 id="market-trend-title" class="text-base font-semibold tracking-tight">Across the time frames</h2>
                        <p class="mt-1 text-xs leading-5 text-[#706b7d]">Stocks above their moving average.</p>
                        <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 lg:grid-cols-1">
                            <button
                                v-for="metric in movingAverages"
                                :key="metric.key"
                                type="button"
                                :aria-label="`${metric.label}: ${formatValue(latestValue(metric.key), metric.unit)}. Show history.`"
                                :aria-pressed="selectedMetric.key === metric.key"
                                :data-metric="metric.key"
                                class="cursor-pointer rounded-sm py-3 text-left hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                :class="{ 'text-[#7515d9]': selectedMetric.key === metric.key }"
                                @click="selectMetric(metric.key, true)"
                            >
                                <span class="flex items-baseline justify-between gap-2"
                                    ><span class="text-xs">{{ metric.shortLabel }}</span
                                    ><strong class="text-sm font-semibold tabular-nums">{{
                                        formatValue(latestValue(metric.key), metric.unit)
                                    }}</strong></span
                                >
                                <span class="relative mt-2 block h-1.5 rounded-xs bg-[#f0edf4]" aria-hidden="true"
                                    ><span
                                        class="block h-full rounded-xs"
                                        :class="selectedMetric.key === metric.key ? 'bg-[#7515d9]' : 'bg-[#c8b5df]'"
                                        :style="{ width: `${Math.min(100, Math.max(0, latestValue(metric.key) ?? 0))}%` }" /><span
                                        class="absolute -top-0.5 left-1/2 h-2.5 w-px bg-[#bfb8ca]"
                                /></span>
                            </button>
                        </div>
                        <div class="mt-1 hidden justify-between text-[10px] text-[#706b7d] lg:flex" aria-hidden="true">
                            <span>0%</span><span>50%</span><span>100%</span>
                        </div>
                        <div class="mt-5 border-t border-[#e5e2ea] pt-4 text-xs leading-6 text-[#706b7d] lg:mt-auto">
                            <p>The centre mark is 50% of stocks.</p>
                            <p>Select a row to see its history.</p>
                        </div>
                    </aside>
                </section>
                <div class="mt-7 mb-3 flex items-baseline justify-between gap-4">
                    <h2 class="text-base font-semibold tracking-tight">More of the picture</h2>
                    <p class="hidden text-xs text-[#706b7d] md:block">Latest available session · Select any value to see its history</p>
                </div>
                <section class="grid gap-4 md:grid-cols-2 md:gap-6" aria-label="Annual returns and proximity to all-time highs">
                    <div v-for="group in detailGroups" :key="group.name" class="rounded-lg border border-[#e5e2ea] bg-white px-5 pt-5 pb-2 sm:px-6">
                        <h3 class="text-sm font-semibold">{{ group.name }}</h3>
                        <p class="mt-1 text-xs leading-5 text-[#706b7d]">{{ group.description }}</p>
                        <div class="mt-4">
                            <button
                                v-for="metric in group.metrics"
                                :key="metric.key"
                                type="button"
                                :aria-label="`${metric.label}: ${formatValue(latestValue(metric.key), metric.unit)}. Show history.`"
                                :aria-pressed="selectedMetric.key === metric.key"
                                :data-metric="metric.key"
                                class="flex min-h-12 w-full cursor-pointer items-center justify-between gap-4 border-t border-[#f0edf4] text-left hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                :class="{ 'text-[#7515d9]': selectedMetric.key === metric.key }"
                                @click="selectMetric(metric.key, true)"
                            >
                                <span class="text-xs" :class="selectedMetric.key === metric.key ? 'text-[#7515d9]' : 'text-[#706b7d]'">{{
                                    metric.shortLabel
                                }}</span
                                ><span class="inline-flex items-center gap-4"
                                    ><strong class="text-base font-semibold tabular-nums">{{
                                        formatValue(latestValue(metric.key), metric.unit)
                                    }}</strong
                                    ><ArrowUpRightIcon class="h-3.5 w-3.5 text-[#a19aaa]" aria-hidden="true"
                                /></span>
                            </button>
                        </div>
                    </div>
                </section>
                <div class="mt-5 flex flex-col justify-between gap-4 text-xs leading-6 text-[#706b7d] sm:flex-row sm:gap-8">
                    <details class="max-w-xl">
                        <summary
                            class="w-fit cursor-pointer rounded-sm underline decoration-dotted underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                        >
                            How to read these measures
                        </summary>
                        <p class="mt-3">
                            Each percentage is the share of stocks in the selected index. A moving average is the average price over the stated number
                            of trading days. The 50% line means half of stocks are above their average; it is a reference, not a forecast.
                        </p>
                        <p class="mt-2">
                            The advance / decline ratio compares the number of stocks that rose with the number that fell. The session bar uses these
                            two reported counts. Missing values stay empty. Changes in percentages use percentage points (pp).
                        </p>
                    </details>
                    <p class="sm:text-right">{{ historyLabel }}</p>
                </div>
            </div>
            <p v-if="isChangingIndex" class="sr-only" role="status">Loading market data.</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import MetricChart from '@/Components/Charts/MetricChart.vue';
import type { MarketHeartbeat } from '@/types/app/Models/MarketHeartbeat';
import { ArrowUpRightIcon, ChevronDownIcon } from '@heroicons/vue/20/solid';
import { Head, router, useRemember } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{ heartbeats: MarketHeartbeat[]; index: string; indexName: string; availableIndices: string[] }>();

type MetricKey = Exclude<keyof MarketHeartbeat, 'id' | 'date' | 'index' | 'created_at' | 'updated_at'>;
type MetricUnit = 'percent' | 'ratio' | 'count';
interface Metric {
    key: MetricKey;
    label: string;
    shortLabel: string;
    unit: MetricUnit;
    referenceLine?: number;
}
interface MetricGroup {
    name: string;
    description?: string;
    metrics: Metric[];
}

const metricGroups: MetricGroup[] = [
    {
        name: 'Moving averages',
        metrics: [
            { key: 'percentage_above_ma_200', label: 'Above 200-day average', shortLabel: '200-day', unit: 'percent', referenceLine: 50 },
            { key: 'percentage_above_ma_100', label: 'Above 100-day average', shortLabel: '100-day', unit: 'percent', referenceLine: 50 },
            { key: 'percentage_above_ma_50', label: 'Above 50-day average', shortLabel: '50-day', unit: 'percent', referenceLine: 50 },
            { key: 'percentage_above_ma_20', label: 'Above 20-day average', shortLabel: '20-day', unit: 'percent', referenceLine: 50 },
        ],
    },
    {
        name: 'Annual returns',
        description: 'Stocks with 1-year returns above each level.',
        metrics: [
            {
                key: 'percentage_of_stocks_with_returns_one_year_above_zero',
                label: '1-year return above 0%',
                shortLabel: 'Above 0%',
                unit: 'percent',
                referenceLine: 50,
            },
            {
                key: 'percentage_of_stocks_with_returns_one_year_above_ten',
                label: '1-year return above 10%',
                shortLabel: 'Above 10%',
                unit: 'percent',
                referenceLine: 50,
            },
            {
                key: 'percentage_of_stocks_with_returns_one_year_above_hundred',
                label: '1-year return above 100%',
                shortLabel: 'Above 100%',
                unit: 'percent',
            },
        ],
    },
    {
        name: 'Near all-time highs',
        description: 'Stocks within each distance of their all-time high.',
        metrics: [
            {
                key: 'percentage_of_stocks_within_ten_percent_of_ath',
                label: 'Within 10% of all-time high',
                shortLabel: 'Within 10%',
                unit: 'percent',
            },
            {
                key: 'percentage_of_stocks_within_twenty_percent_of_ath',
                label: 'Within 20% of all-time high',
                shortLabel: 'Within 20%',
                unit: 'percent',
            },
            {
                key: 'percentage_of_stocks_within_thirty_percent_of_ath',
                label: 'Within 30% of all-time high',
                shortLabel: 'Within 30%',
                unit: 'percent',
            },
        ],
    },
    {
        name: 'Daily breadth',
        metrics: [
            { key: 'advance_decline_ratio', label: 'Advance / decline ratio', shortLabel: 'A/D ratio', unit: 'ratio', referenceLine: 1 },
            { key: 'advances', label: 'Number of advances', shortLabel: 'Advances', unit: 'count' },
            { key: 'declines', label: 'Number of declines', shortLabel: 'Declines', unit: 'count' },
        ],
    },
];
const periods = [
    { key: '1M', label: '1M', description: '1 month', months: 1 },
    { key: '3M', label: '3M', description: '3 months', months: 3 },
    { key: '6M', label: '6M', description: '6 months', months: 6 },
    { key: '1Y', label: '1Y', description: '1 year', months: 12 },
    { key: '5Y', label: '5Y', description: '5 years', months: 60 },
    { key: 'ALL', label: 'All', description: 'all available history', months: null },
] as const;
type PeriodKey = (typeof periods)[number]['key'];

const selection = ref(
    useRemember<{ metric: MetricKey; period: PeriodKey }>({ metric: 'percentage_above_ma_200', period: '1Y' }, 'MarketHealth:selection'),
);
const selectedMetric = computed(
    () => metricGroups.flatMap((group) => group.metrics).find((metric) => metric.key === selection.value.metric) ?? metricGroups[0].metrics[0],
);
const movingAverages = [...metricGroups[0].metrics].reverse();
const detailGroups = metricGroups.slice(1, 3);
const chosenIndex = ref(props.index);
const isChangingIndex = ref(false);
const chartSection = ref<HTMLElement>();
const metricSelector = ref<HTMLSelectElement>();
const metricChart = ref<InstanceType<typeof MetricChart>>();
const visibleChartRange = ref<{ from: string; to: string } | null>(null);
const latestHeartbeat = computed(() => props.heartbeats.at(-1) ?? null);
const latestDateLabel = computed(() => (latestHeartbeat.value ? formatDate(latestHeartbeat.value.date) : 'No data'));
const historyLabel = computed(() =>
    props.heartbeats.length ? `Available history: ${formatDate(props.heartbeats[0].date)} – ${latestDateLabel.value}` : 'No history available',
);
const chartRangeLabel = computed(() =>
    visibleChartRange.value ? `In view: ${formatDate(visibleChartRange.value.from)} – ${formatDate(visibleChartRange.value.to)}` : '',
);

const sessionState = computed(() => {
    const advances = latestValue('advances');
    const declines = latestValue('declines');
    if (advances === null || declines === null) {
        return { label: 'No session data', headline: 'Market breadth is unavailable.', color: 'text-[#706b7d]' };
    }
    if (advances + declines === 0) {
        return { label: 'No reported moves', headline: 'No advances or declines.', color: 'text-[#706b7d]' };
    }
    if (advances === declines) {
        return { label: 'Even session', headline: 'Advances and declines are equal.', color: 'text-[#706b7d]' };
    }
    return advances > declines
        ? { label: 'Advances lead', headline: 'More stocks rose than fell.', color: 'text-[#24745e]' }
        : { label: 'Declines lead', headline: 'More stocks fell than rose.', color: 'text-[#b44854]' };
});
const briefDescription = computed(() => {
    if (!latestHeartbeat.value) {
        return `No market health history is available for ${props.indexName}. Select another index to continue.`;
    }
    const longTerm = latestValue('percentage_above_ma_200');
    const shortTerm = latestValue('percentage_above_ma_20');
    const description =
        longTerm === null
            ? 'The 200-day measure is not available for this session.'
            : `${formatValue(longTerm)} of stocks are above their 200-day average.`;
    return shortTerm === null ? description : `${description} The share above the 20-day average is ${formatValue(shortTerm)}.`;
});
const reportedMoves = computed(() => {
    const advances = latestValue('advances');
    const declines = latestValue('declines');
    return advances === null || declines === null ? 0 : advances + declines;
});
const advanceShare = computed(() => (reportedMoves.value > 0 ? (latestValue('advances')! / reportedMoves.value) * 100 : 0));
const declineShare = computed(() => (reportedMoves.value > 0 ? (latestValue('declines')! / reportedMoves.value) * 100 : 0));
const metricChange = computed(() => {
    const current = latestValue(selectedMetric.value.key);
    const previous = props.heartbeats.length > 20 ? numericValue(props.heartbeats.at(-21)?.[selectedMetric.value.key]) : null;
    if (current === null || previous === null) {
        return '20-session change unavailable';
    }
    const difference = current - previous;
    const unit = selectedMetric.value.unit;
    const amount = Math.abs(difference).toFixed(unit === 'ratio' ? 2 : unit === 'count' ? 0 : 1);
    const suffix = unit === 'percent' ? 'pp' : unit === 'ratio' ? '×' : 'stocks';
    return `${difference < 0 ? '−' : difference > 0 ? '+' : ''}${amount} ${suffix} vs 20 sessions ago`;
});
const chartContext = computed(() =>
    selectedMetric.value.referenceLine === 50
        ? 'Dashed line: half of stocks (50%).'
        : selectedMetric.value.referenceLine === 1
          ? 'Dashed line: equal advances and declines (1×).'
          : selectedMetric.value.unit === 'percent'
            ? 'Share of stocks in the selected index.'
            : 'Number of stocks in each session.',
);
const selectedChartData = computed(() => {
    const period = periods.find((item) => item.key === selection.value.period) ?? periods[3];
    let from = '';
    if (period.months !== null && latestHeartbeat.value) {
        const start = new Date(`${latestHeartbeat.value.date.slice(0, 10)}T00:00:00Z`);
        const day = start.getUTCDate();
        start.setUTCDate(1);
        start.setUTCMonth(start.getUTCMonth() - period.months);
        const lastDay = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 1, 0)).getUTCDate();
        start.setUTCDate(Math.min(day, lastDay));
        from = start.toISOString().slice(0, 10);
    }
    return props.heartbeats
        .filter((heartbeat) => heartbeat.date.slice(0, 10) >= from)
        .map((heartbeat) => ({ time: heartbeat.date.slice(0, 10), value: numericValue(heartbeat[selectedMetric.value.key]) }));
});

function numericValue(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}
function latestValue(key: MetricKey): number | null {
    return numericValue(latestHeartbeat.value?.[key]);
}
function formatValue(value: number | null, unit: MetricUnit = 'percent'): string {
    if (value === null) {
        return '—';
    }
    if (unit === 'count') {
        return Math.round(value).toLocaleString('en-IN');
    }
    return `${value.toFixed(unit === 'ratio' ? 2 : 1)}${unit === 'ratio' ? '×' : '%'}`;
}
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(`${value.slice(0, 10)}T00:00:00Z`),
    );
}
function formatIndexLabel(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
async function selectMetric(key: MetricKey, revealChart = false): Promise<void> {
    selection.value.metric = key;
    await nextTick();
    if (revealChart && chartSection.value) {
        const bounds = chartSection.value.getBoundingClientRect();
        if (bounds.top < 0 || bounds.top >= window.innerHeight) {
            chartSection.value.scrollIntoView({ block: 'start' });
        }
        metricSelector.value?.focus({ preventScroll: true });
    }
}
async function selectPeriod(period: PeriodKey): Promise<void> {
    selection.value.period = period;
    await nextTick();
    metricChart.value?.resetView();
}
function changeIndex(): void {
    if (chosenIndex.value === props.index) {
        return;
    }
    router.get(
        `/market-health/${chosenIndex.value}`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isChangingIndex.value = true;
            },
            onFinish: () => {
                isChangingIndex.value = false;
                chosenIndex.value = props.index;
            },
        },
    );
}
watch(
    () => props.index,
    (index) => {
        chosenIndex.value = index;
    },
);
</script>
