<template>
    <div class="-mx-4 -my-10 bg-white px-4 py-8 text-[#252135] sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
        <Head title="NSE indices" />
        <div class="mx-auto max-w-[1240px]">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="text-[10px] font-semibold tracking-[0.16em] text-[#706b7d] uppercase">Market explorer</p>
                    <h1 class="mt-2 font-[Georgia,serif] text-4xl tracking-tight sm:text-[44px]">NSE indices</h1>
                    <p class="mt-3 text-sm leading-6 text-[#706b7d]">Compare daily moves and valuations. Open an index to explore its history.</p>
                </div>
                <div class="flex shrink-0 items-center justify-between gap-5 text-xs sm:flex-col sm:items-end sm:gap-2">
                    <p class="text-[#706b7d]">
                        Daily close <span aria-hidden="true">·</span> <span class="font-medium text-[#252135]">{{ latestDate }}</span>
                    </p>
                    <Link href="/market-health" class="inline-flex min-h-9 items-center gap-1.5 font-semibold text-[#7515d9] hover:underline">
                        Market Health <ArrowUpRightIcon class="h-4 w-4" aria-hidden="true" />
                    </Link>
                </div>
            </header>

            <section
                v-if="featuredIndices.length"
                aria-label="Key market indices"
                class="my-7 grid grid-cols-2 overflow-hidden rounded-xl border border-[#e5e2ea] bg-white lg:grid-cols-4"
            >
                <Link
                    v-for="(item, position) in featuredIndices"
                    :key="item.slug"
                    :href="indexHref(item.slug)"
                    class="group min-w-0 p-4 transition-colors hover:bg-[#faf8fd] focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-purple-600 sm:p-5"
                    :class="[
                        position % 2 === 0 ? 'border-r border-[#e5e2ea]' : '',
                        position < 2 ? 'border-b border-[#e5e2ea] lg:border-b-0' : '',
                        position === 1 ? 'lg:border-r' : '',
                    ]"
                >
                    <span class="flex min-h-10 items-start justify-between gap-2 text-xs font-semibold text-[#706b7d] sm:min-h-8"
                        >{{ item.symbol }}<ArrowUpRightIcon class="h-3.5 w-3.5 shrink-0 text-[#a49cae] group-hover:text-[#7515d9]" aria-hidden="true"
                    /></span>
                    <span class="block text-xl font-semibold tracking-tight tabular-nums sm:text-2xl">{{ formatIndexNumber(item.close) }}</span>
                    <span class="mt-2 block text-xs font-medium tabular-nums" :class="indexChangeClass(item.percentage_change)"
                        >{{ formatIndexChange(item.percentage_change) }}{{ indexNumber(item.percentage_change) === null ? '' : '%' }}
                        <span class="font-normal text-[#706b7d]">this session</span></span
                    >
                </Link>
            </section>

            <section class="mt-7 overflow-hidden rounded-xl border border-[#e5e2ea] bg-white" aria-labelledby="index-comparison-title">
                <div class="border-b border-[#e5e2ea] p-4 sm:p-6">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 id="index-comparison-title" class="scroll-mt-28 text-base font-semibold">Find your index</h2>
                            <p class="mt-1 text-xs text-[#706b7d]">{{ indices.length }} indices · Latest available session</p>
                        </div>
                        <div class="relative w-full sm:w-72">
                            <label for="index-search" class="sr-only">Search indices</label>
                            <MagnifyingGlassIcon class="pointer-events-none absolute top-3 left-3 h-4 w-4 text-[#8c8498]" aria-hidden="true" />
                            <input
                                id="index-search"
                                v-model="filters.search"
                                type="search"
                                placeholder="Search indices…"
                                class="min-h-11 w-full rounded-md border border-[#d3cedd] bg-white py-2 pr-3 pl-9 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#eeebf2] px-4 py-3 sm:px-6">
                    <p class="text-xs text-[#706b7d]" aria-live="polite" data-testid="index-result-count">
                        {{ filteredIndices.length }} {{ filteredIndices.length === 1 ? 'index' : 'indices'
                        }}<template v-if="filters.search"> matching “{{ filters.search.trim() }}”</template>
                    </p>
                    <div class="flex items-center gap-2">
                        <label for="index-sort" class="text-xs text-[#706b7d]">Sort</label>
                        <select
                            id="index-sort"
                            v-model="filters.sort"
                            class="min-h-9 max-w-52 rounded-md border border-[#e5e2ea] bg-white py-1 pr-7 pl-2 text-xs font-medium focus-visible:outline-2 focus-visible:outline-purple-600"
                        >
                            <option v-for="sort in sortOptions" :key="sort.key" :value="sort.key">{{ sort.label }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="pageIndices.length" class="hidden md:block">
                    <table class="w-full table-fixed text-sm">
                        <caption class="sr-only">
                            Index levels, daily changes, and valuations as of
                            {{
                                latestDate
                            }}. Select a column to sort.
                        </caption>
                        <thead class="border-b border-[#eeebf2] bg-[#fcfbfd] text-xs text-[#706b7d]">
                            <tr>
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    scope="col"
                                    class="py-1"
                                    :class="column.key === 'name' ? 'w-[29%] pl-6 text-left' : 'pr-5 text-right'"
                                    :aria-sort="sortAria(column.key)"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex min-h-11 cursor-pointer items-center gap-1 font-medium hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-purple-600"
                                        @click="sortBy(column.key)"
                                    >
                                        {{ column.label
                                        }}<span v-if="filters.sort.startsWith(`${column.key}-`)" aria-hidden="true">{{
                                            filters.sort.endsWith('asc') ? '↑' : '↓'
                                        }}</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#eeebf2]">
                            <tr v-for="item in pageIndices" :key="item.slug" class="group hover:bg-[#faf8fd]" data-testid="index-row">
                                <th scope="row" class="py-4 pr-3 pl-6 text-left font-normal">
                                    <Link
                                        :href="indexHref(item.slug)"
                                        class="inline-flex items-center gap-2 leading-5 font-semibold hover:text-[#7515d9] focus-visible:outline-2 focus-visible:outline-purple-600"
                                        >{{ item.symbol
                                        }}<ArrowUpRightIcon class="h-3.5 w-3.5 shrink-0 text-[#a49cae] group-hover:text-[#7515d9]" aria-hidden="true"
                                    /></Link>
                                </th>
                                <td class="pr-5 text-right font-medium tabular-nums">{{ formatIndexNumber(item.close) }}</td>
                                <td class="pr-5 text-right tabular-nums">
                                    <span class="font-semibold" :class="indexChangeClass(item.percentage_change)"
                                        >{{ formatIndexChange(item.percentage_change)
                                        }}{{ indexNumber(item.percentage_change) === null ? '' : '%' }}</span
                                    ><span class="mt-1 block text-[11px] text-[#706b7d]"
                                        >{{ formatIndexChange(item.points_change) }}{{ indexNumber(item.points_change) === null ? '' : ' pts' }}</span
                                    >
                                </td>
                                <td class="pr-5 text-right tabular-nums">{{ formatIndexNumber(item.price_to_earnings) }}</td>
                                <td class="pr-5 text-right tabular-nums">{{ formatIndexNumber(item.price_to_book) }}</td>
                                <td class="pr-5 text-right tabular-nums">
                                    {{ formatIndexNumber(item.dividend_yield) }}{{ indexNumber(item.dividend_yield) === null ? '' : '%' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul v-if="pageIndices.length" class="divide-y divide-[#eeebf2] md:hidden" aria-label="Matching indices">
                    <li v-for="item in pageIndices" :key="item.slug">
                        <Link
                            :href="indexHref(item.slug)"
                            class="block px-4 py-4 hover:bg-[#faf8fd] focus-visible:outline-2 focus-visible:outline-purple-600"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <span class="min-w-0 text-sm leading-5 font-semibold">{{ item.symbol }}</span>
                                <span class="shrink-0 text-right tabular-nums"
                                    ><span class="block text-sm font-semibold">{{ formatIndexNumber(item.close) }}</span
                                    ><span class="mt-1 block text-xs" :class="indexChangeClass(item.percentage_change)"
                                        >{{ formatIndexChange(item.percentage_change)
                                        }}{{ indexNumber(item.percentage_change) === null ? '' : '%' }}</span
                                    ></span
                                >
                            </div>
                            <div class="mt-3 flex flex-wrap justify-between gap-x-3 gap-y-1 text-[11px] text-[#706b7d] tabular-nums">
                                <span
                                    >P/E <span class="text-[#252135]">{{ formatIndexNumber(item.price_to_earnings) }}</span></span
                                ><span
                                    >P/B <span class="text-[#252135]">{{ formatIndexNumber(item.price_to_book) }}</span></span
                                ><span
                                    >Yield
                                    <span class="text-[#252135]"
                                        >{{ formatIndexNumber(item.dividend_yield) }}{{ indexNumber(item.dividend_yield) === null ? '' : '%' }}</span
                                    ></span
                                >
                            </div>
                        </Link>
                    </li>
                </ul>

                <div v-else class="px-6 py-16 text-center">
                    <p class="font-semibold">No indices match your search.</p>
                    <p class="mt-2 text-sm text-[#706b7d]">Try a shorter name.</p>
                    <button
                        type="button"
                        class="mt-4 min-h-11 cursor-pointer text-sm font-semibold text-[#7515d9] hover:underline"
                        @click="clearSearch"
                    >
                        Clear search
                    </button>
                </div>

                <div
                    v-if="filteredIndices.length"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e5e2ea] px-4 py-3 sm:px-6"
                >
                    <p class="text-xs text-[#706b7d]">
                        {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredIndices.length) }} of
                        {{ filteredIndices.length }} <span class="hidden sm:inline">· — means unavailable</span>
                    </p>
                    <div class="flex items-center gap-3 text-xs">
                        <button
                            type="button"
                            aria-label="Previous results page"
                            :disabled="currentPage === 1"
                            class="min-h-10 cursor-pointer rounded-md border border-[#e5e2ea] px-3 font-medium hover:bg-[#f7f6f9] disabled:cursor-default disabled:opacity-40"
                            @click="changePage(currentPage - 1)"
                        >
                            Previous
                        </button>
                        <span class="text-[#706b7d]">{{ currentPage }} / {{ pageCount }}</span>
                        <button
                            type="button"
                            aria-label="Next results page"
                            :disabled="currentPage === pageCount"
                            class="min-h-10 cursor-pointer rounded-md border border-[#e5e2ea] px-3 font-medium hover:bg-[#f7f6f9] disabled:cursor-default disabled:opacity-40"
                            @click="changePage(currentPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
            <p class="mt-4 text-xs leading-5 text-[#8c8498]">
                Daily changes compare the latest close with the previous session. Indices can share constituents; their moves are not stock breadth.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ArrowUpRightIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { Head, Link, usePage, useRemember } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { NseIndex } from '@/types/app/Models/NseIndex';
