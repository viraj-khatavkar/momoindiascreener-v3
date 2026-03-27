<template>
    <div>
        <Head :title="backtest.name" />

        <div class="flex items-start justify-between">
            <PageHeader :description="backtest.status === 'completed' ? backtestPeriod : 'Backtest results'">
                {{ backtest.name }}
            </PageHeader>
            <div class="flex items-center gap-3">
                <span
                    :class="statusBadgeClass(backtest.status)"
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                >
                    {{ backtest.status }}
                </span>
                <Link
                    :href="`/backtests/${backtest.id}/edit`"
                    class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs ring-1 ring-gray-300 transition-all duration-200 hover:bg-gray-50"
                >
                    Edit
                </Link>
                <Link
                    :href="`/backtests/${backtest.id}/run`"
                    method="post"
                    as="button"
                    class="inline-flex items-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
                >
                    Re-run
                </Link>
            </div>
        </div>

        <!-- Running state -->
        <div v-if="backtest.status === 'running'" class="mt-6">
            <div class="rounded-xl bg-blue-50 p-6 ring-1 ring-blue-100">
                <p class="mb-3 text-sm font-medium text-blue-800">
                    Backtest is running... {{ backtest.progress }}% complete
                </p>
                <div class="h-2.5 w-full rounded-full bg-blue-200">
                    <div
                        class="h-2.5 rounded-full bg-blue-600 transition-all duration-500"
                        :style="`width: ${backtest.progress}%`"
                    />
                </div>
            </div>
        </div>

        <!-- Failed state -->
        <div v-else-if="backtest.status === 'failed'" class="mt-6">
            <ErrorAlert>
                {{ backtest.error_message || 'An unknown error occurred while running the backtest.' }}
            </ErrorAlert>
        </div>

        <!-- Pending state -->
        <div v-else-if="backtest.status === 'pending'" class="mt-6">
            <InfoAlert>
                This backtest hasn't been run yet. Click <Link :href="`/backtests/${backtest.id}/edit`" class="font-semibold text-blue-700 underline">Edit</Link> to configure parameters, then click "Run Backtest".
            </InfoAlert>
        </div>

        <!-- Completed state -->
        <div v-else-if="backtest.status === 'completed' && summaryMetrics" class="mt-6 space-y-6">

            <!-- Hero: one-line result -->
            <div class="inline-flex items-baseline gap-x-4 rounded-lg bg-linear-to-r from-purple-600 to-purple-800 px-5 py-3 text-white shadow-md">
                <span class="text-xl font-bold">{{ formatPercent(summaryMetrics.cagr) }} CAGR</span>
                <span class="text-sm text-purple-200">{{ formatCurrencyShort(backtest.initial_capital) }} &rarr; {{ formatCurrencyShort(summaryMetrics.final_value) }}</span>
                <span class="text-xs text-purple-300">{{ backtestYears }} yrs</span>
            </div>

            <!-- Row 1: NAV Chart (primary visual) -->
            <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">NAV Chart</h2>
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-gray-500">Benchmark:</label>
                        <select
                            v-model="selectedBenchmark"
                            class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm text-gray-700 shadow-xs focus:border-purple-500 focus:ring-purple-500"
                        >
                            <option value="">None</option>
                            <option v-for="opt in benchmarkOptions" :key="opt.id" :value="opt.id">
                                {{ opt.name }}
                            </option>
                        </select>
                        <span v-if="loadingBenchmark" class="text-xs text-gray-400">Loading...</span>
                    </div>
                </div>
                <BacktestNavChart :daily-snapshots="dailySnapshots" :benchmark-data="benchmarkData" />

                <!-- Benchmark comparison metrics -->
                <div v-if="benchmarkData.length > 0" class="mt-4 flex flex-wrap gap-6 border-t border-gray-100 pt-4 text-sm">
                    <div>
                        <span class="text-gray-500">Benchmark CAGR: </span>
                        <span :class="benchmarkMetrics.cagr >= 0 ? 'text-green-700' : 'text-red-700'" class="font-semibold">
                            {{ formatPercent(benchmarkMetrics.cagr) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Alpha: </span>
                        <span :class="benchmarkMetrics.alpha >= 0 ? 'text-green-700' : 'text-red-700'" class="font-semibold">
                            {{ formatPercent(benchmarkMetrics.alpha) }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500">Benchmark Max DD: </span>
                        <span class="font-semibold text-red-700">{{ formatPercent(benchmarkMetrics.maxDrawdown) }}</span>
                    </div>
                </div>
            </div>

            <!-- Drawdown Chart -->
            <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Drawdown</h2>
                <DrawdownChart :daily-snapshots="dailySnapshots" />
            </div>

            <!-- Row 2: Key Metrics table + Cash Allocation chart side by side -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

                <!-- Key Metrics comparison table -->
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100 lg:col-span-2">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Key Metrics</h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left font-medium text-gray-500"></th>
                                <th class="pb-2 text-right font-semibold text-purple-700">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-2.5 text-gray-600">CAGR</td>
                                <td class="py-2.5 text-right font-semibold" :class="summaryMetrics.cagr >= 0 ? 'text-green-700' : 'text-red-700'">{{ formatPercent(summaryMetrics.cagr) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Total Return</td>
                                <td class="py-2.5 text-right font-semibold" :class="totalReturn >= 0 ? 'text-green-700' : 'text-red-700'">{{ formatPercent(totalReturn) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Max Drawdown</td>
                                <td class="py-2.5 text-right font-semibold text-red-700">{{ formatPercent(summaryMetrics.max_drawdown) }}</td>
                            </tr>
                            <tr v-if="summaryMetrics.max_drawdown_start_date">
                                <td class="py-2.5 text-gray-600">Drawdown Period</td>
                                <td class="py-2.5 text-right text-sm text-gray-700" colspan="2">
                                    {{ formatDate(summaryMetrics.max_drawdown_start_date) }} to {{ formatDate(summaryMetrics.max_drawdown_end_date) }}
                                </td>
                            </tr>
                            <tr class="border-t border-gray-200">
                                <td class="py-2.5 text-gray-600">Initial Investment</td>
                                <td class="py-2.5 text-right text-gray-900" colspan="2">{{ formatCurrencyShort(backtest.initial_capital) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Final Value</td>
                                <td class="py-2.5 text-right font-semibold text-green-700" colspan="2">{{ formatCurrencyShort(summaryMetrics.final_value) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Total Charges</td>
                                <td class="py-2.5 text-right text-gray-700" colspan="2">{{ formatCurrencyShort(summaryMetrics.total_charges_paid) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Total Trades</td>
                                <td class="py-2.5 text-right text-gray-700" colspan="2">{{ summaryMetrics.total_trades }} ({{ buyCount }} buys, {{ sellCount }} sells)</td>
                            </tr>
                            <tr>
                                <td class="py-2.5 text-gray-600">Avg Cash %</td>
                                <td class="py-2.5 text-right text-gray-700" colspan="2">{{ cashStats.avg.toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Cash Allocation Chart -->
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100 lg:col-span-3">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Cash Allocation Over Time</h2>
                    <CashAllocationChart :daily-snapshots="dailySnapshots" />
                </div>
            </div>

            <!-- Monthly Returns Heatmap -->
            <div
                v-if="monthlyReturns.length > 0"
                class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100"
            >
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Monthly Returns</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Year</th>
                                <th v-for="month in monthNames" :key="month" class="px-3 py-2 text-right font-medium text-gray-500">
                                    {{ month }}
                                </th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-700">Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in monthlyReturns" :key="row.year">
                                <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900">{{ row.year }}</td>
                                <td
                                    v-for="(val, idx) in row.months"
                                    :key="idx"
                                    class="whitespace-nowrap px-3 py-2 text-right"
                                    :class="val === null ? 'text-gray-300' : val >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                                >
                                    {{ val === null ? '-' : (val >= 0 ? '+' : '') + (val * 100).toFixed(1) + '%' }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-2 text-right font-semibold"
                                    :class="row.yearReturn >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                >
                                    {{ (row.yearReturn >= 0 ? '+' : '') + (row.yearReturn * 100).toFixed(1) + '%' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rolling Returns -->
            <div class="grid grid-cols-1 gap-6">
                <div
                    v-if="hasRollingReturns"
                    class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100"
                >
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Rolling Returns (Annualized)</h2>
                    <RollingReturnsChart
                        :one-year="summaryMetrics.rolling_returns_one_year"
                        :three-year="summaryMetrics.rolling_returns_three_year"
                        :five-year="summaryMetrics.rolling_returns_five_year"
                        class="mb-6"
                    />
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left font-medium text-gray-500">Period</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Min</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Avg</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Max</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="period in rollingPeriods" :key="period.key">
                                <td class="py-2.5 font-medium text-gray-900">{{ period.label }}</td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).min)">
                                    {{ formatPercent(rollingStats(period.key).min) }}
                                </td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).avg)">
                                    {{ formatPercent(rollingStats(period.key).avg) }}
                                </td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).max)">
                                    {{ formatPercent(rollingStats(period.key).max) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Trade Log Table -->
            <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    Trade Log
                    <span class="ml-2 text-xs font-normal normal-case text-gray-400">({{ trades.length }} trades)</span>
                </h2>
                <TradeLogTable :trades="trades" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import BacktestNavChart from '@/Pages/Backtests/partials/BacktestNavChart.vue';
import CashAllocationChart from '@/Pages/Backtests/partials/CashAllocationChart.vue';
import DrawdownChart from '@/Pages/Backtests/partials/DrawdownChart.vue';
import RollingReturnsChart from '@/Pages/Backtests/partials/RollingReturnsChart.vue';
import TradeLogTable from '@/Pages/Backtests/partials/TradeLogTable.vue';
import type { Backtest } from '@/types/app/Models/Backtest';
import type { BacktestSummaryMetric } from '@/types/app/Models/BacktestSummaryMetric';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';
import type { BacktestTrade } from '@/types/app/Models/BacktestTrade';
import type { SelectOption } from '@/types/SelectOption';

interface BenchmarkPoint {
    date: string;
    nav: number;
}

const props = defineProps<{
    backtest: Backtest;
    summaryMetrics: BacktestSummaryMetric | null;
    dailySnapshots: BacktestDailySnapshot[];
    trades: BacktestTrade[];
    benchmarkOptions: SelectOption[];
}>();

// --- Polling ---

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.backtest.status === 'running' || props.backtest.status === 'pending') {
        startPolling();
    }
    if (props.backtest.status === 'completed') {
        fetchBenchmark(selectedBenchmark.value);
    }
});

onUnmounted(() => stopPolling());

watch(() => props.backtest.status, (newStatus, oldStatus) => {
    if (newStatus === 'completed' && oldStatus !== 'completed') {
        // Job just finished — reload all data and fetch benchmark
        stopPolling();
        router.reload({
            only: ['backtest', 'summaryMetrics', 'dailySnapshots', 'trades'],
            onFinish: () => fetchBenchmark(selectedBenchmark.value),
        });
    } else if (newStatus !== 'running' && newStatus !== 'pending') {
        stopPolling();
    }
});

function startPolling(): void {
    pollInterval = setInterval(() => {
        // Only reload backtest (status/progress) while running — not the heavy data
        router.reload({ only: ['backtest'] });
    }, 3000);
}

function stopPolling(): void {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

// --- Benchmark ---

const selectedBenchmark = ref<string>('nifty-50');
const benchmarkData = ref<BenchmarkPoint[]>([]);
const loadingBenchmark = ref(false);

async function fetchBenchmark(slug: string): Promise<void> {
    if (!slug) {
        benchmarkData.value = [];
        return;
    }
    loadingBenchmark.value = true;
    try {
        const response = await fetch(`/backtests/${props.backtest.id}/benchmark?slug=${slug}`);
        benchmarkData.value = await response.json();
    } catch {
        benchmarkData.value = [];
    } finally {
        loadingBenchmark.value = false;
    }
}

watch(selectedBenchmark, (slug) => fetchBenchmark(slug));

const benchmarkMetrics = computed(() => {
    if (benchmarkData.value.length < 2 || !props.dailySnapshots || props.dailySnapshots.length < 2) {
        return { cagr: 0, alpha: 0, maxDrawdown: 0, totalReturn: 0 };
    }

    const firstNav = benchmarkData.value[0].nav;
    const lastNav = benchmarkData.value[benchmarkData.value.length - 1].nav;
    const totalReturn = (lastNav - firstNav) / firstNav;

    const first = new Date(benchmarkData.value[0].date);
    const last = new Date(benchmarkData.value[benchmarkData.value.length - 1].date);
    const years = (last.getTime() - first.getTime()) / (365.25 * 24 * 60 * 60 * 1000);

    const cagr = years > 0 ? Math.pow(lastNav / firstNav, 1 / years) - 1 : 0;
    const alpha = props.summaryMetrics ? props.summaryMetrics.cagr - cagr : 0;

    // Max drawdown
    let peak = 0;
    let maxDd = 0;
    for (const point of benchmarkData.value) {
        if (point.nav > peak) peak = point.nav;
        if (peak > 0) {
            const dd = (point.nav - peak) / peak;
            if (dd < maxDd) maxDd = dd;
        }
    }

    return { cagr, alpha, maxDrawdown: maxDd, totalReturn };
});

// --- Formatters ---

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatPercent(value: number): string {
    return (value * 100).toFixed(2) + '%';
}

function formatCurrencyShort(value: number): string {
    const v = Number(value);
    const abs = Math.abs(v);
    if (abs >= 10000000) return '\u20B9' + (v / 10000000).toFixed(2) + ' Cr';
    if (abs >= 100000) return '\u20B9' + (v / 100000).toFixed(2) + ' L';
    return '\u20B9' + v.toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function statusBadgeClass(status: string): Record<string, boolean> {
    return {
        'bg-gray-100 text-gray-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'running',
        'bg-green-100 text-green-700': status === 'completed',
        'bg-red-100 text-red-700': status === 'failed',
    };
}

function rollingReturnColor(value: number): string {
    return value >= 0 ? 'text-green-700' : 'text-red-700';
}

// --- Computed: derived metrics ---

const totalReturn = computed(() =>
    props.summaryMetrics ? (props.summaryMetrics.final_value - props.backtest.initial_capital) / props.backtest.initial_capital : 0,
);

const backtestPeriod = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length < 2) return '';
    return formatDate(props.dailySnapshots[0].date) + ' to ' + formatDate(props.dailySnapshots[props.dailySnapshots.length - 1].date);
});

const backtestYears = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length < 2) return '0';
    const first = new Date(props.dailySnapshots[0].date);
    const last = new Date(props.dailySnapshots[props.dailySnapshots.length - 1].date);
    return ((last.getTime() - first.getTime()) / (365.25 * 24 * 60 * 60 * 1000)).toFixed(1);
});

const buyCount = computed(() => props.trades.filter((t) => t.trade_type === 'buy').length);
const sellCount = computed(() => props.trades.filter((t) => t.trade_type === 'sell').length);

const cashStats = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return { min: 0, avg: 0, max: 0 };
    const pcts = props.dailySnapshots.map((s) => {
        const total = Number(s.total_value);
        return total > 0 ? (Number(s.cash) / total) * 100 : 0;
    });
    return { min: Math.min(...pcts), avg: pcts.reduce((a, b) => a + b, 0) / pcts.length, max: Math.max(...pcts) };
});

// --- Computed: rolling returns ---

const rollingPeriods = computed(() => {
    const periods: { key: 'one_year' | 'three_year' | 'five_year'; label: string }[] = [];
    if (props.summaryMetrics?.rolling_returns_one_year?.length) periods.push({ key: 'one_year', label: '1 Year' });
    if (props.summaryMetrics?.rolling_returns_three_year?.length) periods.push({ key: 'three_year', label: '3 Year' });
    if (props.summaryMetrics?.rolling_returns_five_year?.length) periods.push({ key: 'five_year', label: '5 Year' });
    return periods;
});

const hasRollingReturns = computed(() => rollingPeriods.value.length > 0);

function rollingStats(period: 'one_year' | 'three_year' | 'five_year'): { min: number; avg: number; max: number } {
    const data = props.summaryMetrics?.[`rolling_returns_${period}`];
    if (!data || data.length === 0) return { min: 0, avg: 0, max: 0 };
    const returns = data.map((d) => d.return);
    return {
        min: Math.min(...returns),
        avg: returns.reduce((sum, r) => sum + r, 0) / returns.length,
        max: Math.max(...returns),
    };
}

// --- Computed: yearly breakdown ---

const yearlyBreakdown = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return [];

    const byYear: Record<string, { firstNav: number; lastNav: number; firstBench: number; lastBench: number }> = {};
    for (const s of props.dailySnapshots) {
        const year = s.date.substring(0, 4);
        if (!byYear[year]) {
            byYear[year] = { firstNav: Number(s.nav), lastNav: Number(s.nav), firstBench: Number(s.benchmark_nav), lastBench: Number(s.benchmark_nav) };
        } else {
            byYear[year].lastNav = Number(s.nav);
            byYear[year].lastBench = Number(s.benchmark_nav);
        }
    }

    const years = Object.keys(byYear).sort();
    return years.map((year, idx) => {
        const d = byYear[year];
        const prevNav = idx > 0 ? byYear[years[idx - 1]].lastNav : 100;
        const prevBench = idx > 0 ? byYear[years[idx - 1]].lastBench : 100;
        const sr = (d.lastNav - prevNav) / prevNav;
        const br = (d.lastBench - prevBench) / prevBench;
        return { year, strategyReturn: sr, benchmarkReturn: br, alpha: sr - br };
    });
});

// --- Computed: monthly returns heatmap ---

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const monthlyReturns = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return [];

    const byMonth: Record<string, { firstNav: number; lastNav: number }> = {};
    for (const s of props.dailySnapshots) {
        const key = s.date.substring(0, 7);
        const nav = Number(s.nav);
        if (!byMonth[key]) { byMonth[key] = { firstNav: nav, lastNav: nav }; }
        else { byMonth[key].lastNav = nav; }
    }

    const sortedKeys = Object.keys(byMonth).sort();
    const monthlyData: Record<string, number> = {};
    for (let i = 0; i < sortedKeys.length; i++) {
        const key = sortedKeys[i];
        const prevNav = i > 0 ? byMonth[sortedKeys[i - 1]].lastNav : byMonth[key].firstNav;
        monthlyData[key] = (byMonth[key].lastNav - prevNav) / prevNav;
    }

    const years = [...new Set(sortedKeys.map((k) => k.substring(0, 4)))].sort().reverse();
    return years.map((year) => {
        const months: (number | null)[] = [];
        let yearStartNav = 0;
        let yearEndNav = 0;
        for (let m = 0; m < 12; m++) {
            const key = year + '-' + String(m + 1).padStart(2, '0');
            if (monthlyData[key] !== undefined) {
                months.push(monthlyData[key]);
                if (!yearStartNav) {
                    const prevKey = sortedKeys[sortedKeys.indexOf(key) - 1];
                    yearStartNav = prevKey ? byMonth[prevKey].lastNav : byMonth[key].firstNav;
                }
                yearEndNav = byMonth[key].lastNav;
            } else {
                months.push(null);
            }
        }
        const yearReturn = yearStartNav > 0 ? (yearEndNav - yearStartNav) / yearStartNav : 0;
        return { year, months, yearReturn };
    });
});
</script>
