<template>
    <div :aria-busy="isFiltering || pendingFilters">
        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="inline-flex rounded-md border border-gray-300 bg-white">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="cursor-pointer px-4 py-1.5 text-sm font-medium first:rounded-l-md last:rounded-r-md"
                    :class="activeTab === tab.key ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                    :aria-pressed="activeTab === tab.key"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>

            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    type="text"
                    maxlength="100"
                    placeholder="Search symbol..."
                    aria-label="Search trades by symbol"
                    class="w-44 rounded-md border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
            </div>

            <div v-if="reasonCategoryCounts.length > 0" class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="category in reasonCategoryCounts"
                    :key="category.key"
                    type="button"
                    class="cursor-pointer rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="[category.chipClass, activeReasonCategory === category.key ? 'ring-2 ring-purple-500 ring-offset-1' : '']"
                    :aria-pressed="activeReasonCategory === category.key"
                    @click="toggleReasonCategory(category.key)"
                >
                    {{ category.label }} ({{ category.count }})
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2 text-xs">
                <button
                    type="button"
                    class="cursor-pointer font-medium text-purple-600 hover:underline"
                    title="Toggle sort order"
                    @click="sortOrder = sortOrder === 'desc' ? 'asc' : 'desc'"
                >
                    {{ sortOrder === 'desc' ? 'Newest first' : 'Oldest first' }}
                </button>
                <span class="text-gray-300">·</span>
                <button
                    type="button"
                    class="cursor-pointer font-medium text-purple-600 hover:underline"
                    @click="expandAll"
                >
                    Expand loaded
                </button>
                <span class="text-gray-300">·</span>
                <button
                    type="button"
                    class="cursor-pointer font-medium text-purple-600 hover:underline"
                    @click="collapseAll"
                >
                    Collapse all
                </button>
            </div>
        </div>

        <!-- The year filter also finds trades that have not been loaded. -->
        <div v-if="availableYears.length > 0" class="mb-3 flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-medium text-gray-400">Year</span>
            <button
                type="button"
                class="cursor-pointer rounded-full border border-gray-300 bg-white px-2.5 py-0.5 text-xs font-medium text-gray-600 hover:border-purple-400 hover:text-purple-700"
                :class="selectedYear === null ? 'ring-2 ring-purple-500' : ''"
                :aria-pressed="selectedYear === null"
                @click="selectedYear = null"
            >
                All years
            </button>
            <button
                v-for="year in availableYears"
                :key="year"
                type="button"
                class="cursor-pointer rounded-full border border-gray-300 bg-white px-2.5 py-0.5 text-xs font-medium text-gray-600 hover:border-purple-400 hover:text-purple-700"
                :class="selectedYear === year ? 'ring-2 ring-purple-500' : ''"
                :aria-pressed="selectedYear === year"
                @click="selectedYear = year"
            >
                {{ year }}
            </button>
        </div>

        <p class="mb-3 text-xs text-gray-500" role="status" aria-live="polite">
            <template v-if="isFiltering || pendingFilters">Updating trades...</template>
            <template v-else>Loaded {{ trades.data.length }} of {{ trades.total }} matching trades. Date totals include loaded trades.</template>
        </p>

        <!-- Grouped by trade date; more rows for the same date join the existing group. -->
        <InfiniteScroll data="trades" only-next preserve-url :buffer="200" :manual="isFiltering || pendingFilters" class="space-y-2">
            <div v-for="group in filteredGroups" :id="'tl-' + group.date" :key="group.date" class="scroll-mt-28 rounded-lg border border-gray-200">
                <!-- Group header (clickable) -->
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50"
                    :aria-expanded="isExpanded(group.date)"
                    :aria-controls="'tl-panel-' + group.date"
                    @click="toggleGroup(group.date)"
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-semibold text-gray-900">{{ formatDate(group.date) }}</span>
                        <span v-if="group.buyCount > 0" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                            {{ group.buyCount }} {{ group.buyCount === 1 ? 'buy' : 'buys' }}
                        </span>
                        <span v-if="group.sellCount > 0" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                            {{ group.sellCount }} {{ group.sellCount === 1 ? 'sell' : 'sells' }}
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="hidden text-xs text-gray-500 sm:inline">
                            <template v-if="group.buyGross > 0">↑ {{ formatCurrencyShort(group.buyGross) }} bought</template>
                            <template v-if="group.buyGross > 0 && group.sellGross > 0"> · </template>
                            <template v-if="group.sellGross > 0">↓ {{ formatCurrencyShort(group.sellGross) }} sold</template>
                            <template v-if="group.charges > 0"> · {{ formatCurrencyShort(group.charges) }} fees</template>
                        </span>
                        <svg
                            class="h-5 w-5 text-gray-400 transition-transform"
                            :class="isExpanded(group.date) ? 'rotate-180' : ''"
                            fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </div>
                </button>

                <!-- Expanded trade rows -->
                <div v-if="isExpanded(group.date)" :id="'tl-panel-' + group.date" class="overflow-x-auto border-t border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Symbol</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Type</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Reason</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Qty</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Raw Price</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Adj. Price</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Gross</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Charges</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Net</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">P&amp;L</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="trade in group.trades" :key="trade.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-3 py-2">
                                    <a
                                        :href="`/instruments/${trade.symbol}`"
                                        target="_blank"
                                        class="font-medium text-gray-900 hover:text-purple-700 hover:underline"
                                    >
                                        {{ trade.symbol }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span
                                        :class="trade.trade_type === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ trade.trade_type }}
                                    </span>
                                </td>
                                <td class="max-w-[24rem] px-3 py-2 text-gray-600" :title="trade.reason">
                                    <div class="flex items-center gap-2">
                                        <span
                                            :class="categorizeReason(trade.reason_category).chipClass"
                                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ categorizeReason(trade.reason_category).label }}
                                        </span>
                                        <span class="min-w-0 truncate">{{ trade.reason }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ trade.quantity.toLocaleString('en-IN') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ formatCurrency(trade.raw_price) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-500">{{ formatCurrency(trade.price) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ formatCurrency(trade.gross_amount) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-500">{{ formatCurrency(trade.total_charges) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-900">{{ formatCurrency(trade.net_amount) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <template v-if="trade.trade_type === 'sell' && trade.realized_pnl !== null">
                                        <div class="font-semibold tabular-nums" :class="pnlColorClass(trade.realized_pnl)">
                                            {{ formatSignedPnl(trade.realized_pnl) }}
                                        </div>
                                        <div v-if="trade.realized_pnl_pct !== null" class="text-xs tabular-nums" :class="pnlColorClass(trade.realized_pnl)">
                                            {{ formatSignedPnlPct(trade.realized_pnl_pct) }}
                                        </div>
                                    </template>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <template #next="{ loading, hasMore, fetch }">
                <div class="py-4 text-center text-sm text-gray-500">
                    <p v-if="loading" role="status">Loading more trades...</p>
                    <button
                        v-else-if="hasMore"
                        type="button"
                        class="cursor-pointer font-medium text-purple-600 hover:underline disabled:cursor-wait"
                        :disabled="isFiltering || pendingFilters"
                        @click="fetch()"
                    >
                        Load more trades
                    </button>
                    <p v-else-if="trades.total > 0">All matching trades are loaded.</p>
                </div>
            </template>
        </InfiniteScroll>

        <p v-if="filteredGroups.length === 0" class="py-8 text-center text-sm text-gray-500">
            {{ summary.total === 0 ? 'This run produced no trades — your filters may exclude every stock in the universe.' : 'No trades match the selected filter.' }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { InfiniteScroll, router } from '@inertiajs/vue3';
import type { CancelToken } from '@inertiajs/core';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';
import type { PaginatedBacktestTrades, TradeLogFilters, TradeLogSummary } from '@/types/BacktestTradeLog';
import { formatCurrency, formatCurrencyShort, formatDate } from '@/utils/format';

const props = defineProps<{
    trades: PaginatedBacktestTrades;
    summary: TradeLogSummary;
    filters: TradeLogFilters;
}>();

type TabKey = 'all' | 'buy' | 'sell';
const activeTab = ref<TabKey>(props.filters.type);
const search = ref(props.filters.search);
const sortOrder = ref<'desc' | 'asc'>(props.filters.sort);
const activeReasonCategory = ref<string | null>(props.filters.reason);
const selectedYear = ref<number | null>(props.filters.year);
const isFiltering = ref(false);
const pendingFilters = ref(false);
let isLoadingPage = false;
let filterTimer: ReturnType<typeof setTimeout> | undefined;
let filterCancelToken: CancelToken | undefined;
let disposed = false;

const expandedGroups = ref<Set<string>>(new Set());

const tabs = computed(() => [
    { key: 'all' as TabKey, label: 'All', count: props.summary.counts.all },
    { key: 'buy' as TabKey, label: 'Buys', count: props.summary.counts.buy },
    { key: 'sell' as TabKey, label: 'Sells', count: props.summary.counts.sell },
]);

function loadFilters(): void {
    if (disposed || !pendingFilters.value || isLoadingPage || isFiltering.value || filterTimer !== undefined) {
        return;
    }

    pendingFilters.value = false;
    isFiltering.value = true;
    expandedGroups.value = new Set();

    const url = new URL(window.location.href);
    const parameters = {
        trade_search: search.value.trim(),
        trade_type: activeTab.value,
        trade_reason: activeReasonCategory.value,
        trade_sort: sortOrder.value,
        trade_year: selectedYear.value,
    };
    url.searchParams.delete('trades_page');
    for (const [key, value] of Object.entries(parameters)) {
        if (value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    router.get(url.pathname + url.search, {}, {
        only: ['trades', 'tradeLogSummary', 'tradeFilters'],
        reset: ['trades'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onCancelToken: (token) => { filterCancelToken = token; },
        onFinish: () => {
            isFiltering.value = false;
            filterCancelToken = undefined;
            loadFilters();
        },
    });
}

watch([search, activeTab, activeReasonCategory, sortOrder, selectedYear], (values, previous) => {
    pendingFilters.value = true;
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        filterTimer = undefined;
        loadFilters();
    }, values[0] !== previous[0] ? 300 : 0);
});

/** Finish an active scroll request before resetting its filters, so its old rows cannot merge into the new result. */
const removeStartListener = router.on('start', (event) => {
    if (event.detail.visit.only.length === 1 && event.detail.visit.only[0] === 'trades') {
        isLoadingPage = true;
    }
});
const removeFinishListener = router.on('finish', (event) => {
    if (event.detail.visit.only.length === 1 && event.detail.visit.only[0] === 'trades') {
        isLoadingPage = false;
        loadFilters();
    }
});

onUnmounted(() => {
    disposed = true;
    clearTimeout(filterTimer);
    filterCancelToken?.cancel();
    removeStartListener();
    removeFinishListener();
});

interface ReasonCategory {
    key: string;
    label: string;
    chipClass: string;
}

const reasonCategories: ReasonCategory[] = [
    { key: 'rank-exit', label: 'Rank exit', chipClass: 'bg-gray-100 text-gray-700' },
    { key: 'cash-call', label: 'Cash call', chipClass: 'bg-amber-100 text-amber-700' },
    { key: 'demerger', label: 'Demerger', chipClass: 'bg-violet-100 text-violet-700' },
    { key: 'be-exit', label: 'BE exit', chipClass: 'bg-orange-100 text-orange-700' },
    { key: 'gold-rotation', label: 'Gold rotation', chipClass: 'bg-yellow-100 text-yellow-800' },
    { key: 'new-entry', label: 'New entry', chipClass: 'bg-green-50 text-green-700' },
    { key: 'replacement', label: 'Replacement', chipClass: 'bg-blue-100 text-blue-700' },
    { key: 'rebalance', label: 'Rebalance', chipClass: 'bg-sky-100 text-sky-700' },
    { key: 'filter-exit', label: 'Filter exit', chipClass: 'bg-rose-100 text-rose-700' },
    { key: 'stop-loss', label: 'Stop loss', chipClass: 'bg-red-100 text-red-700' },
    { key: 'assumed-delisting', label: 'Assumed delisting', chipClass: 'bg-orange-100 text-orange-800' },
];

function categorizeReason(key: string): ReasonCategory {
    return reasonCategories.find((category) => category.key === key) ?? reasonCategories.find((category) => category.key === 'filter-exit')!;
}

const reasonCategoryCounts = computed((): Array<ReasonCategory & { count: number }> => reasonCategories
    .filter((category) => (props.summary.reasons[category.key] ?? 0) > 0 || activeReasonCategory.value === category.key)
    .map((category) => ({ ...category, count: props.summary.reasons[category.key] ?? 0 })));

function toggleReasonCategory(key: string): void {
    activeReasonCategory.value = activeReasonCategory.value === key ? null : key;
}

interface TradeGroup {
    date: string;
    buyCount: number;
    sellCount: number;
    buyGross: number;
    sellGross: number;
    charges: number;
    trades: BacktestTrade[];
}

const filteredGroups = computed((): TradeGroup[] => {
    const grouped: Record<string, BacktestTrade[]> = {};
    for (const trade of props.trades.data) {
        const dateKey = trade.date.substring(0, 10);
        if (!grouped[dateKey]) grouped[dateKey] = [];
        grouped[dateKey].push(trade);
    }

    return Object.keys(grouped)
        .sort((a, b) => (sortOrder.value === 'desc' ? b.localeCompare(a) : a.localeCompare(b)))
        .map((date) => {
            const buys = grouped[date].filter((t) => t.trade_type === 'buy');
            const sells = grouped[date].filter((t) => t.trade_type === 'sell');

            return {
                date,
                buyCount: buys.length,
                sellCount: sells.length,
                buyGross: buys.reduce((sum, t) => sum + Number(t.gross_amount), 0),
                sellGross: sells.reduce((sum, t) => sum + Number(t.gross_amount), 0),
                charges: grouped[date].reduce((sum, t) => sum + Number(t.total_charges), 0),
                trades: grouped[date],
            };
        });
});

const availableYears = computed(() => props.summary.years);

// While searching, every matching group is auto-expanded so results are visible.
function isExpanded(date: string): boolean {
    if (search.value.trim()) {
        return true;
    }
    return expandedGroups.value.has(date);
}

function toggleGroup(date: string): void {
    const next = new Set(expandedGroups.value);
    if (next.has(date)) {
        next.delete(date);
    } else {
        next.add(date);
    }
    expandedGroups.value = next;
}

function expandAll(): void {
    expandedGroups.value = new Set(filteredGroups.value.map((g) => g.date));
}

function collapseAll(): void {
    expandedGroups.value = new Set();
}

function pnlColorClass(value: number | string): string {
    return Number(value) >= 0 ? 'text-green-700' : 'text-red-700';
}

function formatSignedPnl(value: number | string): string {
    const v = Number(value);
    return v >= 0 ? '+' + formatCurrencyShort(v) : formatCurrencyShort(v);
}

/** realized_pnl_pct arrives as a percent (12.34), not a fraction — format directly. */
function formatSignedPnlPct(value: number | string): string {
    const v = Number(value);
    return (v >= 0 ? '+' : '') + v.toFixed(2) + '%';
}
</script>
