<template>
    <div>
        <!-- Filter tabs -->
        <div class="mb-4 inline-flex rounded-md border border-gray-300 bg-white">
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

        <!-- Grouped by rebalance date -->
        <div class="space-y-2">
            <div v-for="group in filteredGroups" :key="group.date" class="rounded-lg border border-gray-200">
                <!-- Group header (clickable) -->
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between px-4 py-3 text-left hover:bg-gray-50"
                    @click="toggleGroup(group.date)"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-gray-900">{{ formatDate(group.date) }}</span>
                        <span v-if="group.buyCount > 0" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                            {{ group.buyCount }} {{ group.buyCount === 1 ? 'buy' : 'buys' }}
                        </span>
                        <span v-if="group.sellCount > 0" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                            {{ group.sellCount }} {{ group.sellCount === 1 ? 'sell' : 'sells' }}
                        </span>
                    </div>
                    <svg
                        class="h-5 w-5 text-gray-400 transition-transform"
                        :class="expandedGroups.has(group.date) ? 'rotate-180' : ''"
                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <!-- Expanded trade rows -->
                <div v-if="expandedGroups.has(group.date)" class="border-t border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Symbol</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Type</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Reason</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Qty</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Price</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Gross</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Charges</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-500">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="trade in group.trades" :key="trade.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900">{{ trade.symbol }}</td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <span
                                        :class="trade.trade_type === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ trade.trade_type }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-600">{{ trade.reason }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ trade.quantity.toLocaleString('en-IN') }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-900">{{ formatCurrency(trade.raw_price) }}</td>
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
import { computed, reactive, ref } from 'vue';
import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';

const props = defineProps<{
    trades: BacktestTrade[];
}>();

type TabKey = 'all' | 'buy' | 'sell';
const activeTab = ref<TabKey>('all');

const expandedGroups = reactive(new Set<string>());

const tabs = computed(() => [
    { key: 'all' as TabKey, label: 'All', count: props.trades.length },
    { key: 'buy' as TabKey, label: 'Buys', count: props.trades.filter((t) => t.trade_type === 'buy').length },
    { key: 'sell' as TabKey, label: 'Sells', count: props.trades.filter((t) => t.trade_type === 'sell').length },
]);

interface TradeGroup {
    date: string;
    buyCount: number;
    sellCount: number;
    trades: BacktestTrade[];
}

const filteredGroups = computed((): TradeGroup[] => {
    const filtered = activeTab.value === 'all'
        ? props.trades
        : props.trades.filter((t) => t.trade_type === activeTab.value);

    const grouped: Record<string, BacktestTrade[]> = {};
    for (const trade of filtered) {
        const dateKey = trade.date.substring(0, 10);
        if (!grouped[dateKey]) grouped[dateKey] = [];
        grouped[dateKey].push(trade);
    }

    return Object.keys(grouped)
        .sort()
        .map((date) => ({
            date,
            buyCount: grouped[date].filter((t) => t.trade_type === 'buy').length,
            sellCount: grouped[date].filter((t) => t.trade_type === 'sell').length,
            trades: grouped[date],
        }));
});

function toggleGroup(date: string): void {
    if (expandedGroups.has(date)) {
        expandedGroups.delete(date);
    } else {
        expandedGroups.add(date);
    }
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatCurrency(value: number): string {
    return '₹' + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
