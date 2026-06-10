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
                    class="w-44 rounded-md border border-gray-300 bg-white py-1.5 pl-8 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
            </div>

            <div class="ml-auto flex items-center gap-2 text-xs">
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

        <!-- Grouped by rebalance date -->
        <div class="space-y-2">
            <div v-for="group in filteredGroups" :key="group.date" class="rounded-lg border border-gray-200">
                <!-- Group header (clickable) -->
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50"
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
                <div v-if="isExpanded(group.date)" class="overflow-x-auto border-t border-gray-200">
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
                                <td class="max-w-[24rem] truncate px-3 py-2 text-gray-600" :title="trade.reason">{{ trade.reason }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ trade.quantity.toLocaleString('en-IN') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ formatCurrency(trade.raw_price) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-500">{{ formatCurrency(trade.price) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ formatCurrency(trade.gross_amount) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-500">{{ formatCurrency(trade.total_charges) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-900">{{ formatCurrency(trade.net_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <p v-if="filteredGroups.length === 0" class="py-8 text-center text-sm text-gray-500">
            No trades match the selected filter.
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';

const props = defineProps<{
    trades: BacktestTrade[];
}>();

type TabKey = 'all' | 'buy' | 'sell';
const activeTab = ref<TabKey>('all');
const search = ref('');

const expandedGroups = ref<Set<string>>(new Set());

const tabs = computed(() => [
    { key: 'all' as TabKey, label: 'All', count: props.trades.length },
    { key: 'buy' as TabKey, label: 'Buys', count: props.trades.filter((t) => t.trade_type === 'buy').length },
    { key: 'sell' as TabKey, label: 'Sells', count: props.trades.filter((t) => t.trade_type === 'sell').length },
]);

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
    const query = search.value.trim().toLowerCase();

    let filtered = activeTab.value === 'all'
        ? props.trades
        : props.trades.filter((t) => t.trade_type === activeTab.value);

    if (query) {
        filtered = filtered.filter(
            (t) => t.symbol.toLowerCase().includes(query) || (t.name ?? '').toLowerCase().includes(query),
        );
    }

    const grouped: Record<string, BacktestTrade[]> = {};
    for (const trade of filtered) {
        const dateKey = trade.date.substring(0, 10);
        if (!grouped[dateKey]) grouped[dateKey] = [];
        grouped[dateKey].push(trade);
    }

    return Object.keys(grouped)
        .sort()
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
    search.value = '';
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatCurrency(value: number): string {
    return '₹' + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCurrencyShort(value: number): string {
    const v = Number(value);
    const abs = Math.abs(v);
    if (abs >= 10000000) return '₹' + (v / 10000000).toFixed(2) + ' Cr';
    if (abs >= 100000) return '₹' + (v / 100000).toFixed(2) + ' L';
    if (abs >= 1000) return '₹' + (v / 1000).toFixed(1) + ' K';
    return '₹' + v.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}
</script>
