<template>
    <div>
        <Head title="Backtests" />

        <div class="flex items-start justify-between">
            <PageHeader description="Create and manage your portfolio backtests">
                Backtests
            </PageHeader>
            <Link
                href="/backtests/create"
                class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
            >
                Create New Backtest
            </Link>
        </div>

        <!-- Search + Status Filters -->
        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search backtests..."
                    class="w-64 rounded-lg border-gray-300 py-2 pl-9 pr-4 text-sm shadow-xs focus:border-purple-500 focus:ring-purple-500"
                />
            </div>
            <div class="flex gap-1 rounded-lg bg-gray-100 p-1">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    @click="activeStatus = tab.value"
                    :class="[
                        'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                        activeStatus === tab.value
                            ? 'bg-white text-gray-900 shadow-xs'
                            : 'text-gray-600 hover:text-gray-900',
                    ]"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-gray-400">{{ tab.count }}</span>
                </button>
            </div>
        </div>

        <!-- Table -->
        <div v-if="sortedBacktests.length > 0" class="mt-4 overflow-x-auto rounded-xl bg-white shadow-xs ring-1 ring-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            @click="col.sortable ? toggleSort(col.key) : undefined"
                            :class="[
                                'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500',
                                col.align === 'right' ? 'text-right' : 'text-left',
                                col.sortable ? 'cursor-pointer select-none hover:text-gray-700' : '',
                            ]"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ col.label }}
                                <template v-if="col.sortable && sortKey === col.key">
                                    <ChevronUpIcon v-if="sortDir === 'asc'" class="h-3 w-3" />
                                    <ChevronDownIcon v-else class="h-3 w-3" />
                                </template>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="bt in sortedBacktests" :key="bt.id">
                        <!-- Main row -->
                        <tr
                            class="group cursor-pointer transition-colors hover:bg-purple-50/50"
                            @click="toggleExpand(bt.id)"
                        >
                            <!-- Name -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <ChevronRightIcon
                                        class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform duration-150"
                                        :class="expandedRows.has(bt.id) ? 'rotate-90 text-purple-500' : ''"
                                    />
                                    <Link
                                        :href="`/backtests/${bt.id}`"
                                        class="font-medium text-gray-900 hover:text-purple-700 hover:underline"
                                        @click.stop
                                    >
                                        {{ bt.name }}
                                    </Link>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    :class="statusBadgeClass(bt.status)"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                >
                                    {{ bt.status }}
                                </span>
                                <div v-if="bt.status === 'running'" class="mt-1 h-1 w-16 rounded-full bg-gray-200">
                                    <div class="h-1 rounded-full bg-blue-500 transition-all duration-300" :style="`width: ${bt.progress}%`" />
                                </div>
                            </td>

                            <!-- CAGR -->
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold" :class="cagrColor(bt)">
                                {{ formatMetric(bt, 'cagr') }}
                            </td>

                            <!-- Max Drawdown -->
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-red-700">
                                {{ formatMetric(bt, 'max_drawdown') }}
                            </td>

                            <!-- Final Value -->
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">
                                {{ bt.summary_metrics ? formatCurrencyShort(bt.summary_metrics.final_value) : '—' }}
                            </td>

                            <!-- Trades -->
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                {{ bt.summary_metrics?.total_trades ?? '—' }}
                            </td>

                            <!-- Rebalance -->
                            <td class="whitespace-nowrap px-4 py-3 text-sm capitalize text-gray-600">
                                {{ bt.rebalance_frequency }}
                            </td>

                            <!-- Max Stocks -->
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-600">
                                {{ bt.max_stocks_to_hold }}
                            </td>

                            <!-- Created -->
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ formatRelativeDate(bt.created_at) }}
                            </td>
                        </tr>

                        <!-- Expanded config row -->
                        <tr v-if="expandedRows.has(bt.id)">
                            <td :colspan="columns.length" class="bg-gray-50/80 px-6 py-4">
                                <BacktestStrategyRules :backtest="bt" />
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty States -->
        <div v-else class="mt-4 rounded-xl bg-white p-12 text-center shadow-xs ring-1 ring-gray-200">
            <template v-if="backtests.length === 0">
                <h3 class="text-lg font-semibold text-gray-900">No backtests yet</h3>
                <p class="mt-2 text-sm text-gray-500">Create your first backtest to get started</p>
                <Link
                    href="/backtests/create"
                    class="mt-4 inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-purple-500"
                >
                    Create New Backtest
                </Link>
            </template>
            <template v-else>
                <p class="text-sm text-gray-500">No backtests match your filters</p>
            </template>
        </div>

        <!-- Count -->
        <p v-if="sortedBacktests.length > 0" class="mt-3 text-xs text-gray-500">
            Showing {{ sortedBacktests.length }} of {{ backtests.length }} backtests
        </p>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { MagnifyingGlassIcon, ChevronUpIcon, ChevronDownIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';
import PageHeader from '@/Components/PageHeader.vue';
import BacktestStrategyRules from '@/Pages/Backtests/partials/BacktestStrategyRules.vue';
import type { Backtest } from '@/types/app/Models/Backtest';
import type { BacktestSummaryMetric } from '@/types/app/Models/BacktestSummaryMetric';

type BacktestWithMetrics = Backtest & {
    summary_metrics: Pick<BacktestSummaryMetric, 'cagr' | 'max_drawdown' | 'total_trades' | 'total_charges_paid' | 'final_value'> | null;
};

const props = defineProps<{
    backtests: BacktestWithMetrics[];
}>();

// --- Search & Filter ---

const search = ref('');
const activeStatus = ref('all');

const statusTabs = computed(() => {
    const counts: Record<string, number> = { all: props.backtests.length, completed: 0, running: 0, pending: 0, failed: 0 };
    for (const bt of props.backtests) {
        counts[bt.status]++;
    }
    return [
        { label: 'All', value: 'all', count: counts.all },
        { label: 'Completed', value: 'completed', count: counts.completed },
        { label: 'Running', value: 'running', count: counts.running },
        { label: 'Pending', value: 'pending', count: counts.pending },
        { label: 'Failed', value: 'failed', count: counts.failed },
    ];
});

const filteredBacktests = computed(() => {
    let list = props.backtests;
    if (activeStatus.value !== 'all') {
        list = list.filter((bt) => bt.status === activeStatus.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter((bt) => bt.name.toLowerCase().includes(q));
    }
    return list;
});

// --- Expand/Collapse ---

const expandedRows = reactive(new Set<number>());

function toggleExpand(id: number): void {
    if (expandedRows.has(id)) {
        expandedRows.delete(id);
    } else {
        expandedRows.add(id);
    }
}

// --- Sorting ---

type SortKey = 'name' | 'status' | 'cagr' | 'max_drawdown' | 'final_value' | 'total_trades' | 'rebalance_frequency' | 'max_stocks_to_hold' | 'created_at';

const sortKey = ref<SortKey>('created_at');
const sortDir = ref<'asc' | 'desc'>('desc');

const columns: { key: SortKey; label: string; align: 'left' | 'right'; sortable: boolean }[] = [
    { key: 'name', label: 'Name', align: 'left', sortable: true },
    { key: 'status', label: 'Status', align: 'left', sortable: true },
    { key: 'cagr', label: 'CAGR', align: 'right', sortable: true },
    { key: 'max_drawdown', label: 'Max DD', align: 'right', sortable: true },
    { key: 'final_value', label: 'Final Value', align: 'right', sortable: true },
    { key: 'total_trades', label: 'Trades', align: 'right', sortable: true },
    { key: 'rebalance_frequency', label: 'Rebalance', align: 'left', sortable: true },
    { key: 'max_stocks_to_hold', label: 'Stocks', align: 'right', sortable: true },
    { key: 'created_at', label: 'Created', align: 'left', sortable: true },
];

function toggleSort(key: SortKey): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = key === 'name' || key === 'rebalance_frequency' ? 'asc' : 'desc';
    }
}

