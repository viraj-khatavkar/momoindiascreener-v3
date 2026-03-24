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

        <!-- Index Selector -->
        <div class="inline-flex rounded-md border border-gray-300 bg-white">
            <Link
                v-for="slug in availableIndices"
                :key="slug"
                :href="`/market-health/${slug}`"
                class="px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                :class="
                    slug === index
                        ? 'bg-purple-600 text-white'
                        : 'text-gray-700 hover:bg-gray-50'
                "
                preserve-state
            >
                {{ formatIndexLabel(slug) }}
            </Link>
        </div>

        <!-- Summary Stats -->
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <StatCard
                label="% Above 200 DMA"
                :value="summaryStats.dma200"
                :variant="Number(summaryStats.dma200) >= 50 ? 'green' : 'red'"
            />
            <StatCard
                label="Advances"
                :value="summaryStats.advances"
                variant="green"
            />
            <StatCard
                label="Declines"
                :value="summaryStats.declines"
                variant="red"
            />
            <StatCard
                label="A/D Ratio"
                :value="summaryStats.adRatio"
                :variant="Number(summaryStats.adRatio) >= 1 ? 'green' : 'red'"
            />
        </div>

        <!-- Metric Picker -->
        <div class="mt-8">
            <!-- Group Tabs -->
            <div class="inline-flex rounded-md border border-gray-300 bg-white">
                <button
                    v-for="group in metricGroups"
                    :key="group.name"
                    type="button"
                    class="cursor-pointer px-3 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                    :class="
                        selectedGroup.name === group.name
                            ? 'bg-purple-600 text-white'
                            : 'text-gray-700 hover:bg-gray-50'
                    "
                    @click="selectGroup(group)"
                >
                    {{ group.name }}
                </button>
            </div>

            <!-- Metric Pills -->
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="metric in selectedGroup.metrics"
                    :key="metric.key"
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1.5 text-sm font-medium transition"
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
                    @click="selectedMetric = metric"
                >
                    {{ metric.label }}
                </button>
            </div>
        </div>

        <!-- Chart -->
        <div class="mt-4">
            <MetricChart
                :key="selectedMetric.key"
                :title="selectedMetric.title"
                :data="selectedChartData"
                :color="selectedMetric.color"
                :reference-line="selectedMetric.referenceLine"
                tall
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

const latestHeartbeat = computed(() => {
    return props.heartbeats.at(-1) ?? null;
});

const summaryStats = computed(() => {
    const h = latestHeartbeat.value;
    return {
        dma200: h?.percentage_above_ma_200 != null ? String(h.percentage_above_ma_200) : '-',
        advances: h?.advances != null ? String(h.advances) : '-',
        declines: h?.declines != null ? String(h.declines) : '-',
        adRatio: h?.advance_decline_ratio != null ? String(h.advance_decline_ratio) : '-',
    };
});

const selectedChartData = computed(() => {
    return props.heartbeats.map((h) => ({
        time: h.date.slice(0, 10),
        value: Number(h[selectedMetric.value.key]),
    }));
});

function selectGroup(group: MetricGroup): void {
    selectedGroup.value = group;
    selectedMetric.value = group.metrics[0];
}

function formatIndexLabel(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>
