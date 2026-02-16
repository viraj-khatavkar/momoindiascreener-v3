<template>
    <div>
        <Head :title="`Market Health - ${indexName}`">
            <meta head-key="og-title" property="og:title" :content="`Market Health - ${indexName}`" />
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
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-4">
                <dt class="text-sm font-medium text-gray-500">% Above 200 DMA</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900">
                    {{ latestHeartbeat?.percentage_above_ma_200 ?? '-' }}%
                </dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <dt class="text-sm font-medium text-gray-500">Advances</dt>
                <dd class="mt-1 text-lg font-semibold text-green-600">
                    {{ latestHeartbeat?.advances ?? '-' }}
                </dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <dt class="text-sm font-medium text-gray-500">Declines</dt>
                <dd class="mt-1 text-lg font-semibold text-red-600">
                    {{ latestHeartbeat?.declines ?? '-' }}
                </dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <dt class="text-sm font-medium text-gray-500">A/D Ratio</dt>
                <dd class="mt-1 text-lg font-semibold text-gray-900">
                    {{ latestHeartbeat?.advance_decline_ratio ?? '-' }}
                </dd>
            </div>
        </div>

        <!-- Section: Moving Average Breadth -->
        <h2 class="mt-10 text-lg font-semibold text-gray-900">Moving Average Breadth</h2>
        <hr class="mt-2" />
        <div class="mt-4 grid grid-cols-1 gap-6">
            <MetricChart
                title="% Above 200 DMA"
                :data="toChartData('percentage_above_ma_200')"
            />
            <MetricChart
                title="% Above 100 DMA"
                :data="toChartData('percentage_above_ma_100')"
                color="#2563eb"
            />
            <MetricChart
                title="% Above 50 DMA"
                :data="toChartData('percentage_above_ma_50')"
                color="#059669"
            />
            <MetricChart
                title="% Above 20 DMA"
                :data="toChartData('percentage_above_ma_20')"
                color="#d97706"
            />
        </div>

        <!-- Section: Return Distribution -->
        <h2 class="mt-10 text-lg font-semibold text-gray-900">Return Distribution</h2>
        <hr class="mt-2" />
        <div class="mt-4 grid grid-cols-1 gap-6">
            <MetricChart
                title="1Y Return > 0%"
                :data="toChartData('percentage_of_stocks_with_returns_one_year_above_zero')"
                color="#059669"
            />
            <MetricChart
                title="1Y Return > 10%"
                :data="toChartData('percentage_of_stocks_with_returns_one_year_above_ten')"
                color="#2563eb"
            />
            <MetricChart
                title="1Y Return > 100%"
                :data="toChartData('percentage_of_stocks_with_returns_one_year_above_hundred')"
                color="#7c3aed"
            />
        </div>

        <!-- Section: All-Time High Proximity -->
        <h2 class="mt-10 text-lg font-semibold text-gray-900">All-Time High Proximity</h2>
        <hr class="mt-2" />
        <div class="mt-4 grid grid-cols-1 gap-6">
            <MetricChart
                title="Within 10% of ATH"
                :data="toChartData('percentage_of_stocks_within_ten_percent_of_ath')"
                color="#7c3aed"
            />
            <MetricChart
                title="Within 20% of ATH"
                :data="toChartData('percentage_of_stocks_within_twenty_percent_of_ath')"
                color="#2563eb"
            />
            <MetricChart
                title="Within 30% of ATH"
                :data="toChartData('percentage_of_stocks_within_thirty_percent_of_ath')"
                color="#059669"
            />
        </div>

        <!-- Section: Market Breadth -->
        <h2 class="mt-10 text-lg font-semibold text-gray-900">Market Breadth</h2>
        <hr class="mt-2" />
        <div class="mt-4">
            <MetricChart
                title="Advance / Decline Ratio"
                :data="toChartData('advance_decline_ratio')"
                color="#7c3aed"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MetricChart from '@/Components/Charts/MetricChart.vue';
import PageHeader from '@/Components/PageHeader.vue';
import type { MarketHeartbeat } from '@/types/app/Models/MarketHeartbeat';

const props = defineProps<{
    heartbeats: MarketHeartbeat[];
    index: string;
    indexName: string;
    availableIndices: string[];
}>();

const latestHeartbeat = computed(() => {
    if (props.heartbeats.length === 0) {
        return null;
    }
    return props.heartbeats[props.heartbeats.length - 1];
});

type MetricKey = keyof Omit<MarketHeartbeat, 'id' | 'date' | 'index' | 'created_at' | 'updated_at'>;

function toChartData(key: MetricKey): { time: string; value: number }[] {
    return props.heartbeats.map((h) => ({
        time: h.date.slice(0, 10),
        value: Number(h[key]),
    }));
}

function formatIndexLabel(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
</script>