import { formatIndexChange, formatIndexNumber, indexChangeClass, indexNumber } from '@/utils/nseIndices';
import type { IndexValue } from '@/utils/nseIndices';

const props = defineProps<{ indices: NseIndex[]; latestDate: string }>();
const sortOptions = [
    { key: 'daily-desc', label: 'Daily move: high to low' },
    { key: 'daily-asc', label: 'Daily move: low to high' },
    { key: 'name-asc', label: 'Name: A–Z' },
    { key: 'name-desc', label: 'Name: Z–A' },
    { key: 'close-desc', label: 'Level: high to low' },
    { key: 'close-asc', label: 'Level: low to high' },
    { key: 'pe-asc', label: 'P/E: low to high' },
    { key: 'pe-desc', label: 'P/E: high to low' },
    { key: 'pb-asc', label: 'P/B: low to high' },
    { key: 'pb-desc', label: 'P/B: high to low' },
    { key: 'yield-desc', label: 'Yield: high to low' },
    { key: 'yield-asc', label: 'Yield: low to high' },
];
const columns = [
    { key: 'name', label: 'Index' },
    { key: 'close', label: 'Level' },
    { key: 'daily', label: 'Daily move' },
    { key: 'pe', label: 'P/E' },
    { key: 'pb', label: 'P/B' },
    { key: 'yield', label: 'Div. yield' },
];
const params = new URLSearchParams(usePage().url.split('?')[1]);
const filters = ref(
    useRemember(
        {
            search: params.get('q') ?? '',
            sort: sortOptions.some((sort) => sort.key === params.get('sort')) ? params.get('sort')! : 'daily-desc',
            page: Math.max(1, Number.parseInt(params.get('page') ?? '1', 10) || 1),
        },
        'IndicesDashboard:filters',
    ),
);
Reflect.deleteProperty(filters.value, 'group');
const pageSize = 20;
const featuredIndices = computed(() =>
    ['nifty-50', 'nifty-bank', 'nifty-midcap-150', 'nifty-smallcap-250'].flatMap((slug) => props.indices.filter((item) => item.slug === slug)),
);
const filteredIndices = computed(() => {
    const query = filters.value.search.trim().toLowerCase();
    const [field, direction] = filters.value.sort.split('-');
    return props.indices
        .filter((item) => !query || item.symbol.toLowerCase().includes(query))
        .sort((a, b) => {
            if (field === 'name') return a.symbol.localeCompare(b.symbol) * (direction === 'asc' ? 1 : -1);
            const values = (item: NseIndex): IndexValue =>
                ({
                    close: item.close,
                    daily: item.percentage_change,
                    pe: item.price_to_earnings,
                    pb: item.price_to_book,
                    yield: item.dividend_yield,
                })[field];
            const first = indexNumber(values(a));
            const second = indexNumber(values(b));
            if (first === null || second === null) return first === second ? a.symbol.localeCompare(b.symbol) : first === null ? 1 : -1;
            return (first - second) * (direction === 'asc' ? 1 : -1) || a.symbol.localeCompare(b.symbol);
        });
});
const pageCount = computed(() => Math.max(1, Math.ceil(filteredIndices.value.length / pageSize)));
const currentPage = computed(() => Math.min(filters.value.page, pageCount.value));
const pageIndices = computed(() => filteredIndices.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));
watch(
    () => [filters.value.search, filters.value.sort],
    () => {
        filters.value.page = 1;
    },
);

function indexHref(slug: string): string {
    const query = new URLSearchParams({
        q: filters.value.search,
        sort: filters.value.sort,
        page: String(currentPage.value),
    });
    return `/nse-index/${slug}?indices=${encodeURIComponent(query.toString())}`;
}
function sortBy(key: string): void {
    filters.value.sort = `${key}-${filters.value.sort === `${key}-asc` ? 'desc' : 'asc'}`;
}
function sortAria(key: string): 'ascending' | 'descending' | 'none' {
    return filters.value.sort.startsWith(`${key}-`) ? (filters.value.sort.endsWith('asc') ? 'ascending' : 'descending') : 'none';
}
function clearSearch(): void {
    filters.value.search = '';
}
function changePage(page: number): void {
    filters.value.page = page;
    document.getElementById('index-comparison-title')?.scrollIntoView({ block: 'start' });
}
</script>
