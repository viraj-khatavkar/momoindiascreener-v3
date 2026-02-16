<template>
    <div>
        <Head title="Indices Dashboard" />

        <PageHeader>Indices Dashboard</PageHeader>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-500">Last updated: {{ latestDate }}</p>
            <div class="relative">
                <MagnifyingGlassIcon
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search indices..."
                    class="w-full rounded-md border border-gray-300 py-2 pl-9 pr-3 text-sm placeholder-gray-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 sm:w-64"
                />
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="item in filteredIndices"
                :key="item.id"
                :href="`/nse-index/${item.slug}`"
                class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ item.symbol }}</h3>
                    <span
                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="
                            (item.percentage_change ?? 0) >= 0
                                ? 'bg-green-50 text-green-700 ring-green-600/20'
                                : 'bg-red-50 text-red-700 ring-red-600/20'
                        "
                    >
                        {{ (item.percentage_change ?? 0) >= 0 ? '+' : ''
                        }}{{ item.percentage_change ?? '0.00' }}%
                    </span>
                </div>

                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-lg font-bold text-gray-900">{{ formatIndian(item.close) }}</span>
                    <span
                        class="text-xs"
                        :class="
                            (item.points_change ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'
                        "
                    >
                        {{ (item.points_change ?? 0) >= 0 ? '+' : ''
                        }}{{ item.points_change ?? '0.00' }}
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-500">
                    <div>
                        <span class="block font-medium text-gray-400">PE</span>
                        {{ item.price_to_earnings ?? '-' }}
                    </div>
                    <div>
                        <span class="block font-medium text-gray-400">PB</span>
                        {{ item.price_to_book ?? '-' }}
                    </div>
                    <div>
                        <span class="block font-medium text-gray-400">Div Yield</span>
                        {{ item.dividend_yield ? `${item.dividend_yield}%` : '-' }}
                    </div>
                </div>
            </Link>
        </div>

        <p v-if="filteredIndices.length === 0" class="mt-8 text-center text-sm text-gray-500">
            No indices match your search.
        </p>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import type { NseIndex } from '@/types/app/Models/NseIndex';

const props = defineProps<{
    indices: NseIndex[];
    latestDate: string;
}>();

const search = ref('');

const filteredIndices = computed(() => {
    const q = search.value.toLowerCase().trim();
    if (!q) {
        return props.indices;
    }
    return props.indices.filter((i) => i.symbol.toLowerCase().includes(q));
});

function formatIndian(value: number | string): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
