<template>
    <div>
        <Head :title="`Market Health - ${indexName}`">
            <meta
                head-key="og-title"
                property="og:title"
                :content="`Market Health - ${indexName}`"
            />
            <meta
                head-key="og-description"
                property="og:description"
                :content="`Market breadth and health metrics for ${indexName}`"
            />
        </Head>

        <PageHeader :description="indexName">Market Health</PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500">
                <span>As of {{ latestDateLabel }}</span>
                <span class="text-gray-300">·</span>
                <span>{{ historyLabel }}</span>
                <span v-if="hasHeartbeatData" class="text-gray-300">·</span>
                <span v-if="hasHeartbeatData">Last 12 months</span>
            </div>

            <span
                class="inline-flex w-fit items-center rounded-md px-2.5 py-1 text-xs font-medium"
                :class="marketState.class"
            >
                {{ marketState.label }}
            </span>
        </div>

        <!-- Index Selector -->
        <div class="mt-4 sm:hidden">
            <label for="market-health-index" class="sr-only">Index</label>
            <select
                id="market-health-index"
                :value="index"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-900 shadow-xs focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                @change="changeIndex"
            >
                <option
                    v-for="slug in availableIndices"
                    :key="slug"
                    :value="slug"
                >
                    {{ formatIndexLabel(slug) }}
                </option>
            </select>
        </div>

        <div class="mt-4 hidden flex-wrap gap-2 sm:flex">
            <Link
                v-for="slug in availableIndices"
                :key="slug"
                :href="`/market-health/${slug}`"
                class="rounded-md border px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-purple-500/30 focus-visible:outline-none"
                :class="
                    slug === index
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50'
                "
                :aria-current="slug === index ? 'page' : undefined"
                prefetch
                preserve-state
                preserve-scroll
            >
                {{ formatIndexLabel(slug) }}
            </Link>
        </div>

        <!-- Summary Stats -->
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <StatCard
                label="% Above 200 DMA"
                :value="summaryStats.dma200"
                :variant="dma200Variant"
            />
            <StatCard
                label="Advances"
                :value="summaryStats.advances"
                :variant="hasHeartbeatData ? 'green' : undefined"
            />
            <StatCard
                label="Declines"
                :value="summaryStats.declines"
                :variant="hasHeartbeatData ? 'red' : undefined"
            />
            <StatCard
                label="A/D Ratio"
                :value="summaryStats.adRatio"
                :variant="adRatioVariant"
            />
        </div>

        <!-- Metric Picker -->
        <div class="mt-8 rounded-lg border border-gray-200 bg-white p-3 sm:p-4">
            <!-- Group Tabs -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="group in metricGroups"
                        :key="group.name"
                        type="button"
                        class="cursor-pointer rounded-md border px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-purple-500/30 focus-visible:outline-none"
                        :class="
                            selectedGroup.name === group.name
                                ? 'border-gray-900 bg-gray-900 text-white'
                                : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50'
                        "
                        :aria-pressed="selectedGroup.name === group.name"
                        @click="selectGroup(group)"
                    >
                        {{ group.name }}
                    </button>
                </div>

                <!-- Metric Pills -->
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="metric in selectedGroup.metrics"
                        :key="metric.key"
                        type="button"
                        class="cursor-pointer rounded-full border px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-purple-500/30 focus-visible:outline-none"
                        :class="
                            selectedMetric.key === metric.key
                                ? 'border-transparent text-white'
                                : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 hover:bg-gray-50'
                        "
                        :style="
                            selectedMetric.key === metric.key
                                ? { backgroundColor: metric.color }
                                : {}
                        "
                        :aria-pressed="selectedMetric.key === metric.key"
                        @click="selectedMetric = metric"
                    >
                        {{ metric.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div v-if="hasHeartbeatData" class="mt-4">
            <MetricChart
                :key="selectedMetric.key"
                :title="selectedMetric.title"
                :data="selectedChartData"
                :color="selectedMetric.color"
                :reference-line="selectedMetric.referenceLine"
                tall
            />
        </div>
        <div
            v-else
            class="mt-4 flex min-h-[360px] items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center"
        >
            <div>
                <p class="text-sm font-medium text-gray-900">No market health data for {{ indexName }} yet.</p>
                <p class="mt-1 text-sm text-gray-500">The chart will appear once heartbeat history is available.</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MetricChart from '@/Components/Charts/MetricChart.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import type { MarketHeartbeat } from '@/types/app/Models/MarketHeartbeat';

const props = defineProps<{
    heartbeats: MarketHeartbeat[];
    index: string;
    indexName: string;
    availableIndices: string[];
}>();

type NumericMetricKey =
    | 'percentage_above_ma_200'
    | 'percentage_above_ma_100'
    | 'percentage_above_ma_50'
    | 'percentage_above_ma_20'
    | 'percentage_of_stocks_with_returns_one_year_above_zero'
    | 'percentage_of_stocks_with_returns_one_year_above_ten'
    | 'percentage_of_stocks_with_returns_one_year_above_hundred'
    | 'percentage_of_stocks_within_ten_percent_of_ath'
    | 'percentage_of_stocks_within_twenty_percent_of_ath'
    | 'percentage_of_stocks_within_thirty_percent_of_ath'
    | 'advances'
    | 'declines'
    | 'advance_decline_ratio';

interface Metric {
    key: NumericMetricKey;
    label: string;
    title: string;
    color: string;
    referenceLine?: number;
}

interface MetricGroup {
    name: string;
    metrics: Metric[];
}

const metricGroups: MetricGroup[] = [
    {
        name: 'Moving Averages',
        metrics: [
            { key: 'percentage_above_ma_200', label: '200 DMA', title: '% of Stocks Above 200-Day Moving Average', color: '#7c3aed', referenceLine: 50 },
            { key: 'percentage_above_ma_100', label: '100 DMA', title: '% of Stocks Above 100-Day Moving Average', color: '#2563eb', referenceLine: 50 },
            { key: 'percentage_above_ma_50', label: '50 DMA', title: '% of Stocks Above 50-Day Moving Average', color: '#059669', referenceLine: 50 },
            { key: 'percentage_above_ma_20', label: '20 DMA', title: '% of Stocks Above 20-Day Moving Average', color: '#d97706', referenceLine: 50 },
        ],
    },
    {
        name: 'Returns',
        metrics: [
            { key: 'percentage_of_stocks_with_returns_one_year_above_zero', label: '1Y > 0%', title: '% of Stocks with 1-Year Return Above 0%', color: '#059669', referenceLine: 50 },
            { key: 'percentage_of_stocks_with_returns_one_year_above_ten', label: '1Y > 10%', title: '% of Stocks with 1-Year Return Above 10%', color: '#2563eb', referenceLine: 50 },
            { key: 'percentage_of_stocks_with_returns_one_year_above_hundred', label: '1Y > 100%', title: '% of Stocks with 1-Year Return Above 100%', color: '#7c3aed' },
        ],
    },
    {
        name: 'ATH Proximity',
        metrics: [
            { key: 'percentage_of_stocks_within_ten_percent_of_ath', label: 'Within 10%', title: '% of Stocks Within 10% of All-Time High', color: '#7c3aed' },
            { key: 'percentage_of_stocks_within_twenty_percent_of_ath', label: 'Within 20%', title: '% of Stocks Within 20% of All-Time High', color: '#2563eb' },
            { key: 'percentage_of_stocks_within_thirty_percent_of_ath', label: 'Within 30%', title: '% of Stocks Within 30% of All-Time High', color: '#059669' },
        ],
    },
    {
        name: 'Breadth',
        metrics: [
            { key: 'advance_decline_ratio', label: 'A/D Ratio', title: 'Advance / Decline Ratio', color: '#7c3aed', referenceLine: 1 },
        ],
    },
];

const selectedGroup = ref<MetricGroup>(metricGroups[0]);
const selectedMetric = ref<Metric>(metricGroups[0].metrics[0]);

const dateFormatter = new Intl.DateTimeFormat('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const latestHeartbeat = computed(() => {
    return props.heartbeats.at(-1) ?? null;
});

const hasHeartbeatData = computed(() => props.heartbeats.length > 0);

const latestDateLabel = computed(() => {
    return latestHeartbeat.value ? formatDate(latestHeartbeat.value.date) : 'No data';
});

const historyLabel = computed(() => {
    const firstHeartbeat = props.heartbeats.at(0);

    if (!firstHeartbeat || !latestHeartbeat.value) {
        return 'No history available';
    }

    return `${formatDate(firstHeartbeat.date)} - ${formatDate(latestHeartbeat.value.date)}`;
});

const summaryStats = computed(() => {
    const h = latestHeartbeat.value;
    return {
        dma200: h?.percentage_above_ma_200 != null ? formatPercent(h.percentage_above_ma_200) : '-',
        advances: h?.advances != null ? formatCount(h.advances) : '-',
        declines: h?.declines != null ? formatCount(h.declines) : '-',
        adRatio: h?.advance_decline_ratio != null ? formatRatio(h.advance_decline_ratio) : '-',
    };
});

type StatVariant = 'green' | 'red' | 'blue' | 'amber' | undefined;

const dma200Variant = computed<StatVariant>(() => {
    const value = numericValue(latestHeartbeat.value?.percentage_above_ma_200);

    if (value === null) {
        return undefined;
    }

    return value >= 50 ? 'green' : 'red';
});

const adRatioVariant = computed<StatVariant>(() => {
    const value = numericValue(latestHeartbeat.value?.advance_decline_ratio);

    if (value === null) {
        return undefined;
    }

    return value >= 1 ? 'green' : 'red';
});

const marketState = computed(() => {
    const dma200 = numericValue(latestHeartbeat.value?.percentage_above_ma_200);
    const adRatio = numericValue(latestHeartbeat.value?.advance_decline_ratio);

    if (dma200 === null || adRatio === null) {
        return {
            label: 'No Data',
            class: 'bg-gray-100 text-gray-700',
        };
    }

    if (dma200 >= 55 && adRatio >= 1) {
        return {
            label: 'Strong Breadth',
            class: 'bg-green-50 text-green-700 ring-1 ring-green-200',
        };
    }

    if (dma200 < 45 || adRatio < 0.8) {
        return {
            label: 'Weak Breadth',
            class: 'bg-red-50 text-red-700 ring-1 ring-red-200',
        };
    }

    return {
        label: 'Neutral Breadth',
        class: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    };
});

const selectedChartData = computed(() => {
    return props.heartbeats
        .map((h) => ({
            time: h.date.slice(0, 10),
            value: Number(h[selectedMetric.value.key]),
        }))
        .filter((point) => Number.isFinite(point.value));
});

function selectGroup(group: MetricGroup): void {
    selectedGroup.value = group;
    selectedMetric.value = group.metrics[0];
}

function changeIndex(event: Event): void {
    const target = event.target as HTMLSelectElement;

    if (target.value === props.index) {
        return;
    }

    router.visit(`/market-health/${target.value}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

function formatIndexLabel(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatDate(value: string): string {
    return dateFormatter.format(new Date(value.slice(0, 10)));
}

function formatPercent(value: number | string): string {
    return `${Number(value).toFixed(1)}%`;
}

function formatCount(value: number | string): string {
    return String(Math.round(Number(value)));
}

function formatRatio(value: number | string): string {
    return `${Number(value).toFixed(2)}x`;
}

function numericValue(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}
</script>
