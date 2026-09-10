<template>
    <div class="space-y-2.5">
        <!-- Universe & Ranking -->
        <div class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Universe</span>
            <div class="flex flex-wrap gap-1.5">
                <span class="rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">
                    {{ formatIndex(backtest.index) }}
                </span>
                <span class="rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800">
                    {{ formatSortBy(backtest.sort_by) }} {{ backtest.sort_direction === 'desc' ? '↓' : '↑' }}
                </span>
                <span v-if="backtest.apply_filters_on" class="rounded-md bg-purple-50 px-2 py-0.5 text-xs text-purple-700">
                    Apply on: {{ formatApplyFiltersOn(backtest.apply_filters_on) }}
                </span>
            </div>
        </div>

        <!-- Portfolio -->
        <div class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Portfolio</span>
            <div class="flex flex-wrap gap-1.5">
                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                    {{ formatWeightage(backtest.weightage) }}
                </span>
                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                    {{ formatCurrencyShort(backtest.initial_capital) }} capital
                </span>
                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    {{ backtest.max_stocks_to_hold }} stocks
                </span>
                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    {{ backtest.rebalance_frequency === 'weekly' ? 'Weekly · ' + weekdayName(backtest.rebalance_day) : 'Monthly day ' + backtest.rebalance_day }}
                </span>
                <span v-if="backtest.worst_rank_held > 0" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Worst rank {{ backtest.worst_rank_held }}
                </span>
                <span v-if="backtest.apply_hold_above_dma" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Hold above DMA {{ backtest.hold_above_dma_period }}
                </span>
                <span v-if="backtest.execute_next_trading_day" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Execute next day
                </span>
                <span v-if="backtest.skip_circuit_trades" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Skip circuit trades
                </span>
                <span v-if="backtest.exit_before_demerger" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Exit before demerger
                </span>
                <span v-if="backtest.exit_on_be_series" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    Exit on BE series
                </span>
                <span v-if="backtest.start_date" class="rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                    From {{ formatStartDate(backtest.start_date) }}
                </span>
            </div>
        </div>

        <!-- Cash Call (only if active) -->
        <!-- Cash interest accrues on idle cash regardless of the cash-call mode -->
        <div v-if="backtest.cash_call !== 'no_cash_call' || Number(backtest.cash_return_rate) > 0" class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Cash</span>
            <div class="flex flex-wrap gap-1.5">
                <span v-if="backtest.cash_call !== 'no_cash_call'" class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                    {{ formatCashCall(backtest.cash_call) }}
                </span>
                <span
                    v-if="hasDmaBasedCashCall(backtest.cash_call)"
                    class="rounded-md bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                >
                    {{ formatIndex(backtest.cash_call_index) }} DMA {{ backtest.cash_call_dma_period }}
                </span>
                <span
                    v-if="backtest.cash_call === 'only_exits_allocate_to_gold_above_dma_below_index_dma'"
                    class="rounded-md bg-amber-50 px-2 py-0.5 text-xs text-amber-700"
                >
                    GOLDBEES above DMA {{ backtest.cash_call_gold_dma_period }}
                </span>
                <span v-if="Number(backtest.cash_return_rate) > 0" class="rounded-md bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                    {{ backtest.cash_return_rate }}% p.a. return
                </span>
            </div>
        </div>

        <!-- Costs (only when rates differ from the statutory defaults) -->
        <div v-if="costChips(backtest).length > 0" class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Costs</span>
            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="(chip, idx) in costChips(backtest)"
                    :key="idx"
                    class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800"
                >
                    {{ chip }}
                </span>
            </div>
        </div>

        <!-- Filters (only if any are active) -->
        <div v-if="activeFilters(backtest).length > 0" class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Filters</span>
            <div class="flex flex-wrap gap-1.5">
                <span
                    v-for="(filter, idx) in activeFilters(backtest)"
                    :key="idx"
                    class="rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-800"
                >
                    {{ filter }}
                </span>
            </div>
        </div>

        <!-- Multi-factor (if active) -->
        <div v-if="backtest.apply_factor_two || backtest.apply_factor_three" class="flex items-baseline gap-2">
            <span class="w-16 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Factors</span>
            <div class="flex flex-wrap gap-1.5">
                <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                    Factor 1: {{ formatSortBy(backtest.sort_by) }} {{ backtest.sort_direction === 'desc' ? '↓' : '↑' }}
                </span>
                <span v-if="backtest.apply_factor_two" class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                    Factor 2: {{ formatSortBy(backtest.factor_two_sort_by) }} {{ backtest.factor_two_sort_direction === 'desc' ? '↓' : '↑' }}
                </span>
                <span v-if="backtest.apply_factor_three" class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-800">
                    Factor 3: {{ formatSortBy(backtest.factor_three_sort_by) }} {{ backtest.factor_three_sort_direction === 'desc' ? '↓' : '↑' }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Backtest } from '@/types/app/Models/Backtest';
import { formatCompactNumber, formatCurrencyShort } from '@/utils/format';

defineProps<{
    backtest: Backtest;
}>();

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
        only_exits_allocate_to_gold_above_dma_below_index_dma: 'Exits to gold with gold DMA check',
    };
    return labels[value] || formatSnakeCase(value);
}

