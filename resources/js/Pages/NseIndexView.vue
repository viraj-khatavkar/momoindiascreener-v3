<template>
    <div>
        <Head :title="index.symbol" />

        <PageHeader>
            <div class="flex items-end gap-x-4">
                {{ index.symbol }}
                <span
                    class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-yellow-600/20 ring-inset"
                >
                    &#8377; {{ formatIndian(index.close) }}
                </span>
                <span
                    class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                    :class="
                        (index.percentage_change ?? 0) >= 0
                            ? 'bg-green-50 text-green-700 ring-green-600/20'
                            : 'bg-red-50 text-red-700 ring-red-600/20'
                    "
                >
                    {{ (index.percentage_change ?? 0) >= 0 ? '+' : ''
                    }}{{ index.percentage_change ?? '0.00' }}%
                </span>
            </div>
        </PageHeader>

        <!-- Key Stats -->
        <dl class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div>
                <dt class="text-sm font-medium text-gray-500">Close</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ formatIndian(index.close) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">PE</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ index.price_to_earnings ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">PB</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ index.price_to_book ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Dividend Yield</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ index.dividend_yield ? `${index.dividend_yield}%` : '-' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Volume</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ index.volume ? index.volume.toLocaleString('en-IN') : '-' }}
                </dd>
            </div>
        </dl>

        <hr class="mt-8" />

        <!-- Price Chart (Deferred) -->
        <Deferred data="priceHistory">
            <template #fallback>
                <div class="mt-8 rounded-lg bg-slate-50 p-4 md:p-6">
                    <div class="animate-pulse">
                        <div class="flex gap-2">
                            <div v-for="n in 5" :key="n" class="h-8 w-12 rounded bg-gray-200" />
                        </div>
                        <div class="mt-4 h-[400px] rounded bg-gray-200" />
                    </div>
                </div>
            </template>

            <div v-if="priceHistory && priceHistory.length > 0" class="mt-8">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Price History</h2>
                <PriceChart :data="priceHistory" />
            </div>
        </Deferred>

        <hr class="mt-8" />

        <!-- Valuation Metrics (Deferred) -->
        <Deferred data="metricHistory">
            <template #fallback>
                <div class="mt-8 space-y-6">
                    <div v-for="n in 3" :key="n" class="animate-pulse">
                        <div class="h-4 w-32 rounded bg-gray-200" />
                        <div class="mt-2 h-[200px] rounded bg-gray-200" />
                    </div>
                </div>
            </template>

            <div v-if="metricHistory" class="mt-8 space-y-6">
                <h2 class="text-lg font-semibold text-gray-900">Valuation Metrics</h2>
                <MetricChart
                    v-if="metricHistory.pe.length > 0"
                    title="Price to Earnings (PE)"
                    :data="metricHistory.pe"
                    color="#7c3aed"
                />
                <MetricChart
                    v-if="metricHistory.pb.length > 0"
                    title="Price to Book (PB)"
                    :data="metricHistory.pb"
                    color="#2563eb"
                />
                <MetricChart
                    v-if="metricHistory.dividendYield.length > 0"
                    title="Dividend Yield"
                    :data="metricHistory.dividendYield"
                    color="#059669"
                />
            </div>
        </Deferred>

        <hr class="mt-8" />

        <!-- Monthly Returns Heatmap (Deferred) -->
        <Deferred data="monthlyReturns">
            <template #fallback>
                <div class="mt-8 animate-pulse">
                    <div class="h-5 w-48 rounded bg-gray-200" />
                    <div class="mt-4 h-[300px] rounded bg-gray-200" />
                </div>
            </template>

            <div v-if="monthlyReturns && monthlyReturns.length > 0" class="mt-8">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Monthly Returns</h2>
                <ReturnsHeatmap :years="monthlyReturns" />
            </div>
        </Deferred>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import MetricChart from '@/Components/Charts/MetricChart.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PriceChart from '@/Components/Charts/PriceChart.vue';
import ReturnsHeatmap from '@/Components/ReturnsHeatmap.vue';
import type { NseIndex } from '@/types/app/Models/NseIndex';

interface DataPoint {
    time: string;
    value: number;
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
}

defineProps<{
    index: NseIndex;
    slug: string;
    priceHistory?: DataPoint[];
    metricHistory?: MetricHistory;
    monthlyReturns?: YearRow[];
}>();

function formatIndian(value: number | string): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
