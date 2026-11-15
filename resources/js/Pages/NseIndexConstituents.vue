<template>
    <div class="mx-auto max-w-7xl text-gray-900">
        <Head :title="`${indexName} constituents`" />

        <nav aria-label="Index navigation" class="mb-6 flex flex-wrap items-center justify-between gap-3 text-sm">
            <Link
                :href="allIndicesUrl"
                class="inline-flex min-h-10 items-center gap-2 rounded text-gray-600 hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-purple-600"
            >
                <ArrowLeftIcon class="size-4" aria-hidden="true" /> All indices
            </Link>
            <Link
                v-if="indexUrl"
                :href="indexUrl"
                class="inline-flex min-h-10 items-center rounded font-medium text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
            >
                Index overview
            </Link>
        </nav>

        <header class="flex flex-col justify-between gap-5 border-b border-gray-200 pb-6 sm:flex-row sm:items-end">
            <div>
                <p class="text-xs font-semibold tracking-widest text-purple-700 uppercase">Constituents</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ indexName }}</h1>
                <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span
                        >{{ constituents.total.toLocaleString('en-IN') }} {{ filters.search ? 'matching ' : ''
                        }}{{ constituents.total === 1 ? 'constituent' : 'constituents' }}</span
                    >
                    <time v-if="date" :datetime="date">As of {{ formatDate(date) }}</time>
                    <span v-else>No data available</span>
                </p>
            </div>
            <a
                v-if="constituents.total > 0"
                :href="exportUrl"
                class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 self-start rounded-md bg-purple-700 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                <ArrowDownTrayIcon class="size-4" aria-hidden="true" /> Export CSV
            </a>
        </header>

        <div class="my-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <form class="flex flex-1 items-end gap-2 sm:max-w-lg" role="search" @submit.prevent="applyFilters({ search: search.trim() })">
                <div class="min-w-0 flex-1">
                    <label for="constituent-search" class="mb-1.5 block text-sm font-medium">Search constituents</label>
                    <input
                        id="constituent-search"
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
                <label for="constituent-sort" class="mb-1.5 block text-sm font-medium">Sort by</label>
                <select
                    id="constituent-sort"
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
            <div v-if="constituents.data.length" class="hidden lg:block">
                <table class="w-full border-collapse text-sm">
                    <caption class="sr-only">
                        {{
                            indexName
                        }}
                        constituents and market data
                    </caption>
                    <thead class="border-y border-gray-200 bg-gray-50">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                scope="col"
                                class="px-3 py-3 font-semibold"
                                :class="column.text ? 'text-left' : 'text-right'"
                                :aria-sort="filters.sort === column.key ? (filters.direction === 'asc' ? 'ascending' : 'descending') : 'none'"
                            >
                                <button
                                    type="button"
                                    :disabled="isLoading"
                                    class="inline-flex min-h-9 cursor-pointer items-center gap-1 rounded hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-purple-600"
                                    @click="sortColumn(column.key)"
                                >
                                    {{ column.label }}
                                    <ChevronUpIcon
                                        v-if="filters.sort === column.key && filters.direction === 'asc'"
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <ChevronDownIcon v-else-if="filters.sort === column.key" class="size-3.5 shrink-0" aria-hidden="true" />
                                    <ChevronUpDownIcon v-else class="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="stock in constituents.data" :key="stock.symbol" class="hover:bg-purple-50/30">
                            <td class="px-3 py-4">
                                <Link
                                    :href="stock.url"
                                    class="inline-flex min-h-9 items-center rounded font-semibold text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                                    >{{ stock.symbol }}</Link
                                >
                            </td>
                            <td class="max-w-64 px-3 py-4 break-words text-gray-600">{{ stock.name || '—' }}</td>
                            <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.close_adjusted) }}</td>
                            <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.marketcap, 0) }}</td>
                            <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.price_to_earnings) }}</td>
                            <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums" :class="returnClass(stock.absolute_return_one_year)">
                                {{ formatReturn(stock.absolute_return_one_year) }}
                            </td>
                            <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.beta) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul v-if="constituents.data.length" class="divide-y divide-gray-200 border-y border-gray-200 lg:hidden" aria-label="Constituents">
                <li v-for="stock in constituents.data" :key="stock.symbol" class="py-5">
                    <Link
                        :href="stock.url"
                        class="inline-flex min-h-9 items-center rounded text-base font-semibold text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                        >{{ stock.symbol }}</Link
                    >
                    <p class="mt-1 text-sm break-words text-gray-600">{{ stock.name || '—' }}</p>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">Adjusted close (₹)</dt>
                            <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.close_adjusted) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Market cap (₹ crore)</dt>
                            <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.marketcap, 0) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">P/E</dt>
                            <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.price_to_earnings) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">1-year return</dt>
                            <dd class="mt-1 font-medium tabular-nums" :class="returnClass(stock.absolute_return_one_year)">
                                {{ formatReturn(stock.absolute_return_one_year) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Beta</dt>
                            <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.beta) }}</dd>
                        </div>
                    </dl>
                </li>
            </ul>

            <p v-else class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-10 text-center text-sm text-gray-600">
                {{ filters.search ? 'No constituents match this search.' : 'Constituents are not available for this date.' }}
            </p>
            <div class="mt-4 flex flex-wrap justify-between gap-2 text-xs leading-5 text-gray-500">
                <p>Market cap is in ₹ crore. — means data is not available.</p>
                <p v-if="constituents.total">
                    Showing {{ constituents.from }}–{{ constituents.to }} of {{ constituents.total.toLocaleString('en-IN') }}
                </p>
            </div>
            <Pagination :links="constituents.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import { ArrowDownTrayIcon, ArrowLeftIcon, ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type NumericValue = number | string | null;
type SortKey = 'symbol' | 'name' | 'close_adjusted' | 'marketcap' | 'price_to_earnings' | 'absolute_return_one_year' | 'beta';
interface Filters {
    date: string | null;
    search: string;
    sort: SortKey;
    direction: 'asc' | 'desc';
}
interface Constituent {
    symbol: string;
    name: string | null;
    url: string;
    close_adjusted: NumericValue;
    marketcap: NumericValue;
    price_to_earnings: NumericValue;
    absolute_return_one_year: NumericValue;
    beta: NumericValue;
}
const props = defineProps<{
    indexName: string;
    slug: string;
    date: string | null;
    constituents: {
        data: Constituent[];
        total: number;
        from: number | null;
        to: number | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: Filters;
    listUrl: string;
    exportUrl: string;
    indexUrl: string | null;
    allIndicesUrl: string;
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
function numberValue(value: NumericValue): number | null {
    if (value === null || value === '') {
        return null;
    }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}
function formatNumber(value: NumericValue, decimals = 2): string {
    const number = numberValue(value);
    return number === null ? '—' : number.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
function formatReturn(value: NumericValue): string {
    const number = numberValue(value);
    return number === null ? '—' : `${number > 0 ? '+' : ''}${formatNumber(number)}%`;
}
function returnClass(value: NumericValue): string {
    const number = numberValue(value);
    return number === null || number === 0 ? 'text-gray-600' : number > 0 ? 'text-emerald-700' : 'text-rose-700';
}
</script>
