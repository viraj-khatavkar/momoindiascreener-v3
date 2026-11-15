<template>
    <div class="-mx-4 -my-10 bg-white px-4 py-7 text-[#252135] sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
        <Head :title="index.symbol" />
        <div class="mx-auto max-w-[1240px]">
            <nav aria-label="Index navigation" class="flex items-center justify-between gap-4">
                <Link
                    :href="dashboardHref"
                    class="inline-flex min-h-10 shrink-0 items-center gap-2 text-xs font-semibold text-[#706b7d] hover:text-[#7515d9]"
                    ><ArrowLeftIcon class="h-4 w-4" aria-hidden="true" /> All indices</Link
                >
                <div class="flex min-w-0 flex-1 items-center gap-3 sm:max-w-80">
                    <label for="index-switcher" class="sr-only shrink-0 text-xs text-[#706b7d] sm:not-sr-only">Switch index</label>
                    <select
                        id="index-switcher"
                        v-model="chosenIndex"
                        :disabled="isChangingIndex"
                        class="min-h-11 min-w-0 flex-1 rounded-md border border-[#d3cedd] bg-white py-2 pr-8 pl-3 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:opacity-50"
                        @change="changeIndex"
                    >
                        <option v-if="!availableIndices.some((item) => item.slug === slug)" :value="slug">{{ index.symbol }}</option>
                        <option v-for="item in availableIndices" :key="item.slug" :value="item.slug">{{ item.symbol }}</option>
                    </select>
                </div>
            </nav>

            <div :aria-busy="isChangingIndex" :class="{ 'opacity-50': isChangingIndex }">
                <header
                    class="my-5 grid gap-4 border-y border-[#e5e2ea] py-5 sm:my-6 sm:gap-6 sm:py-7 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                >
                    <div class="min-w-0">
                        <p class="mb-3 text-xs text-[#706b7d]">
                            <time :datetime="asOfDate">As of {{ formatIndexDate(asOfDate) }}</time>
                        </p>
                        <h1 class="max-w-3xl font-[Georgia,serif] text-[32px] leading-tight tracking-tight sm:text-[42px]">{{ index.symbol }}</h1>
                        <Link
                            v-if="constituentsUrl"
                            :href="constituentsUrl"
                            class="mt-3 inline-flex min-h-10 items-center rounded text-sm font-semibold text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                            >View constituents</Link
                        >
                    </div>
                    <div class="md:min-w-56 md:border-l md:border-[#e5e2ea] md:pl-8">
                        <p class="text-[10px] font-semibold tracking-[0.14em] text-[#706b7d] uppercase">Closing index level</p>
                        <p class="mt-1 text-[34px] font-medium tracking-tight tabular-nums" data-testid="index-level">
                            {{ formatIndexNumber(index.close)
                            }}<span v-if="slug !== 'india-vix'" class="ml-2 text-xs font-normal tracking-normal text-[#706b7d]">points</span>
                        </p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm tabular-nums" :class="indexChangeClass(index.percentage_change)">
                            <span class="font-semibold"
                                >{{ formatIndexChange(index.percentage_change) }}{{ indexNumber(index.percentage_change) === null ? '' : '%' }}</span
                            ><span class="text-xs text-[#706b7d]"
                                >{{ formatIndexChange(index.points_change) }}{{ indexNumber(index.points_change) === null ? '' : ' pts' }} · daily
                                change</span
                            >
                        </p>
                    </div>
                </header>

                <section class="overflow-hidden rounded-xl border border-[#e5e2ea] bg-white" aria-label="Index history">
                    <Deferred :data="['priceHistory', 'metricHistory']">
                        <template #fallback
                            ><div class="animate-pulse p-5 sm:p-6" role="status" aria-label="Loading index history">
                                <div class="h-9 w-56 rounded bg-[#eeebf2]" />
                                <div class="mt-6 h-12 w-40 rounded bg-[#eeebf2]" />
                                <div class="mt-5 h-[300px] rounded bg-[#f7f6f9]" />
                                <span class="sr-only">Loading index history</span>
                            </div></template
                        >
                        <div class="grid lg:grid-cols-[minmax(0,1fr)_248px]">
                            <div class="min-w-0 p-4 sm:p-6">
                                <div class="flex flex-wrap gap-1 border-b border-[#eeebf2] pb-3" role="group" aria-label="Chart measure">
                                    <button
                                        v-for="metric in availableMetrics"
                                        :key="metric.key"
                                        type="button"
                                        :aria-pressed="selection.metric === metric.key"
                                        class="min-h-10 cursor-pointer rounded-md px-3 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                        :class="selection.metric === metric.key ? 'bg-[#f1e9fb] text-[#7515d9]' : 'text-[#706b7d] hover:bg-[#f7f6f9]'"
                                        @click="selectMetric(metric.key)"
                                    >
                                        {{ metric.tab }}
                                    </button>
                                </div>
                                <div class="mt-5 flex flex-wrap items-end justify-between gap-3">
                                    <div>
                                        <h2 class="text-xs font-medium text-[#706b7d]">{{ selectedMetric.label }}</h2>
                                        <p class="mt-1 text-3xl font-medium tracking-tight tabular-nums" data-testid="index-metric-value">
                                            {{ formatIndexNumber(latestMetricValue)
                                            }}<span v-if="latestMetricValue !== null" class="ml-1 text-base text-[#706b7d]">{{
                                                selectedMetric.suffix
                                            }}</span>
                                        </p>
                                        <p class="mt-1 text-[11px] text-[#8c8498]">Latest close · {{ formatIndexDate(asOfDate) }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-10 cursor-pointer items-center gap-1.5 text-xs font-medium text-[#706b7d] hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-purple-600"
                                        @click="chartRef?.resetView()"
                                    >
                                        <ArrowPathIcon class="h-3.5 w-3.5" aria-hidden="true" /> Reset view
                                    </button>
                                </div>
                                <div class="my-4 flex flex-wrap gap-0.5" role="group" aria-label="Chart period">
                                    <button
                                        v-for="period in indexPeriods"
                                        :key="period.key"
                                        type="button"
                                        :aria-label="`Show ${period.label}`"
                                        :aria-pressed="selection.period === period.key"
                                        class="min-h-10 cursor-pointer rounded-md px-2.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                        :class="selection.period === period.key ? 'bg-[#f1e9fb] text-[#7515d9]' : 'text-[#706b7d] hover:bg-[#f7f6f9]'"
                                        @click="selectPeriod(period.key)"
                                    >
                                        {{ period.key }}
                                    </button>
                                </div>
                                <MetricChart
                                    :key="`${slug}:${selectedMetric.key}`"
                                    ref="chartRef"
                                    :title="selectedMetric.label"
                                    :data="chartData"
                                    :color="selectedMetric.color"
                                    :unit="selectedMetric.unit"
                                    :fixed-percent-scale="false"
                                    :decimals="2"
                                    embedded
                                    fit-content
                                    @range-change="visibleRange = $event"
                                />
                                <div
                                    class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-[#eeebf2] pt-3 text-[11px] text-[#8c8498]"
                                >
                                    <span data-testid="index-chart-range">{{ chartRangeLabel }}</span
                                    ><span>{{ selectedMetric.note }}</span>
                                </div>
                            </div>
                            <aside
                                class="border-t border-[#e5e2ea] bg-[#fcfbfd] p-4 sm:p-6 lg:border-t-0 lg:border-l"
                                aria-label="Latest index statistics"
                            >
                                <h2 class="text-[10px] font-semibold tracking-[0.14em] text-[#706b7d] uppercase">At the latest close</h2>
                                <div class="mt-4 grid grid-cols-2 gap-x-5 lg:grid-cols-1">
                                    <button
                                        v-for="metric in valuationMetrics"
                                        :key="metric.key"
                                        type="button"
                                        :disabled="!hasMetricHistory(metric.key)"
                                        :aria-label="`Show ${metric.label} history`"
                                        class="group flex min-h-20 cursor-pointer flex-col items-start justify-center border-b border-[#e5e2ea] py-3 text-left focus-visible:outline-2 focus-visible:outline-purple-600 disabled:cursor-default"
                                        @click="selectMetric(metric.key)"
                                    >
                                        <span class="flex w-full items-center justify-between gap-2 text-xs text-[#706b7d]"
                                            >{{ metric.label
                                            }}<ArrowUpRightIcon
                                                v-if="hasMetricHistory(metric.key)"
                                                class="h-3.5 w-3.5 text-[#a49cae] group-hover:text-[#7515d9]"
                                                aria-hidden="true"
                                        /></span>
                                        <span class="mt-1 text-xl font-medium tabular-nums"
                                            >{{ formatIndexNumber(metricValue(metric.key))
                                            }}<span v-if="metricValue(metric.key) !== null" class="ml-0.5 text-sm text-[#706b7d]">{{
                                                metric.suffix
                                            }}</span></span
                                        >
                                    </button>
                                    <div class="flex min-h-20 flex-col justify-center py-3">
                                        <p class="text-xs text-[#706b7d]">Volume</p>
                                        <p class="mt-1 text-lg font-medium tabular-nums">{{ formatIndexNumber(index.volume, 0) }}</p>
                                    </div>
                                </div>
                                <p class="mt-5 text-xs leading-6 text-[#8c8498]">
                                    {{
                                        availableMetrics.length === 1
                                            ? 'Valuation history is not available for this index.'
                                            : 'Select a valuation to see its history. The selected period stays the same.'
                                    }}
                                </p>
                            </aside>
                        </div>
                    </Deferred>
                </section>

                <section class="mt-6 rounded-xl border border-[#e5e2ea] bg-white p-4 sm:p-6" aria-labelledby="index-returns-heading">
                    <h2 id="index-returns-heading" class="text-base font-semibold">{{ isSpecialIndex ? 'Monthly changes' : 'Monthly returns' }}</h2>
                    <p class="mt-1 text-xs leading-6 text-[#706b7d]">
                        {{
                            slug === 'nifty50-dividend-points'
                                ? 'This index resets each year. A zero starting value has no percentage change.'
                                : 'Compare changes between monthly closing index levels.'
                        }}
                    </p>
                    <Deferred data="monthlyReturns">
                        <template #fallback
                            ><div class="mt-5 h-64 animate-pulse rounded bg-[#f7f6f9]" role="status" aria-label="Loading monthly returns"
                        /></template>
                        <ReturnsHeatmap v-if="monthlyReturns?.length" :years="monthlyReturns" :as-of-date="asOfDate" />
                        <p v-else class="py-12 text-center text-sm text-[#706b7d]">No monthly history is available for this index.</p>
                    </Deferred>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ArrowLeftIcon, ArrowPathIcon, ArrowUpRightIcon } from '@heroicons/vue/20/solid';
import { Deferred, Head, Link, router, usePage, useRemember } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MetricChart from '@/Components/Charts/MetricChart.vue';
import ReturnsHeatmap from '@/Components/ReturnsHeatmap.vue';
import type { NseIndex } from '@/types/app/Models/NseIndex';
import {
    formatIndexChange,
    formatIndexDate,
    formatIndexNumber,
    indexChangeClass,
    indexNumber,
    indexPeriods,
    indexPeriodStart,
} from '@/utils/nseIndices';
import type { IndexPeriod } from '@/utils/nseIndices';

interface DataPoint {
    time: string;
    value: number | null;
}
interface MetricHistory {
    pe: DataPoint[];
    pb: DataPoint[];
    dividendYield: DataPoint[];
}
interface YearRow {
    year: number;
    months: Record<number, number | null>;
    yearReturn: number | null;
    throughDate: string;
}
const props = defineProps<{
    index: NseIndex;
    slug: string;
    constituentsUrl: string | null;
    availableIndices: { slug: string; symbol: string }[];
    priceHistory?: DataPoint[];
    metricHistory?: MetricHistory;
    monthlyReturns?: YearRow[];
}>();
type MetricKey = 'price' | keyof MetricHistory;
interface Metric {
    key: MetricKey;
    tab: string;
    label: string;
    suffix: string;
    unit: 'number' | 'points' | 'ratio' | 'percent';
    color: string;
    note: string;
}
const metrics = computed<Metric[]>(() => [
    {
        key: 'price',
        tab: 'Price',
        label: 'Index level',
        suffix: props.slug === 'india-vix' ? '' : 'pts',
        unit: props.slug === 'india-vix' ? 'number' : 'points',
        color: '#8547c4',
        note: 'Daily closing levels',
    },
    {
        key: 'pe',
        tab: 'P/E',
        label: 'Price to earnings',
        suffix: '×',
        unit: 'ratio',
        color: '#6482b8',
        note: 'P/E ratio · Missing values stay empty',
    },
    { key: 'pb', tab: 'P/B', label: 'Price to book', suffix: '×', unit: 'ratio', color: '#6482b8', note: 'P/B ratio · Missing values stay empty' },
    {
        key: 'dividendYield',
        tab: 'Dividend yield',
        label: 'Dividend yield',
        suffix: '%',
        unit: 'percent',
        color: '#438774',
        note: 'Yield (%) · Missing values stay empty',
    },
]);
const selection = ref(useRemember<{ metric: MetricKey; period: IndexPeriod }>({ metric: 'price', period: '1Y' }, 'IndexExplorer:chart'));
const chosenIndex = ref(props.slug);
const isChangingIndex = ref(false);
const chartRef = ref<InstanceType<typeof MetricChart>>();
const visibleRange = ref<{ from: string; to: string } | null>(null);
const page = usePage();
const dashboardQuery = computed(() => {
    const query = new URLSearchParams(new URLSearchParams(page.url.split('?')[1]).get('indices') ?? '');
    query.delete('group');
    return query.toString();
});
const dashboardHref = computed(() => `/dashboard${dashboardQuery.value ? `?${new URLSearchParams(dashboardQuery.value).toString()}` : ''}`);
const asOfDate = computed(() => props.index.date.slice(0, 10));
const isSpecialIndex = computed(() => ['india-vix', 'nifty50-dividend-points'].includes(props.slug));
const availableMetrics = computed(() => metrics.value.filter((metric) => metric.key === 'price' || hasMetricHistory(metric.key)));
const valuationMetrics = computed(() => metrics.value.filter((metric) => metric.key !== 'price'));
const selectedMetric = computed(() => metrics.value.find((metric) => metric.key === selection.value.metric)!);
const latestMetricValue = computed(() => metricValue(selection.value.metric));
const chartData = computed(() => {
    const data = selection.value.metric === 'price' ? (props.priceHistory ?? []) : (props.metricHistory?.[selection.value.metric] ?? []);
    const start = indexPeriodStart(asOfDate.value, selection.value.period);
    return data.filter((point) => (!start || point.time >= start) && point.time <= asOfDate.value);
});
const chartRangeLabel = computed(() => {
    const from = visibleRange.value?.from ?? chartData.value[0]?.time;
    const to = visibleRange.value?.to ?? chartData.value.at(-1)?.time;
    return from && to ? `${formatIndexDate(from)} – ${formatIndexDate(to)}` : 'No history in this period';
});
watch(
    () => props.slug,
    (slug) => {
        chosenIndex.value = slug;
        visibleRange.value = null;
    },
);
watch(
    () => props.metricHistory,
    (history) => {
        if (history && selection.value.metric !== 'price' && !hasMetricHistory(selection.value.metric)) selection.value.metric = 'price';
    },
);
function hasMetricHistory(key: MetricKey): boolean {
    return key === 'price' || (props.metricHistory?.[key]?.some((point) => point.value !== null) ?? false);
}
function metricValue(key: MetricKey): number | null {
    return indexNumber(
        { price: props.index.close, pe: props.index.price_to_earnings, pb: props.index.price_to_book, dividendYield: props.index.dividend_yield }[
            key
        ],
    );
}
function selectMetric(key: MetricKey): void {
    selection.value.metric = key;
    visibleRange.value = null;
}
function selectPeriod(period: IndexPeriod): void {
    selection.value.period = period;
    visibleRange.value = null;
    chartRef.value?.resetView();
}
function changeIndex(): void {
    if (chosenIndex.value === props.slug) return;
    router.visit(`/nse-index/${chosenIndex.value}${dashboardQuery.value ? `?indices=${encodeURIComponent(dashboardQuery.value)}` : ''}`, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            isChangingIndex.value = true;
        },
        onFinish: () => {
            isChangingIndex.value = false;
            chosenIndex.value = props.slug;
        },
    });
}
</script>
