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
                                        :href="bt.status === 'completed' ? `/backtests/${bt.id}` : `/backtests/${bt.id}/edit`"
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
                                <div class="space-y-2.5">
                                    <!-- Universe & Ranking -->
                                    <div class="flex items-baseline gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Universe</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">
                                                {{ formatIndex(bt.index) }}
                                            </span>
                                            <span class="rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">
                                                {{ formatSortBy(bt.sort_by) }} {{ bt.sort_direction === 'desc' ? '↓' : '↑' }}
                                            </span>
                                            <span v-if="bt.apply_filters_on" class="rounded-md bg-purple-50 px-2 py-0.5 text-xs text-purple-700">
                                                Apply on: {{ formatSnakeCase(bt.apply_filters_on) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Portfolio -->
                                    <div class="flex items-baseline gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Portfolio</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ formatWeightage(bt.weightage) }}
                                            </span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ formatCurrencyShort(bt.initial_capital) }} capital
                                            </span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                {{ bt.max_stocks_to_hold }} stocks
                                            </span>
                                            <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                {{ bt.rebalance_frequency === 'weekly' ? 'Weekly' : 'Monthly' }} day {{ bt.rebalance_day }}
                                            </span>
                                            <span v-if="bt.worst_rank_held > 0" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                Worst rank {{ bt.worst_rank_held }}
                                            </span>
                                            <span v-if="bt.apply_hold_above_dma" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                Hold above DMA {{ bt.hold_above_dma_period }}
                                            </span>
                                            <span v-if="bt.execute_next_trading_day" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                                Execute next day
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Cash Call (only if active) -->
                                    <div v-if="bt.cash_call !== 'no_cash_call'" class="flex items-baseline gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Cash</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                {{ formatCashCall(bt.cash_call) }}
                                            </span>
                                            <span
                                                v-if="hasDmaBasedCashCall(bt.cash_call)"
                                                class="rounded-md bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                                            >
                                                {{ formatIndex(bt.cash_call_index) }} DMA {{ bt.cash_call_dma_period }}
                                            </span>
                                            <span v-if="bt.cash_return_rate > 0" class="rounded-md bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                                                {{ bt.cash_return_rate }}% p.a. return
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Filters (only if any are active) -->
                                    <div v-if="activeFilters(bt).length > 0" class="flex items-baseline gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Filters</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="(filter, idx) in activeFilters(bt)"
                                                :key="idx"
                                                class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800"
                                            >
                                                {{ filter }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Multi-factor (if active) -->
                                    <div v-if="bt.apply_factor_two || bt.apply_factor_three" class="flex items-baseline gap-2">
                                        <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Factors</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                                Factor 1: {{ formatSortBy(bt.sort_by) }} {{ bt.sort_direction === 'desc' ? '↓' : '↑' }}
                                            </span>
                                            <span v-if="bt.apply_factor_two" class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                                Factor 2: {{ formatSortBy(bt.factor_two_sort_by) }} {{ bt.factor_two_sort_direction === 'desc' ? '↓' : '↑' }}
                                            </span>
                                            <span v-if="bt.apply_factor_three" class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                                Factor 3: {{ formatSortBy(bt.factor_three_sort_by) }} {{ bt.factor_three_sort_direction === 'desc' ? '↓' : '↑' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
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

// --- Config display helpers ---

function formatIndex(value: string): string {
    if (!value) return '';
    return value
        .replace(/^nifty_/, 'Nifty ')
        .replace(/^etf$/, 'ETF')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

const sortByLabels: Record<string, string> = {
    absolute_return_one_year: 'Abs Return 1Y',
    absolute_return_nine_months: 'Abs Return 9M',
    absolute_return_six_months: 'Abs Return 6M',
    absolute_return_three_months: 'Abs Return 3M',
    absolute_return_one_months: 'Abs Return 1M',
    sharpe_return_one_year: 'Sharpe 1Y',
    sharpe_return_nine_months: 'Sharpe 9M',
    sharpe_return_six_months: 'Sharpe 6M',
    sharpe_return_three_months: 'Sharpe 3M',
    sharpe_return_one_months: 'Sharpe 1M',
    rsi_one_year: 'RSI 1Y',
    rsi_nine_months: 'RSI 9M',
    rsi_six_months: 'RSI 6M',
    rsi_three_months: 'RSI 3M',
    rsi_one_months: 'RSI 1M',
    volatility_one_year: 'Volatility 1Y',
    beta: 'Beta',
    price_to_earnings: 'P/E',
    marketcap: 'Market Cap',
    close_adjusted: 'Close (Adj)',
    close_raw: 'Close',
    away_from_high_all_time: 'Away ATH',
    away_from_high_one_year: 'Away 1Y High',
    return_twelve_minus_one_months: 'Return 12-1M',
    return_twelve_minus_two_months: 'Return 12-2M',
    absolute_divide_beta_return_one_year: 'Abs/Beta 1Y',
    sharpe_divide_beta_return_one_year: 'Sharpe/Beta 1Y',
};

function formatSortBy(value: string): string {
    if (sortByLabels[value]) return sortByLabels[value];

    // Generic formatting for average combos
    return value
        .replace(/^average_/, 'Avg ')
        .replace(/absolute_return/, 'Abs Ret')
        .replace(/sharpe_return/, 'Sharpe')
        .replace(/absolute_divide_beta_return/, 'Abs/Beta')
        .replace(/sharpe_divide_beta_return/, 'Sharpe/Beta')
        .replace(/rsi/, 'RSI')
        .replace(/_twelve/g, ' 12').replace(/_nine/g, '/9').replace(/_six/g, '/6')
        .replace(/_three/g, '/3').replace(/_one/g, '/1').replace(/_months$/, 'M')
        .replace(/_/g, ' ')
        .trim();
}

function formatWeightage(value: string): string {
    const labels: Record<string, string> = {
        equal_weight: 'Equal Weight',
        equal_weight_rebalanced: 'EW Rebalanced',
        inverse_volatility: 'Inverse Volatility',
    };
    return labels[value] || formatSnakeCase(value);
}

function formatCashCall(value: string): string {
    const labels: Record<string, string> = {
        no_cash_call: 'None',
        cash_call_if_not_enough_stocks: 'Cash if not enough stocks',
        full_cash_below_index_dma: 'Full cash below DMA',
        only_exits_below_index_dma: 'Only exits below DMA',
        allocate_to_gold_below_index_dma: 'Gold below DMA',
        only_exits_allocate_to_gold_below_index_dma: 'Exits + Gold below DMA',
    };
    return labels[value] || formatSnakeCase(value);
}

function hasDmaBasedCashCall(value: string): boolean {
    return [
        'full_cash_below_index_dma',
        'only_exits_below_index_dma',
        'allocate_to_gold_below_index_dma',
        'only_exits_allocate_to_gold_below_index_dma',
    ].includes(value);
}

function formatSnakeCase(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function activeFilters(bt: BacktestWithMetrics): string[] {
    const filters: string[] = [];

    // Moving Averages
    if (bt.apply_ma) {
        const above: number[] = [];
        const below: number[] = [];
        if (bt.above_ma_200) above.push(200);
        if (bt.above_ma_100) above.push(100);
        if (bt.above_ma_50) above.push(50);
        if (bt.above_ma_20) above.push(20);
        if (bt.below_ma_200) below.push(200);
        if (bt.below_ma_100) below.push(100);
        if (bt.below_ma_50) below.push(50);
        if (bt.below_ma_20) below.push(20);
        if (above.length) filters.push('Above MA ' + above.join(', '));
        if (below.length) filters.push('Below MA ' + below.join(', '));
    }

    // Exponential Moving Averages
    if (bt.apply_ema) {
        const above: number[] = [];
        const below: number[] = [];
        if (bt.above_ema_200) above.push(200);
        if (bt.above_ema_100) above.push(100);
        if (bt.above_ema_50) above.push(50);
        if (bt.above_ema_20) above.push(20);
        if (bt.below_ema_200) below.push(200);
        if (bt.below_ema_100) below.push(100);
        if (bt.below_ema_50) below.push(50);
        if (bt.below_ema_20) below.push(20);
        if (above.length) filters.push('Above EMA ' + above.join(', '));
        if (below.length) filters.push('Below EMA ' + below.join(', '));
    }

    // PE Range
    if (bt.apply_pe) {
        filters.push('PE ' + bt.price_to_earnings_from + '–' + bt.price_to_earnings_to);
    }

    // Series
    const series: string[] = [];
    if (bt.series_eq) series.push('EQ');
    if (bt.series_be) series.push('BE');
    if (series.length) filters.push('Series: ' + series.join(', '));

    // Beta
    if (bt.ignore_above_beta > 0) {
        filters.push('Beta < ' + bt.ignore_above_beta);
    }

    // Volume
    if (bt.median_volume_one_year > 0) {
        filters.push('Vol > ' + formatCompactNumber(bt.median_volume_one_year));
    }

    // Price Range
    if (bt.price_from > 0 || bt.price_to > 0) {
        const from = bt.price_from > 0 ? '₹' + bt.price_from : '';
        const to = bt.price_to > 0 ? '₹' + bt.price_to : '';
        if (from && to) filters.push('Price ' + from + '–' + to);
        else if (from) filters.push('Price > ' + from);
        else if (to) filters.push('Price < ' + to);
    }

    // Min return
    if (bt.minimum_return_one_year > 0) {
        filters.push('Min Return 1Y > ' + bt.minimum_return_one_year + '%');
    }

    // Custom filters
    for (let i = 1; i <= 5; i++) {
        const key = `apply_custom_filter_${numWord(i)}` as keyof Backtest;
        if (bt[key]) {
            const v1Key = `custom_filter_${numWord(i)}_value_one` as keyof Backtest;
            const opKey = `custom_filter_${numWord(i)}_operator` as keyof Backtest;
            const v2Key = `custom_filter_${numWord(i)}_value_two` as keyof Backtest;
            filters.push('Custom: ' + formatSnakeCase(bt[v1Key] as string) + ' ' + bt[opKey] + ' ' + formatSnakeCase(bt[v2Key] as string));
        }
    }

    return filters;
}

function numWord(n: number): string {
    return ['one', 'two', 'three', 'four', 'five'][n - 1];
}

function formatCompactNumber(value: number): string {
    if (value >= 10000000) return (value / 10000000).toFixed(0) + 'Cr';
    if (value >= 100000) return (value / 100000).toFixed(0) + 'L';
    if (value >= 1000) return (value / 1000).toFixed(0) + 'K';
    return String(value);
}

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
