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
            <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-gray-900 to-gray-950 p-8 shadow-xl ring-1 ring-white/10">
                <!-- Animated background grid -->
                <div class="pointer-events-none absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 24px 24px;" />

                <!-- Glow behind progress -->
                <div
                    class="pointer-events-none absolute -top-20 left-0 h-40 w-40 rounded-full bg-purple-500/20 blur-3xl transition-all duration-1000"
                    :style="`left: ${Math.max(0, backtest.progress - 10)}%`"
                />

                <!-- Top row: percentage + status -->
                <div class="relative flex items-end justify-between">
                    <div class="flex items-baseline gap-3">
                        <span class="text-5xl font-black tabular-nums tracking-tight text-white">{{ backtest.progress }}</span>
                        <span class="text-lg font-medium text-white/40">%</span>
                    </div>
                    <div class="flex items-center gap-2 pb-2">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-purple-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-purple-500"></span>
                        </span>
                        <span class="text-sm font-medium text-white/60">{{ progressStageLabel }}</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="relative mt-6 h-3 w-full overflow-hidden rounded-full bg-white/10">
                    <!-- Track glow -->
                    <div
                        class="absolute inset-y-0 left-0 rounded-full bg-linear-to-r from-violet-500 via-purple-500 to-fuchsia-500 transition-all duration-700 ease-out"
                        :style="`width: ${backtest.progress}%`"
                    />
                    <!-- Shimmer overlay -->
                    <div
                        class="absolute inset-y-0 left-0 overflow-hidden rounded-full transition-all duration-700 ease-out"
                        :style="`width: ${backtest.progress}%`"
                    >
                        <div class="animate-shimmer absolute inset-0 -translate-x-full bg-linear-to-r from-transparent via-white/25 to-transparent" />
                    </div>
                </div>

                <!-- Bottom detail chips -->
                <div class="mt-5 flex items-center gap-3 text-xs">
                    <span class="rounded-full bg-white/10 px-3 py-1 font-medium text-white/50">
                        {{ backtest.progress < 95 ? 'Simulating trades' : 'Computing metrics' }}
                    </span>
                    <span v-if="backtest.progress > 0 && backtest.progress < 100" class="text-white/30">
                        {{ backtest.progress }}% of trading days processed
                    </span>
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

            <!-- EDITORIAL HERO: colored top rule + generous typography -->
            <div>
                <!-- Accent top rule (fades from signal color to transparent) -->
                <div
                    class="h-[3px] w-full rounded-full"
                    :class="summaryMetrics.cagr >= 0 ? 'bg-linear-to-r from-emerald-500 via-gray-200/80 to-transparent' : 'bg-linear-to-r from-red-500 via-gray-200/80 to-transparent'"
                    aria-hidden="true"
                />

                <!-- 4 primary stats -->
                <dl class="mt-10 grid grid-cols-2 gap-y-10 gap-x-10 pb-4 md:grid-cols-4 md:gap-x-14">
                    <!-- CAGR -->
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">CAGR</dt>
                        <dd class="mt-2 flex items-baseline gap-1">
                            <span
                                class="text-5xl font-bold tabular-nums leading-none tracking-tight"
                                :class="summaryMetrics.cagr >= 0 ? 'text-emerald-700' : 'text-red-700'"
                            >
                                {{ (summaryMetrics.cagr * 100).toFixed(2) }}
                            </span>
                            <span class="text-2xl font-semibold text-gray-300">%</span>
                        </dd>
                        <dd class="mt-3 text-[11px] text-gray-500">over {{ backtestYears }} years</dd>
                    </div>

                    <!-- Total return -->
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Total return</dt>
                        <dd
                            class="mt-2 text-5xl font-bold tabular-nums leading-none tracking-tight"
                            :class="totalReturn >= 0 ? 'text-emerald-700' : 'text-red-700'"
                        >
                            {{ totalReturn >= 0 ? '+' : '' }}{{ (totalReturn * 100).toFixed(2) }}%
                        </dd>
                        <dd class="mt-3 text-[11px] text-gray-500">compounded</dd>
                    </div>

                    <!-- Final value -->
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">Final value</dt>
                        <dd class="mt-2 text-5xl font-bold tabular-nums leading-none tracking-tight text-gray-900">
                            {{ formatCurrencyShort(summaryMetrics.final_value) }}
                        </dd>
                        <dd class="mt-3 text-[11px] text-gray-500">
                            from {{ formatCurrencyShort(backtest.initial_capital) }}
                            <span class="mx-1 text-gray-300">·</span>
                            <span class="font-semibold text-gray-700">{{ growthMultiplier }}× growth</span>
                        </dd>
                    </div>

                    <!-- vs Nifty 50 (alpha) -->
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">vs Nifty 50</dt>
                        <Deferred data="defaultBenchmark">
                            <template #fallback>
                                <dd class="mt-2 h-12 w-32 animate-pulse rounded bg-gray-100" aria-hidden="true" />
                            </template>
                            <dd class="mt-2 flex items-center gap-2 text-5xl font-bold tabular-nums leading-none tracking-tight">
                                <template v-if="benchmarkDelta === null">
                                    <span class="text-gray-400">—</span>
                                </template>
                                <template v-else>
                                    <ArrowTrendingUpIcon
                                        v-if="benchmarkDelta >= 0"
                                        class="h-7 w-7 shrink-0 text-emerald-700"
                                        aria-hidden="true"
                                    />
                                    <ArrowTrendingDownIcon
                                        v-else
                                        class="h-7 w-7 shrink-0 text-red-700"
                                        aria-hidden="true"
                                    />
                                    <span :class="benchmarkDelta >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                        {{ benchmarkDelta >= 0 ? '+' : '' }}{{ (benchmarkDelta * 100).toFixed(2) }}%
                                    </span>
                                </template>
                            </dd>
                        </Deferred>
                        <dd class="mt-3 text-[11px] text-gray-500">alpha</dd>
                    </div>
                </dl>
            </div>

            <!-- SCORECARD + ACTIVITY STRIP (card) -->
            <div class="relative overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70">
                <!-- SCORECARD: two column list (Risk | Quality) with soft tonal accents -->
                <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x lg:divide-gray-200/60">
                    <!-- Risk column -->
                    <div class="relative overflow-hidden bg-linear-to-br from-rose-50/70 via-white to-white px-8 py-6">
                        <div class="pointer-events-none absolute -left-12 -top-12 h-32 w-32 rounded-full bg-rose-200/30 blur-3xl" aria-hidden="true" />
                        <div class="relative mb-3 flex items-center gap-2 border-b border-rose-200/50 pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-100 ring-1 ring-rose-200/70">
                                <ShieldExclamationIcon class="h-3.5 w-3.5 text-rose-600" aria-hidden="true" />
                            </span>
                            <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-rose-700">Risk</h3>
                        </div>
                        <dl class="relative divide-y divide-rose-100/60">
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">Max drawdown</dt>
                                <dd class="text-right">
                                    <div class="text-xl font-bold tabular-nums text-red-700">
                                        {{ formatPercent(summaryMetrics.max_drawdown) }}
                                    </div>
                                    <div v-if="summaryMetrics.max_drawdown_start_date" class="mt-0.5 text-[11px] font-medium text-rose-600/70">
                                        {{ formatDrawdownPeriod(summaryMetrics.max_drawdown_start_date, summaryMetrics.max_drawdown_end_date) }}
                                    </div>
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">Sharpe ratio</dt>
                                <dd class="text-xl font-bold tabular-nums" :class="sharpeValueClass">
                                    {{ summaryMetrics.sharpe_ratio !== null ? Number(summaryMetrics.sharpe_ratio).toFixed(2) : '—' }}
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">Ulcer index</dt>
                                <dd class="text-xl font-bold tabular-nums text-slate-900">
                                    {{ summaryMetrics.ulcer_index !== null ? Number(summaryMetrics.ulcer_index).toFixed(2) : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Quality column -->
                    <div class="relative overflow-hidden border-t border-gray-200/60 bg-linear-to-br from-sky-50/70 via-white to-white px-8 py-6 lg:border-t-0">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-32 w-32 rounded-full bg-sky-200/30 blur-3xl" aria-hidden="true" />
                        <div class="relative mb-3 flex items-center gap-2 border-b border-sky-200/50 pb-3">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-sky-100 ring-1 ring-sky-200/70">
                                <SparklesIcon class="h-3.5 w-3.5 text-sky-600" aria-hidden="true" />
                            </span>
                            <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-sky-700">Quality</h3>
                        </div>
                        <dl class="relative divide-y divide-sky-100/60">
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">Win rate</dt>
                                <dd class="text-right">
                                    <div class="text-xl font-bold tabular-nums" :class="winRateValueClass">
                                        {{ summaryMetrics.winners_percentage !== null ? Number(summaryMetrics.winners_percentage).toFixed(1) + '%' : '—' }}
                                    </div>
                                    <div v-if="winnersLosersLabel" class="mt-0.5 text-[11px] font-medium text-sky-600/70">
                                        {{ winnersLosersLabel }}
                                    </div>
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">Profit factor</dt>
                                <dd class="text-xl font-bold tabular-nums" :class="profitFactorValueClass">
                                    {{ summaryMetrics.profit_factor !== null ? Number(summaryMetrics.profit_factor).toFixed(2) + '×' : '—' }}
                                </dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-600">K-ratio</dt>
                                <dd class="text-xl font-bold tabular-nums" :class="kRatioValueClass">
                                    {{ summaryMetrics.k_ratio !== null ? Number(summaryMetrics.k_ratio).toFixed(2) : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- ACTIVITY STRIP -->
                <div class="relative flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-100 bg-gray-50/60 px-6 py-3 text-xs text-gray-500">
                    <span>
                        <span class="font-semibold text-gray-800">{{ summaryMetrics.total_trades }}</span>
                        trades
                        <span v-if="trades && trades.length > 0" class="text-gray-400">({{ buyCount }} buys · {{ sellCount }} sells)</span>
                    </span>
                    <span class="text-gray-300" aria-hidden="true">·</span>
                    <span>
                        <span class="font-semibold text-amber-700">{{ formatCurrencyShort(summaryMetrics.total_charges_paid) }}</span>
                        charges
                    </span>
                    <template v-if="dailySnapshots && dailySnapshots.length > 0">
                        <span class="text-gray-300" aria-hidden="true">·</span>
                        <span>
                            <span class="font-semibold text-gray-800">{{ cashStats.avg.toFixed(1) }}%</span>
                            avg cash
                        </span>
                    </template>
                </div>
            </div>

            <!-- NAV Chart (DEFERRED — dailySnapshots) -->
            <Deferred data="dailySnapshots">
                <template #fallback>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                            <div class="h-8 w-48 animate-pulse rounded bg-gray-100"></div>
                        </div>
                        <div class="h-[350px] animate-pulse rounded-lg bg-gray-100"></div>
                    </div>
                </template>
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
            </Deferred>

            <!-- Drawdown Chart (DEFERRED — dailySnapshots, same group, already loaded) -->
            <Deferred data="dailySnapshots">
                <template #fallback>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <div class="mb-4 h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                        <div class="h-[200px] animate-pulse rounded-lg bg-gray-100"></div>
                    </div>
                </template>
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Drawdown</h2>
                    <DrawdownChart :daily-snapshots="dailySnapshots" />
                </div>
            </Deferred>

            <!-- Cash Allocation Chart (DEFERRED — dailySnapshots, full width) -->
            <Deferred data="dailySnapshots">
                <template #fallback>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <div class="mb-4 h-4 w-48 animate-pulse rounded bg-gray-200"></div>
                        <div class="h-[250px] animate-pulse rounded-lg bg-gray-100"></div>
                    </div>
                </template>
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Cash Allocation Over Time</h2>
                    <CashAllocationChart :daily-snapshots="dailySnapshots" />
                </div>
            </Deferred>

            <!-- Monthly Returns Heatmap (DEFERRED — dailySnapshots) -->
            <Deferred data="dailySnapshots">
                <template #fallback>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <div class="mb-4 h-4 w-32 animate-pulse rounded bg-gray-200"></div>
                        <div class="space-y-2">
                            <div v-for="n in 4" :key="n" class="h-8 animate-pulse rounded bg-gray-100"></div>
                        </div>
                    </div>
                </template>
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
            </Deferred>

            <!-- Rolling Returns (IMMEDIATE — from summaryMetrics) -->
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
                                <th class="pb-2 text-right font-medium text-gray-500">Median</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Max</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="period in rollingPeriods" :key="period.key">
                                <td class="py-2.5 font-medium text-gray-900">{{ period.label }}</td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).min)">
                                    {{ formatPercent(rollingStats(period.key).min) }}
                                </td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).median)">
                                    {{ formatPercent(rollingStats(period.key).median) }}
                                </td>
                                <td class="py-2.5 text-right" :class="rollingReturnColor(rollingStats(period.key).max)">
                                    {{ formatPercent(rollingStats(period.key).max) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Gainers & Losers (IMMEDIATE — from summaryMetrics) -->
            <div
                v-if="summaryMetrics.stock_performance && summaryMetrics.stock_performance.length > 0"
                class="grid grid-cols-1 gap-6 lg:grid-cols-2"
            >
                <!-- Top 20 Gainers -->
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-700">Top 20 Gainers</h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left font-medium text-gray-500">Symbol</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Invested</th>
                                <th class="pb-2 text-right font-medium text-gray-500">P&L</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Return</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="stock in topGainers" :key="stock.symbol">
                                <td class="py-2 text-gray-900">
                                    {{ stock.symbol }}
                                    <span v-if="stock.still_held" class="ml-1 text-xs text-blue-500">(held)</span>
                                </td>
                                <td class="py-2 text-right text-gray-700">{{ formatCurrencyShort(stock.buy_value) }}</td>
                                <td class="py-2 text-right font-semibold text-green-700">{{ formatCurrencyShort(stock.net_pnl) }}</td>
                                <td class="py-2 text-right font-semibold text-green-700">+{{ stock.pnl_pct.toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Top 20 Losers -->
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-red-700">Top 20 Losers</h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="pb-2 text-left font-medium text-gray-500">Symbol</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Invested</th>
                                <th class="pb-2 text-right font-medium text-gray-500">P&L</th>
                                <th class="pb-2 text-right font-medium text-gray-500">Return</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="stock in topLosers" :key="stock.symbol">
                                <td class="py-2 text-gray-900">
                                    {{ stock.symbol }}
                                    <span v-if="stock.still_held" class="ml-1 text-xs text-blue-500">(held)</span>
                                </td>
                                <td class="py-2 text-right text-gray-700">{{ formatCurrencyShort(stock.buy_value) }}</td>
                                <td class="py-2 text-right font-semibold text-red-700">{{ formatCurrencyShort(stock.net_pnl) }}</td>
                                <td class="py-2 text-right font-semibold text-red-700">{{ stock.pnl_pct.toFixed(1) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Trade Log (DEFERRED — trades, separate parallel group) -->
            <Deferred data="trades">
                <template #fallback>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <div class="mb-4 h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                        <div class="space-y-2">
                            <div v-for="n in 8" :key="n" class="h-8 animate-pulse rounded bg-gray-100"></div>
                        </div>
                    </div>
                </template>
                <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Trade Log
                        <span class="ml-2 text-xs font-normal normal-case text-gray-400">({{ trades.length }} trades)</span>
                    </h2>
                    <TradeLogTable :trades="trades" />
                </div>
            </Deferred>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ArrowTrendingDownIcon, ArrowTrendingUpIcon, ShieldExclamationIcon, SparklesIcon } from '@heroicons/vue/20/solid';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import BacktestEquitySparkline from '@/Pages/Backtests/partials/BacktestEquitySparkline.vue';
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
    defaultBenchmark: BenchmarkPoint[];
    trades: BacktestTrade[];
    benchmarkOptions: SelectOption[];
}>();

// --- Polling ---

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.backtest.status === 'running' || props.backtest.status === 'pending') {
        startPolling();
    }
});

onUnmounted(() => stopPolling());

watch(() => props.backtest.status, (newStatus, oldStatus) => {
    if (newStatus === 'completed' && oldStatus !== 'completed') {
        // Job just finished — full reload triggers deferred prop loading for charts/trades
        stopPolling();
        router.reload();
    } else if (newStatus === 'running' || newStatus === 'pending') {
        if (!pollInterval) {
            startPolling();
        }
    } else {
        stopPolling();
    }
});

function startPolling(): void {
    pollInterval = setInterval(() => {
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

// Seed benchmarkData from the preloaded defaultBenchmark so the NAV chart doesn't need a redundant fetch.
watch(
    () => props.defaultBenchmark,
    (value) => {
        if (selectedBenchmark.value === 'nifty-50' && Array.isArray(value) && value.length > 0) {
            benchmarkData.value = value;
        }
    },
    { immediate: true },
);

watch(selectedBenchmark, (slug) => {
    if (slug === 'nifty-50' && Array.isArray(props.defaultBenchmark) && props.defaultBenchmark.length > 0) {
        benchmarkData.value = props.defaultBenchmark;
        return;
    }
    fetchBenchmark(slug);
});

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

const progressStageLabel = computed(() => {
    const p = props.backtest.progress;
    if (p === 0) return 'Queued';
    if (p < 20) return 'Warming up';
    if (p < 50) return 'Crunching numbers';
    if (p < 80) return 'Simulating portfolio';
    if (p < 95) return 'Almost there';
    return 'Finalizing metrics';
});

const growthMultiplier = computed(() => {
    if (!props.summaryMetrics || props.backtest.initial_capital === 0) return '0.0';
    return (props.summaryMetrics.final_value / props.backtest.initial_capital).toFixed(1);
});

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

const buyCount = computed(() => (props.trades ?? []).filter((t) => t.trade_type === 'buy').length);
const sellCount = computed(() => (props.trades ?? []).filter((t) => t.trade_type === 'sell').length);

const cashStats = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return { min: 0, avg: 0, max: 0 };
    const pcts = props.dailySnapshots.map((s) => {
        const total = Number(s.total_value);
        return total > 0 ? (Number(s.cash) / total) * 100 : 0;
    });
    return { min: Math.min(...pcts), avg: pcts.reduce((a, b) => a + b, 0) / pcts.length, max: Math.max(...pcts) };
});

// --- Computed: benchmark delta for hero chip (always Nifty 50) ---

const benchmarkDelta = computed<number | null>(() => {
    const bench = props.defaultBenchmark;
    if (!Array.isArray(bench) || bench.length < 2 || !props.summaryMetrics) return null;
    const first = bench[0];
    const last = bench[bench.length - 1];
    if (!first || !last || first.nav <= 0) return null;
    const years = (new Date(last.date).getTime() - new Date(first.date).getTime()) / (365.25 * 24 * 60 * 60 * 1000);
    if (years <= 0) return null;
    const benchmarkCagr = Math.pow(last.nav / first.nav, 1 / years) - 1;
    return props.summaryMetrics.cagr - benchmarkCagr;
});

// --- Scorecard value classes (existing red/green logic, extracted for readability) ---

const sharpeValueClass = computed<string>(() => {
    const v = props.summaryMetrics?.sharpe_ratio;
    if (v === null || v === undefined) return 'text-gray-400';
    const n = Number(v);
    if (n >= 1) return 'text-emerald-700';
    if (n >= 0) return 'text-slate-900';
    return 'text-red-700';
});

const winRateValueClass = computed<string>(() => {
    const v = props.summaryMetrics?.winners_percentage;
    if (v === null || v === undefined) return 'text-gray-400';
    return Number(v) >= 50 ? 'text-emerald-700' : 'text-red-700';
});

const profitFactorValueClass = computed<string>(() => {
    const v = props.summaryMetrics?.profit_factor;
    if (v === null || v === undefined) return 'text-gray-400';
    return Number(v) >= 1 ? 'text-amber-700' : 'text-red-700';
});

const kRatioValueClass = computed<string>(() => {
    const v = props.summaryMetrics?.k_ratio;
    if (v === null || v === undefined) return 'text-gray-400';
    return Number(v) > 0 ? 'text-indigo-700' : 'text-red-700';
});

const winnersLosersLabel = computed<string | null>(() => {
    const pct = props.summaryMetrics?.winners_percentage;
    const total = props.summaryMetrics?.total_trades;
    if (pct === null || pct === undefined || !total) return null;
    const winners = Math.round((Number(pct) / 100) * total);
    return `${winners} winners of ${total}`;
});

const totalReturn = computed<number>(() =>
    props.summaryMetrics ? (props.summaryMetrics.final_value - props.backtest.initial_capital) / props.backtest.initial_capital : 0,
);

// --- Helpers ---

function formatDrawdownPeriod(startIso: string, endIso: string | null): string {
    if (!endIso) return formatDate(startIso);
    const start = new Date(startIso);
    const end = new Date(endIso);
    if (start.getFullYear() === end.getFullYear()) {
        const startStr = start.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        const endStr = end.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        return `${startStr} — ${endStr}`;
    }
    return `${formatDate(startIso)} — ${formatDate(endIso)}`;
}

// --- Computed: rolling returns ---

const rollingPeriods = computed(() => {
    const periods: { key: 'one_year' | 'three_year' | 'five_year'; label: string }[] = [];
    if (props.summaryMetrics?.rolling_returns_one_year?.length) periods.push({ key: 'one_year', label: '1 Year' });
    if (props.summaryMetrics?.rolling_returns_three_year?.length) periods.push({ key: 'three_year', label: '3 Year' });
    if (props.summaryMetrics?.rolling_returns_five_year?.length) periods.push({ key: 'five_year', label: '5 Year' });
    return periods;
});

const hasRollingReturns = computed(() => rollingPeriods.value.length > 0);

function rollingStats(period: 'one_year' | 'three_year' | 'five_year'): { min: number; median: number; max: number } {
    const data = props.summaryMetrics?.[`rolling_returns_${period}`];
    if (!data || data.length === 0) return { min: 0, median: 0, max: 0 };
    const sorted = data.map((d) => d.return).sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    const median = sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
    return {
        min: sorted[0],
        median,
        max: sorted[sorted.length - 1],
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

const topGainers = computed(() => {
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf) return [];
    return perf.filter((s) => s.net_pnl > 0).slice(0, 20);
});

const topLosers = computed(() => {
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf) return [];
    return [...perf].filter((s) => s.net_pnl < 0).sort((a, b) => a.net_pnl - b.net_pnl).slice(0, 20);
});

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