function getSortValue(bt: BacktestWithMetrics, key: SortKey): number | string {
    switch (key) {
        case 'cagr':
            return bt.summary_metrics?.cagr ?? -Infinity;
        case 'max_drawdown':
            return bt.summary_metrics?.max_drawdown ?? -Infinity;
        case 'final_value':
            return bt.summary_metrics?.final_value ?? -Infinity;
        case 'total_trades':
            return bt.summary_metrics?.total_trades ?? -Infinity;
        default:
            return bt[key] ?? '';
    }
}

const sortedBacktests = computed(() => {
    const list = [...filteredBacktests.value];
    const dir = sortDir.value === 'asc' ? 1 : -1;
    return list.sort((a, b) => {
        const av = getSortValue(a, sortKey.value);
        const bv = getSortValue(b, sortKey.value);
        if (av < bv) return -1 * dir;
        if (av > bv) return 1 * dir;
        return 0;
    });
});

// --- Table formatters ---

function formatPercent(value: number): string {
    return (value * 100).toFixed(2) + '%';
}

function formatMetric(bt: BacktestWithMetrics, key: 'cagr' | 'max_drawdown'): string {
    if (!bt.summary_metrics) return '—';
    return formatPercent(bt.summary_metrics[key]);
}

function formatCurrencyShort(value: number): string {
    const v = Number(value);
    const abs = Math.abs(v);
    if (abs >= 10000000) return '\u20B9' + (v / 10000000).toFixed(2) + ' Cr';
    if (abs >= 100000) return '\u20B9' + (v / 100000).toFixed(2) + ' L';
    return '\u20B9' + v.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function formatRelativeDate(dateStr: string): string {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return diffMins + 'm ago';
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return diffHours + 'h ago';
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 30) return diffDays + 'd ago';
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function cagrColor(bt: BacktestWithMetrics): string {
    if (!bt.summary_metrics) return 'text-gray-400';
    return bt.summary_metrics.cagr >= 0 ? 'text-green-700' : 'text-red-700';
}

function statusBadgeClass(status: string): Record<string, boolean> {
    return {
        'bg-gray-100 text-gray-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'running',
        'bg-green-100 text-green-700': status === 'completed',
        'bg-red-100 text-red-700': status === 'failed',
    };
}
</script>
