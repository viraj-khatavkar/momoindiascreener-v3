<template>
    <div>
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
                    Expand all
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

        <!-- Year jump -->
        <div v-if="availableYears.length > 0" class="mb-3 flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-medium text-gray-400">Jump to</span>
            <button
                v-for="year in availableYears"
                :key="year"
                type="button"
                class="cursor-pointer rounded-full border border-gray-300 bg-white px-2.5 py-0.5 text-xs font-medium text-gray-600 hover:border-purple-400 hover:text-purple-700"
                @click="jumpToYear(year)"
            >
                {{ year }}
            </button>
        </div>

        <!-- Grouped by rebalance date -->
        <div class="space-y-2">
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
                                            :class="categorizeReason(trade.reason).chipClass"
                                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ categorizeReason(trade.reason).label }}
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
        </div>

        <p v-if="filteredGroups.length === 0" class="py-8 text-center text-sm text-gray-500">
            {{ trades.length === 0 ? 'This run produced no trades — your filters may exclude every stock in the universe.' : 'No trades match the selected filter.' }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';
import { formatCurrency, formatCurrencyShort, formatDate } from '@/utils/format';

const props = defineProps<{
    trades: BacktestTrade[];
}>();

type TabKey = 'all' | 'buy' | 'sell';
const activeTab = ref<TabKey>('all');
const search = ref('');
const sortOrder = ref<'desc' | 'asc'>('desc');
const activeReasonCategory = ref<string | null>(null);

const expandedGroups = ref<Set<string>>(new Set());

const searchFilteredTrades = computed((): BacktestTrade[] => {
    const query = search.value.trim().toLowerCase();
    if (!query) {
        return props.trades;
    }
    return props.trades.filter(
        (t) => t.symbol.toLowerCase().includes(query) || (t.name ?? '').toLowerCase().includes(query),
    );
});

const tabs = computed(() => [
    { key: 'all' as TabKey, label: 'All', count: searchFilteredTrades.value.length },
    { key: 'buy' as TabKey, label: 'Buys', count: searchFilteredTrades.value.filter((t) => t.trade_type === 'buy').length },
    { key: 'sell' as TabKey, label: 'Sells', count: searchFilteredTrades.value.filter((t) => t.trade_type === 'sell').length },
]);

const tabAndSearchFilteredTrades = computed((): BacktestTrade[] => {
    if (activeTab.value === 'all') {
        return searchFilteredTrades.value;
    }
    return searchFilteredTrades.value.filter((t) => t.trade_type === activeTab.value);
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
];

function categorizeReason(reason: string): ReasonCategory {
    const key = ((): string => {
        // Gold first: rank/filter exits during a gold rotation carry suffixes
        // like '- rotating to gold' and belong to the rotation, not their prefix.
        if (reason.toLowerCase().includes('gold') || reason.startsWith('Index recovered')) return 'gold-rotation';
        if (reason.startsWith('Rank exceeded')) return 'rank-exit';
        if (reason.includes('Cash call')) return 'cash-call';
        if (reason.startsWith('Demerger ex-date')) return 'demerger';
        if (reason.startsWith('Series changed to BE')) return 'be-exit';
        if (reason.startsWith('New entry')) return 'new-entry';
        if (reason.startsWith('Replacement after')) return 'replacement';
        if (reason.startsWith('Weight rebalance adjustment')) return 'rebalance';
        if (reason.startsWith('No volatility data')) return 'rebalance';
        return 'filter-exit';
    })();

    return reasonCategories.find((c) => c.key === key)!;
}

const reasonCategoryCounts = computed((): Array<ReasonCategory & { count: number }> => {
    const counts = new Map<string, number>();
    for (const trade of tabAndSearchFilteredTrades.value) {
        const key = categorizeReason(trade.reason).key;
        counts.set(key, (counts.get(key) ?? 0) + 1);
    }

    return reasonCategories
        .filter((category) => counts.has(category.key))
        .map((category) => ({ ...category, count: counts.get(category.key)! }));
});

// If the active reason category disappears from the current tab+search selection, clear it
// so the user is never stuck on an invisible filter.
watch(reasonCategoryCounts, (categories) => {
    if (activeReasonCategory.value && !categories.some((c) => c.key === activeReasonCategory.value)) {
        activeReasonCategory.value = null;
    }
});

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
    let filtered = tabAndSearchFilteredTrades.value;

    if (activeReasonCategory.value) {
        filtered = filtered.filter((t) => categorizeReason(t.reason).key === activeReasonCategory.value);
    }

    const grouped: Record<string, BacktestTrade[]> = {};
    for (const trade of filtered) {
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

const availableYears = computed((): string[] => {
    const years = new Set<string>();
    for (const group of filteredGroups.value) {
        years.add(group.date.substring(0, 4));
    }
    return [...years].sort((a, b) => b.localeCompare(a));
});

function jumpToYear(year: string): void {
    const target = filteredGroups.value.find((g) => g.date.startsWith(year));
    if (target) {
        document.getElementById('tl-' + target.date)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

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
