<template>
    <div class="mx-auto max-w-7xl text-gray-900">
        <Head :title="`${industry.name} stocks`" />

        <header class="border-b border-gray-200 pb-6">
            <p class="text-xs font-semibold tracking-widest text-purple-700 uppercase">Industry</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ industry.name }}</h1>
            <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                <span
                    >{{ stocks.total.toLocaleString('en-IN') }} {{ filters.search ? 'matching ' : ''
                    }}{{ stocks.total === 1 ? 'stock' : 'stocks' }}</span
                >
                <time v-if="date" :datetime="date">Prices as of {{ formatDate(date) }}</time>
                <span v-else>No price data available</span>
            </p>
            <p class="mt-2 text-sm text-gray-500">Current industry labels from NSE. This list includes stocks with a stored industry label.</p>
        </header>

        <div class="my-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <form class="flex flex-1 items-end gap-2 sm:max-w-lg" role="search" @submit.prevent="applyFilters({ search: search.trim() })">
                <div class="min-w-0 flex-1">
                    <label for="industry-search" class="mb-1.5 block text-sm font-medium">Search stocks</label>
                    <input
                        id="industry-search"
                        v-model="search"
                        type="search"
                        maxlength="100"
                        placeholder="Symbol or company name"
                        class="min-h-11 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-purple-600"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="isLoading"
                    class="min-h-11 cursor-pointer rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-purple-600 disabled:opacity-50"
                >
                    Search
                </button>
                <button
                    v-if="filters.search"
                    type="button"
                    :disabled="isLoading"
                    class="min-h-11 cursor-pointer rounded px-2 text-sm font-medium text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                    @click="applyFilters({ search: '' })"
                >
                    Clear
                </button>
            </form>
            <div class="sm:max-w-72">
                <label for="industry-sort" class="mb-1.5 block text-sm font-medium">Sort by</label>
                <select
                    id="industry-sort"
                    :value="`${filters.sort}:${filters.direction}`"
                    :disabled="isLoading"
                    class="min-h-11 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-purple-600 disabled:opacity-50"
                    @change="changeSort"
                >
                    <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </div>

        <div :aria-busy="isLoading" :class="{ 'opacity-50': isLoading }">
            <StockList
                v-if="stocks.data.length"
                :stocks="stocks.data"
                :title="industry.name"
                :sort="filters.sort"
                :direction="filters.direction"
                :loading="isLoading"
                @sort="sortColumn"
            />
            <p v-else class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-10 text-center text-sm text-gray-600">
                {{ filters.search ? 'No stocks match this search.' : 'No stocks have this industry label.' }}
            </p>
            <div class="mt-4 flex flex-wrap justify-between gap-2 text-xs leading-5 text-gray-500">
                <p>Market cap is in ₹ crore. — means data is not available.</p>
                <p v-if="stocks.total">Showing {{ stocks.from }}–{{ stocks.to }} of {{ stocks.total.toLocaleString('en-IN') }}</p>
            </div>
            <Pagination :links="stocks.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import StockList from '@/Components/StockList.vue';
import { formatDate } from '@/utils';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type NumericValue = number | string | null;
type SortKey = 'symbol' | 'name' | 'close_adjusted' | 'marketcap' | 'price_to_earnings' | 'absolute_return_one_year' | 'beta';
interface Filters {
    search: string;
    sort: SortKey;
    direction: 'asc' | 'desc';
}
interface Stock {
    symbol: string;
    name: string | null;
    url: string | null;
    close_adjusted: NumericValue;
    marketcap: NumericValue;
    price_to_earnings: NumericValue;
    absolute_return_one_year: NumericValue;
    beta: NumericValue;
}
const props = defineProps<{
    industry: { name: string; slug: string };
    date: string | null;
    stocks: {
        data: Stock[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
    listUrl: string;
}>();

const columns: { key: SortKey; label: string; text?: boolean }[] = [
    { key: 'symbol', label: 'Symbol', text: true },
    { key: 'name', label: 'Company', text: true },
    { key: 'close_adjusted', label: 'Adjusted close (₹)' },
    { key: 'marketcap', label: 'Market cap (₹ crore)' },
    { key: 'price_to_earnings', label: 'P/E' },
    { key: 'absolute_return_one_year', label: '1-year return' },
    { key: 'beta', label: 'Beta' },
];
const sortOptions = columns.flatMap((column) => [
    { value: `${column.key}:asc`, label: `${column.label}: ${column.text ? 'A–Z' : 'Low to high'}` },
    { value: `${column.key}:desc`, label: `${column.label}: ${column.text ? 'Z–A' : 'High to low'}` },
]);
const search = ref(props.filters.search);
const isLoading = ref(false);
watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

function applyFilters(changes: Partial<Filters>) {
    router.get(
        props.listUrl,
        { ...props.filters, ...changes },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isLoading.value = true;
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
}
function changeSort(event: Event) {
    const [sort, direction] = (event.target as HTMLSelectElement).value.split(':');
    applyFilters({ sort: sort as SortKey, direction: direction as Filters['direction'] });
}
function sortColumn(sort: SortKey) {
    const direction =
        props.filters.sort === sort ? (props.filters.direction === 'asc' ? 'desc' : 'asc') : sort === 'symbol' || sort === 'name' ? 'asc' : 'desc';
    applyFilters({ sort, direction });
}
</script>
