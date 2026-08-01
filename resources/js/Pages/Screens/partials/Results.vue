<template>
    <div class="relative mt-10" :aria-busy="loading || undefined">
        <!-- Results Toolbar -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600">
                <span>
                    <span class="font-semibold text-gray-900">
                        {{ displayedRows.length === results.length ? results.length : `${displayedRows.length} of ${results.length}` }}
                    </span>
                    {{ results.length === 1 ? 'stock' : 'stocks' }}
                </span>
                <template v-if="results.length > 0">
                    <span class="text-gray-300">·</span>
                    <span>as of {{ formatDate(results[0].date) }}</span>
                </template>
                <span class="text-gray-300">·</span>
                <span v-if="isMultiFactor">
                    combined rank of
                    {{ factorColumns.map((factor) => screenSortByDisplayName(factor.key)).join(' + ') }}
                </span>
                <span v-else>
                    sorted by {{ screenSortByDisplayName(screen.sort_by) }}
                    {{ screen.sort_direction === 'asc' ? '↑' : '↓' }}
                </span>
                <span
                    v-if="screen.apply_historical_date"
                    class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-600/20 ring-inset"
                >
                    Historical date
                </span>
                <button
                    v-if="clientSort"
                    type="button"
                    class="cursor-pointer font-semibold text-purple-600 hover:text-purple-500"
                    @click="clientSort = null"
                >
                    Reset custom sort
                </button>
            </div>

            <div class="ms-auto flex flex-wrap items-center gap-3">
                <div v-if="results.length > 0" class="relative">
                    <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        name="results_search"
                        placeholder="Find symbol or name"
                        class="w-44 rounded-md bg-white py-1.5 pr-3 pl-8 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:w-56"
                    />
                </div>
                <template v-if="showActions">
                    <button
                        v-if="availableColumns"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                        @click="columnsPanelOpen = true"
                    >
                        <TableCellsIcon class="h-4 w-4 text-gray-400" />
                        Edit Columns
                    </button>
                    <a
                        :href="`/screens/${screen.id}/csv`"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4 text-gray-400" />
                        Export
                    </a>
                </template>
            </div>
        </div>

        <!-- No results at all -->
        <div v-if="results.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center">
            <FunnelIcon class="mx-auto h-10 w-10 text-gray-400" />
            <p class="mt-3 text-sm font-semibold text-gray-900">No stocks matched your filters</p>
            <p class="mt-1 text-sm text-gray-500">Try relaxing or removing some filters above, then apply again.</p>
        </div>

        <!-- Search matched nothing -->
        <div v-else-if="displayedRows.length === 0" class="mt-4 rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center">
            <MagnifyingGlassIcon class="mx-auto h-10 w-10 text-gray-400" />
            <p class="mt-3 text-sm font-semibold text-gray-900">No stocks match “{{ searchQuery }}”</p>
            <button type="button" class="mt-1 cursor-pointer text-sm font-semibold text-purple-600 hover:text-purple-500" @click="searchQuery = ''">
                Clear search
            </button>
        </div>

        <template v-else>
            <!-- Desktop Grid -->
            <div class="mt-4 hidden md:block">
                <div class="max-h-[calc(100dvh-8rem)] overflow-auto rounded-lg border border-gray-300">
                    <table class="min-w-full border-separate border-spacing-0">
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    class="sticky top-0 left-0 z-30 w-14 min-w-14 border-b border-gray-300 bg-gray-50 px-3 py-3 text-left text-sm font-semibold text-gray-900"
                                >
                                    <button
                                        type="button"
                                        class="cursor-pointer"
                                        title="Screen rank — click to restore screen order"
                                        @click="clientSort = null"
                                    >
                                        #
                                    </button>
                                </th>
                                <th
                                    scope="col"
                                    class="sticky top-0 left-14 z-30 border-r border-b border-gray-300 bg-gray-50 px-3 py-3 text-left text-sm font-semibold text-gray-900"
                                    :aria-sort="ariaSort('symbol')"
                                >
                                    <SortHeaderButton :direction="sortDirectionFor('symbol')" @toggle="toggleSort('symbol')">
                                        Stock
                                    </SortHeaderButton>
                                </th>
                                <th
                                    v-for="factor in factorColumns"
                                    :key="factor.rankKey"
                                    scope="col"
                                    class="sticky top-0 z-20 border-b border-gray-300 bg-gray-50 px-3 py-3 text-right text-sm font-semibold whitespace-nowrap text-gray-900"
                                    :aria-sort="ariaSort(factor.key)"
                                >
                                    <SortHeaderButton align="right" :direction="sortDirectionFor(factor.key)" @toggle="toggleSort(factor.key)">
                                        {{ screenSortByDisplayName(factor.key) }}
                                        <span class="font-normal text-gray-400">
                                            {{ factor.direction === 'asc' ? '↑' : '↓' }}
                                        </span>
                                    </SortHeaderButton>
                                </th>
                                <th
                                    v-for="column in visibleColumns"
                                    :key="column.name"
                                    scope="col"
                                    class="sticky top-0 z-20 border-b border-gray-300 bg-gray-50 px-3 py-3 text-sm font-semibold whitespace-nowrap text-gray-900"
                                    :class="screenColumnAlignClass(column.name)"
                                    :aria-sort="ariaSort(column.name)"
                                >
                                    <SortHeaderButton
                                        :align="isTextScreenColumn(column.name) ? 'left' : 'right'"
                                        :direction="sortDirectionFor(column.name)"
                                        @toggle="toggleSort(column.name)"
                                    >
                                        {{ column.display_name }}
                                    </SortHeaderButton>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in displayedRows" :key="row.item.id" class="group hover:bg-gray-50">
                                <td
                                    class="sticky left-0 z-10 w-14 min-w-14 border-b border-gray-200 bg-white px-3 py-2.5 text-sm whitespace-nowrap text-gray-500 tabular-nums group-last:border-b-0 group-hover:bg-gray-50"
                                >
                                    {{ row.screenRank }}
                                </td>
                                <td
                                    class="sticky left-14 z-10 border-r border-b border-gray-200 bg-white px-3 py-2.5 whitespace-nowrap group-last:border-b-0 group-hover:bg-gray-50"
                                >
                                    <Link
                                        :href="`/instruments/${row.item.symbol}`"
                                        class="block max-w-44 cursor-pointer text-gray-900 hover:text-purple-600"
                                    >
                                        <span class="block truncate text-sm font-semibold">
                                            {{ row.item.symbol }}
                                        </span>
                                        <span class="block truncate text-xs text-gray-500">
                                            {{ row.item.name }}
                                        </span>
                                    </Link>
                                </td>
                                <td
                                    v-for="factor in factorColumns"
                                    :key="`${row.item.id}-${factor.rankKey}`"
                                    class="border-b border-gray-200 px-3 py-2.5 text-right text-sm whitespace-nowrap tabular-nums group-last:border-b-0"
                                    :class="screenCellClass(factor.key, row.item[factor.key])"
                                >
                                    {{ formatScreenCellValue(factor.key, row.item[factor.key]) }}
                                    <span v-if="isMultiFactor && row.item[factor.rankKey] != null" class="ml-1 text-xs text-gray-400">
                                        #{{ row.item[factor.rankKey] }}
                                    </span>
                                </td>
                                <td
                                    v-for="column in visibleColumns"
                                    :key="`${row.item.id}-${column.name}`"
                                    class="border-b border-gray-200 px-3 py-2.5 text-sm whitespace-nowrap tabular-nums group-last:border-b-0"
                                    :class="[screenColumnAlignClass(column.name), screenCellClass(column.name, row.item[column.name])]"
                                >
                                    {{ formatScreenCellValue(column.name, row.item[column.name]) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile View -->
            <div class="mt-4 md:hidden">
                <Disclosure v-for="row in displayedRows" :key="row.item.id" v-slot="{ open }" as="div" class="border-b border-gray-200">
                    <DisclosureButton class="flex w-full cursor-pointer items-center gap-3 py-3 text-left">
                        <span class="w-7 shrink-0 text-sm text-gray-400 tabular-nums">
                            {{ row.screenRank }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-gray-900">
                                {{ row.item.symbol }}
                            </span>
                            <span class="block truncate text-xs text-gray-500">
                                {{ row.item.name }}
                            </span>
                        </span>
                        <span class="shrink-0 text-sm tabular-nums" :class="screenCellClass(screen.sort_by, row.item[screen.sort_by])">
                            {{ formatScreenCellValue(screen.sort_by, row.item[screen.sort_by]) }}
                        </span>
                        <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 shrink-0 text-gray-400']" />
                    </DisclosureButton>
                    <DisclosurePanel class="pb-4 pl-10">
                        <div class="flex flex-col gap-1">
                            <template v-if="isMultiFactor">
                                <div v-for="factor in factorColumns" :key="factor.rankKey" class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-gray-500">
                                        {{ screenSortByDisplayName(factor.key) }}
                                    </span>
                                    <span class="text-sm tabular-nums" :class="screenCellClass(factor.key, row.item[factor.key])">
                                        {{ formatScreenCellValue(factor.key, row.item[factor.key]) }}
                                        <span v-if="row.item[factor.rankKey] != null" class="text-xs text-gray-400">
                                            #{{ row.item[factor.rankKey] }}
                                        </span>
                                    </span>
                                </div>
                            </template>
                            <div v-for="column in visibleColumns" :key="column.name" class="flex items-center justify-between gap-4">
                                <span class="text-sm text-gray-500">{{ column.display_name }}</span>
                                <span class="text-sm tabular-nums" :class="screenCellClass(column.name, row.item[column.name])">
                                    {{ formatScreenCellValue(column.name, row.item[column.name]) }}
                                </span>
                            </div>
                        </div>
                        <Link
                            :href="`/instruments/${row.item.symbol}`"
                            class="mt-3 inline-flex items-center text-sm font-semibold text-purple-600 hover:text-purple-500"
                        >
                            View {{ row.item.symbol }} details →
                        </Link>
                    </DisclosurePanel>
                </Disclosure>
            </div>
        </template>

        <!-- Loading overlay (last in DOM so it paints above the sticky grid header) -->
        <div v-if="loading" class="absolute inset-0 z-30 flex items-start justify-center rounded-lg bg-white/60">
            <div class="mt-24 h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-purple-600" />
        </div>

        <ColumnsPanel
            v-if="availableColumns"
            :open="columnsPanelOpen"
            :screen="screen"
            :available-columns="availableColumns"
            @close="columnsPanelOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ArrowDownTrayIcon, ChevronDownIcon, MagnifyingGlassIcon, TableCellsIcon } from '@heroicons/vue/20/solid';
import { FunnelIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import ColumnsPanel from '@/Pages/Screens/partials/ColumnsPanel.vue';
import SortHeaderButton from '@/Pages/Screens/partials/SortHeaderButton.vue';
import { formatDate } from '@/utils/format';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { formatScreenCellValue, isTextScreenColumn, screenCellClass, screenCellSortValue, screenColumnAlignClass } from '@/utils/screenColumnFormat';
import type { Screen } from '@/types/app/Models/Screen';
import type { BacktestNseInstrumentPriceResource } from '@/types/app/Resources/BacktestNseInstrumentPriceResource';
import type { ScreenColumnGroup } from '@/types/ScreenColumnGroup';
import type { ScreenResultColumn } from '@/types/ScreenResultColumn';

type ResultItem = BacktestNseInstrumentPriceResource & Record<string, any>;

interface ResultRow {
    item: ResultItem;
    screenRank: number;
}

interface FactorColumn {
    key: string;
    direction: string;
    rankKey: string;
}

const props = withDefaults(
    defineProps<{
        screen: Screen;
        results: ResultItem[];
        columns: ScreenResultColumn[];
        availableColumns?: ScreenColumnGroup[];
        showActions?: boolean;
        loading?: boolean;
    }>(),
    {
        showActions: true,
        loading: false,
    },
);

const isMultiFactor = computed(() => props.screen.apply_factor_two || props.screen.apply_factor_three);

const factorColumns = computed<FactorColumn[]>(() => {
    const factors: FactorColumn[] = [
        {
            key: props.screen.sort_by,
            direction: props.screen.sort_direction,
            rankKey: 'factor_one_rank',
        },
    ];

    if (props.screen.apply_factor_two) {
        factors.push({
            key: props.screen.factor_two_sort_by,
            direction: props.screen.factor_two_sort_direction,
            rankKey: 'factor_two_rank',
        });
    }

    if (props.screen.apply_factor_three) {
        factors.push({
            key: props.screen.factor_three_sort_by,
            direction: props.screen.factor_three_sort_direction,
            rankKey: 'factor_three_rank',
        });
    }

    return factors;
});

const visibleColumns = computed(() => props.columns.filter((column) => !factorColumns.value.some((factor) => factor.key === column.name)));

const searchQuery = ref('');
const clientSort = ref<{ column: string; direction: 'asc' | 'desc' } | null>(null);
const columnsPanelOpen = ref(false);

const rankedRows = computed<ResultRow[]>(() => props.results.map((item, index) => ({ item, screenRank: index + 1 })));

const displayedRows = computed<ResultRow[]>(() => {
    let rows = rankedRows.value;

    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        rows = rows.filter(({ item }) => item.symbol?.toLowerCase().includes(query) || item.name?.toLowerCase().includes(query));
    }

    const sort = clientSort.value;
    if (sort) {
        const factor = sort.direction === 'asc' ? 1 : -1;

        rows = [...rows].sort((a, b) => {
            const aValue = screenCellSortValue(a.item[sort.column]);
            const bValue = screenCellSortValue(b.item[sort.column]);

            if (aValue === null && bValue === null) {
                return 0;
            }
            if (aValue === null) {
                return 1;
            }
            if (bValue === null) {
                return -1;
            }
            if (typeof aValue === 'number' && typeof bValue === 'number') {
                return (aValue - bValue) * factor;
            }

            return String(aValue).localeCompare(String(bValue)) * factor;
        });
    }

    return rows;
});

function sortDirectionFor(column: string): 'asc' | 'desc' | null {
    return clientSort.value?.column === column ? clientSort.value.direction : null;
}

function ariaSort(column: string): 'ascending' | 'descending' | undefined {
    const direction = sortDirectionFor(column);

    if (direction === null) {
        return undefined;
    }

    return direction === 'asc' ? 'ascending' : 'descending';
}

function toggleSort(column: string): void {
    const firstDirection: 'asc' | 'desc' = isTextScreenColumn(column) ? 'asc' : 'desc';

    if (clientSort.value?.column !== column) {
        clientSort.value = { column, direction: firstDirection };
        return;
    }

    if (clientSort.value.direction === firstDirection) {
        clientSort.value = {
            column,
            direction: firstDirection === 'desc' ? 'asc' : 'desc',
        };
        return;
    }

    clientSort.value = null;
}
</script>