function hasDmaBasedCashCall(value: string): boolean {
    return [
        'full_cash_below_index_dma',
        'only_exits_below_index_dma',
        'allocate_to_gold_below_index_dma',
        'only_exits_allocate_to_gold_below_index_dma',
        'only_exits_allocate_to_gold_above_dma_below_index_dma',
    ].includes(value);
}

function formatSnakeCase(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function weekdayName(day: number): string {
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'][day - 1] ?? 'day ' + day;
}

function formatApplyFiltersOn(value: string): string {
    const labels: Record<string, string> = {
        all: 'All stocks of selected index',
        top_decile: 'Top 10% by rank',
        top_two_decile: 'Top 20% by rank',
        top_three_decile: 'Top 30% by rank',
        top_four_decile: 'Top 40% by rank',
        top_five_decile: 'Top 50% by rank',
        top_50: 'Top 50 stocks',
        top_100: 'Top 100 stocks',
    };
    return labels[value] || formatSnakeCase(value);
}

function formatStartDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { month: 'short', year: 'numeric' });
}

function activeFilters(bt: Backtest): string[] {
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

    // Note: apply_pe / marketcap fields exist on the model but the backtest
    // engine never applies them, so they get no chip.

    // Series
    const series: string[] = [];
    if (bt.series_eq) series.push('EQ');
    if (bt.series_be) series.push('BE');
    if (series.length) filters.push('Series: ' + series.join(', '));

    // Beta (100 is the "off" sentinel)
    if (Number(bt.ignore_above_beta) < 100) {
        filters.push('Beta < ' + bt.ignore_above_beta);
    }

    // Volume
    if (bt.median_volume_one_year > 0) {
        filters.push('Vol > ' + formatCompactNumber(bt.median_volume_one_year));
    }

    // Away from high (100 is the "off" sentinel)
    if (bt.away_from_high_all_time < 100) {
        filters.push('Within ' + bt.away_from_high_all_time + '% of ATH');
    }
    if (bt.away_from_high_one_year < 100) {
        filters.push('Within ' + bt.away_from_high_one_year + '% of 1Y high');
    }

    // Positive days (0 is the "off" sentinel)
    const positiveDays: string[] = [];
    if (bt.positive_days_percent_one_year > 0) positiveDays.push('1Y ≥ ' + bt.positive_days_percent_one_year + '%');
    if (bt.positive_days_percent_nine_months > 0) positiveDays.push('9M ≥ ' + bt.positive_days_percent_nine_months + '%');
    if (bt.positive_days_percent_six_months > 0) positiveDays.push('6M ≥ ' + bt.positive_days_percent_six_months + '%');
    if (bt.positive_days_percent_three_months > 0) positiveDays.push('3M ≥ ' + bt.positive_days_percent_three_months + '%');
    if (bt.positive_days_percent_one_months > 0) positiveDays.push('1M ≥ ' + bt.positive_days_percent_one_months + '%');
    if (positiveDays.length) filters.push('Positive days ' + positiveDays.join(', '));

    // Circuits (300 is the "off" sentinel — anything below it is a live filter)
    const circuits: string[] = [];
    if (bt.circuits_one_year < 300) circuits.push('1Y ≤ ' + bt.circuits_one_year);
    if (bt.circuits_nine_months < 300) circuits.push('9M ≤ ' + bt.circuits_nine_months);
    if (bt.circuits_six_months < 300) circuits.push('6M ≤ ' + bt.circuits_six_months);
    if (bt.circuits_three_months < 300) circuits.push('3M ≤ ' + bt.circuits_three_months);
    if (bt.circuits_one_months < 300) circuits.push('1M ≤ ' + bt.circuits_one_months);
    if (circuits.length) filters.push('Circuits ' + circuits.join(', '));

    // Price Range (0 / 10000000 are the "off" sentinels)
    const priceFrom = bt.price_from > 0;
    const priceTo = bt.price_to > 0 && bt.price_to < 10000000;
    if (priceFrom && priceTo) filters.push('Price ₹' + bt.price_from + '–₹' + bt.price_to);
    else if (priceFrom) filters.push('Price > ₹' + bt.price_from);
    else if (priceTo) filters.push('Price < ₹' + bt.price_to);

    // Min return (the engine applies this filter for any value above -100)
    if (Number(bt.minimum_return_one_year) > -100) {
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

// Statutory defaults — a chip only appears when a rate deviates from these.
const defaultCostRates: { key: keyof Backtest; label: string; default: number }[] = [
    { key: 'brokerage_rate', label: 'Brokerage', default: 0 },
    { key: 'stt_rate', label: 'STT', default: 0.1 },
    { key: 'transaction_charges_rate', label: 'Exchange txn', default: 0.00307 },
    { key: 'sebi_charges_rate', label: 'SEBI', default: 0.00001 },
    { key: 'gst_rate', label: 'GST', default: 18 },
    { key: 'stamp_charges_rate', label: 'Stamp', default: 0.015 },
];

function costChips(bt: Backtest): string[] {
    return defaultCostRates
        .filter(({ key, default: def }) => Number(bt[key]) !== def)
        .map(({ key, label }) => `${label} ${Number(bt[key])}%`);
}
</script>
