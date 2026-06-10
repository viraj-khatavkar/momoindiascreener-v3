<template>
    <div>
        <Head :title="backtest.name" />

        <!-- Header -->
        <div class="flex items-start justify-between">
            <PageHeader :description="backtest.status === 'completed' ? backtestPeriod : 'Configure, run, and analyze your backtest'">
                {{ backtest.name }}
            </PageHeader>
            <div class="flex items-center gap-3">
                <span
                    :class="statusBadgeClass(backtest.status)"
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                >
                    {{ backtest.status }}
                </span>
                <button
                    type="button"
                    :disabled="backtest.status === 'running' || form.processing"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-75"
                    @click="saveAndRun"
                >
                    <PlayIcon class="h-4 w-4" aria-hidden="true" />
                    {{ backtest.status === 'running' ? 'Running…' : form.isDirty ? 'Save & Run' : 'Run Backtest' }}
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-2 border-b border-gray-200">
            <nav class="-mb-px flex gap-6" aria-label="Tabs">
                <button
                    type="button"
                    class="cursor-pointer border-b-2 px-1 py-3 text-sm font-medium"
                    :class="activeTab === 'results' ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    @click="setTab('results')"
                >
                    Results
                </button>
                <button
                    type="button"
                    class="cursor-pointer border-b-2 px-1 py-3 text-sm font-medium"
                    :class="activeTab === 'settings' ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                    @click="setTab('settings')"
                >
                    <span class="inline-flex items-center gap-1.5">
                        Settings
                        <span v-if="form.isDirty" class="h-1.5 w-1.5 rounded-full bg-amber-500" title="Unsaved changes"></span>
                    </span>
                </button>
            </nav>
        </div>

        <!-- ============ RESULTS TAB ============ -->
        <div v-show="activeTab === 'results'">
            <!-- Settings changed since this run -->
            <div
                v-if="settingsChangedSinceRun"
                class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
            >
                <p class="text-sm text-amber-800">
                    <span class="font-semibold">Settings changed since this run.</span>
                    The results below were produced with older settings.
                </p>
                <button
                    type="button"
                    :disabled="form.processing"
                    class="cursor-pointer rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-75"
                    @click="saveAndRun"
                >
                    Run with new settings
                </button>
            </div>

            <!-- Strategy rules -->
            <div class="mt-6 rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Strategy Rules</h2>
                    <button
                        type="button"
                        class="cursor-pointer text-xs font-medium text-purple-600 hover:text-purple-700 hover:underline"
                        @click="setTab('settings')"
                    >
                        Edit settings →
                    </button>
                </div>
                <BacktestStrategyRules :backtest="backtest" />
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
                    This backtest hasn't been run yet. Review the rules above, tweak them in the
                    <button type="button" class="cursor-pointer font-semibold text-blue-700 underline" @click="setTab('settings')">Settings tab</button>,
                    then click "Run Backtest".
                </InfoAlert>
            </div>

            <!-- Completed state -->
            <div v-else-if="backtest.status === 'completed' && summaryMetrics" class="mt-6 space-y-6">

                <!-- Section navigation -->
                <nav class="sticky top-0 z-20 flex gap-1 overflow-x-auto rounded-lg border border-gray-200 bg-white/95 px-2 py-1.5 backdrop-blur" aria-label="Results sections">
                    <button
                        v-for="section in resultSections"
                        :key="section.id"
                        type="button"
                        class="cursor-pointer whitespace-nowrap rounded-md px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                        @click="scrollToSection(section.id)"
                    >
                        {{ section.label }}
                    </button>
                </nav>

                <!-- EDITORIAL HERO: colored top rule + generous typography -->
                <div id="bt-overview" class="scroll-mt-14">
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
                                    <dt class="text-sm font-medium text-gray-600">Sortino ratio</dt>
                                    <dd class="text-xl font-bold tabular-nums" :class="sortinoValueClass">
                                        {{ sortinoRatio !== null ? sortinoRatio.toFixed(2) : '—' }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-3">
                                    <dt class="text-sm font-medium text-gray-600">Calmar ratio</dt>
                                    <dd class="text-xl font-bold tabular-nums" :class="calmarValueClass">
                                        {{ calmarRatio !== null ? calmarRatio.toFixed(2) : '—' }}
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
                            <span v-if="chargesPctOfFinal !== null" class="text-gray-400">({{ chargesPctOfFinal }}% of final value)</span>
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
                <div id="bt-nav" class="scroll-mt-14">
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
                                <a
                                    :href="`/backtests/${backtest.id}/csv/nav`"
                                    class="ml-2 text-xs font-medium text-purple-600 hover:underline"
                                >
                                    CSV
                                </a>
                            </div>
                        </div>
                        <BacktestNavChart :daily-snapshots="dailySnapshots" :benchmark-data="benchmarkData" :sync-group="chartSync" />

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

                </div>

                <!-- Drawdown Chart (DEFERRED — dailySnapshots, same group, already loaded) -->
                <div id="bt-drawdown" class="scroll-mt-14">
                <Deferred data="dailySnapshots">
                    <template #fallback>
                        <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                            <div class="mb-4 h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                            <div class="h-[200px] animate-pulse rounded-lg bg-gray-100"></div>
                        </div>
                    </template>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Drawdown</h2>
                        <DrawdownChart
                            :daily-snapshots="dailySnapshots"
                            :sync-group="chartSync"
                            :max-drawdown-start-date="summaryMetrics?.max_drawdown_start_date"
                            :max-drawdown-end-date="summaryMetrics?.max_drawdown_end_date"
                        />

                        <!-- Deepest drawdown episodes -->
                        <div v-if="topDrawdowns.length > 0" class="mt-5">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Deepest Drawdowns</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="pb-2 pr-4 text-right font-medium text-gray-500">Depth</th>
                                            <th class="px-4 pb-2 text-left font-medium text-gray-500">Peak</th>
                                            <th class="px-4 pb-2 text-left font-medium text-gray-500">Trough</th>
                                            <th class="px-4 pb-2 text-left font-medium text-gray-500">Recovered</th>
                                            <th class="pb-2 pl-4 text-right font-medium text-gray-500">Underwater</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="dd in topDrawdowns" :key="dd.peakDate">
                                            <td class="whitespace-nowrap py-2 pr-4 text-right font-semibold tabular-nums text-red-700">
                                                {{ (dd.depth * 100).toFixed(1) }}%
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ formatDate(dd.peakDate) }}</td>
                                            <td class="whitespace-nowrap px-4 py-2 text-gray-700">{{ formatDate(dd.troughDate) }}</td>
                                            <td class="whitespace-nowrap px-4 py-2" :class="dd.recoveryDate ? 'text-gray-700' : 'font-medium text-amber-600'">
                                                {{ dd.recoveryDate ? formatDate(dd.recoveryDate) : 'Not yet' }}
                                            </td>
                                            <td class="whitespace-nowrap py-2 pl-4 text-right tabular-nums text-gray-600">
                                                {{ dd.daysUnderwater.toLocaleString('en-IN') }} days
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </Deferred>
                </div>

                <!-- Cash Allocation Chart (DEFERRED — dailySnapshots, full width) -->
                <div id="bt-cash" class="scroll-mt-14">
                <Deferred data="dailySnapshots">
                    <template #fallback>
                        <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                            <div class="mb-4 h-4 w-48 animate-pulse rounded bg-gray-200"></div>
                            <div class="h-[250px] animate-pulse rounded-lg bg-gray-100"></div>
                        </div>
                    </template>
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Cash Allocation Over Time</h2>
                        <CashAllocationChart :daily-snapshots="dailySnapshots" :sync-group="chartSync" />
                    </div>
                </Deferred>
                </div>

                <!-- Monthly Returns Heatmap (DEFERRED — dailySnapshots) -->
                <div id="bt-monthly" class="scroll-mt-14">
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
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">Nifty 50</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">α</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in monthlyReturns" :key="row.year">
                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-900">{{ row.year }}</td>
                                        <td
                                            v-for="(val, idx) in row.months"
                                            :key="idx"
                                            class="whitespace-nowrap px-3 py-2 text-right"
                                            :class="monthCellClass(val)"
                                        >
                                            {{ val === null ? '-' : (val >= 0 ? '+' : '') + (val * 100).toFixed(1) + '%' }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2 text-right font-semibold"
                                            :class="row.yearReturn >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        >
                                            {{ (row.yearReturn >= 0 ? '+' : '') + (row.yearReturn * 100).toFixed(1) + '%' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right text-gray-600">
                                            {{ row.benchReturn === null ? '—' : (row.benchReturn >= 0 ? '+' : '') + (row.benchReturn * 100).toFixed(1) + '%' }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2 text-right font-semibold"
                                            :class="row.alpha === null ? 'text-gray-300' : row.alpha >= 0 ? 'text-emerald-700' : 'text-red-700'"
                                        >
                                            {{ row.alpha === null ? '—' : (row.alpha >= 0 ? '+' : '') + (row.alpha * 100).toFixed(1) + '%' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </Deferred>

                </div>

                <!-- Rolling Returns (IMMEDIATE — from summaryMetrics) -->
                <div id="bt-rolling" class="grid scroll-mt-14 grid-cols-1 gap-6">
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
                                    <th class="pb-2 text-right font-medium text-gray-500">% Positive</th>
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
                                    <td class="py-2.5 text-right tabular-nums text-gray-700">
                                        {{ rollingStats(period.key).positivePct.toFixed(0) }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Positions: final holdings + top gainers & losers -->
                <div id="bt-positions" class="scroll-mt-14 space-y-6">

                <!-- Final Holdings (IMMEDIATE — from summaryMetrics) -->
                <div v-if="finalHoldings.length > 0" class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                            Final Holdings
                            <span class="ml-2 text-xs font-normal normal-case text-gray-400">({{ finalHoldings.length }} positions on the last day)</span>
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="pb-2 pr-4 text-left font-medium text-gray-500">Symbol</th>
                                    <th class="px-4 pb-2 text-left font-medium text-gray-500">Entry</th>
                                    <th class="px-4 pb-2 text-right font-medium text-gray-500">Held</th>
                                    <th class="px-4 pb-2 text-right font-medium text-gray-500">Invested</th>
                                    <th class="px-4 pb-2 text-right font-medium text-gray-500">Value</th>
                                    <th class="px-4 pb-2 text-right font-medium text-gray-500">Weight</th>
                                    <th class="pb-2 pl-4 text-right font-medium text-gray-500">P&L</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="position in finalHoldings" :key="`${position.symbol}-${position.entry_date}`" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap py-2 pr-4">
                                        <a
                                            :href="`/instruments/${position.symbol}`"
                                            target="_blank"
                                            class="font-medium text-gray-900 hover:text-purple-700 hover:underline"
                                        >
                                            {{ position.symbol }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ formatShortDate(position.entry_date) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-gray-600">{{ formatHoldingPeriod(position.holding_days) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-600">{{ formatCurrencyShort(position.buy_value) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums text-gray-900">{{ formatCurrencyShort(position.unrealized_value) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-700">{{ holdingWeight(position.unrealized_value) }}</td>
                                    <td
                                        class="whitespace-nowrap py-2 pl-4 text-right font-semibold tabular-nums"
                                        :class="position.net_pnl >= 0 ? 'text-green-700' : 'text-red-700'"
                                    >
                                        {{ position.pnl_pct >= 0 ? '+' : '' }}{{ position.pnl_pct.toFixed(1) }}%
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-gray-200">
                                    <td class="py-2 pr-4 font-medium text-gray-500" colspan="4">Cash</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums text-gray-900">{{ formatCurrencyShort(finalCash) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-700">{{ holdingWeight(finalCash) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
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
                        <ul class="divide-y divide-gray-100">
                            <li
                                v-for="(position, index) in topGainers"
                                :key="`g-${index}-${position.symbol}-${position.entry_date}`"
                                class="flex items-start justify-between gap-3 py-2.5 text-sm"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <a
                                            :href="`/instruments/${position.symbol}`"
                                            target="_blank"
                                            class="truncate font-medium text-gray-900 hover:text-purple-700 hover:underline"
                                        >{{ position.symbol }}</a>
                                        <span
                                            v-if="position.still_held"
                                            class="rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600"
                                        >held</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        {{ formatShortDate(position.entry_date) }}
                                        <span class="text-gray-300">→</span>
                                        {{ position.exit_date ? formatShortDate(position.exit_date) : 'now' }}
                                        <span class="mx-1 text-gray-300">·</span>
                                        <span class="font-medium text-gray-600">{{ formatHoldingPeriod(position.holding_days) }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-gray-400">
                                        {{ formatCurrencyShort(position.buy_value) }} invested
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="font-semibold tabular-nums text-green-700">+{{ position.pnl_pct.toFixed(1) }}%</div>
                                    <div class="mt-0.5 text-xs tabular-nums text-green-600">{{ formatCurrencyShort(position.net_pnl) }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Top 20 Losers -->
                    <div class="rounded-xl bg-white p-6 shadow-xs ring-1 ring-gray-100">
                        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-red-700">Top 20 Losers</h2>
                        <ul class="divide-y divide-gray-100">
                            <li
                                v-for="(position, index) in topLosers"
                                :key="`l-${index}-${position.symbol}-${position.entry_date}`"
                                class="flex items-start justify-between gap-3 py-2.5 text-sm"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <a
                                            :href="`/instruments/${position.symbol}`"
                                            target="_blank"
                                            class="truncate font-medium text-gray-900 hover:text-purple-700 hover:underline"
                                        >{{ position.symbol }}</a>
                                        <span
                                            v-if="position.still_held"
                                            class="rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600"
                                        >held</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        {{ formatShortDate(position.entry_date) }}
                                        <span class="text-gray-300">→</span>
                                        {{ position.exit_date ? formatShortDate(position.exit_date) : 'now' }}
                                        <span class="mx-1 text-gray-300">·</span>
                                        <span class="font-medium text-gray-600">{{ formatHoldingPeriod(position.holding_days) }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-gray-400">
                                        {{ formatCurrencyShort(position.buy_value) }} invested
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="font-semibold tabular-nums text-red-700">{{ position.pnl_pct.toFixed(1) }}%</div>
                                    <div class="mt-0.5 text-xs tabular-nums text-red-600">{{ formatCurrencyShort(position.net_pnl) }}</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                </div>

                <!-- Trade Log (DEFERRED — trades, separate parallel group) -->
                <div id="bt-trades" class="scroll-mt-14">
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
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                                Trade Log
                                <span class="ml-2 text-xs font-normal normal-case text-gray-400">({{ trades.length }} trades)</span>
                            </h2>
                            <a
                                :href="`/backtests/${backtest.id}/csv/trades`"
                                class="text-xs font-medium text-purple-600 hover:underline"
                            >
                                Download CSV
                            </a>
                        </div>
                        <TradeLogTable :trades="trades" />
                    </div>
                </Deferred>
                </div>
            </div>
        </div>

        <!-- ============ SETTINGS TAB ============ -->
        <div v-show="activeTab === 'settings'" class="mt-6">
            <BacktestSettingsForm
                :form="form"
                :running="backtest.status === 'running'"
                :indices="indices"
                :sort-by-options="sortByOptions"
                :apply-filters-on-options="applyFiltersOnOptions"
                :custom-filter-value-options="customFilterValueOptions"
                :custom-filter-comparator-options="customFilterComparatorOptions"
                :rebalance-frequency-options="rebalanceFrequencyOptions"
                :weightage-options="weightageOptions"
                :cash-call-options="cashCallOptions"
                :cash-call-index-options="cashCallIndexOptions"
                @save="save"
                @save-and-run="saveAndRun"
                @delete="destroy"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, router, useForm } from '@inertiajs/vue3';
import { ArrowTrendingDownIcon, ArrowTrendingUpIcon, PlayIcon, ShieldExclamationIcon, SparklesIcon } from '@heroicons/vue/20/solid';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import BacktestNavChart from '@/Pages/Backtests/partials/BacktestNavChart.vue';
import BacktestSettingsForm from '@/Pages/Backtests/partials/BacktestSettingsForm.vue';
import BacktestStrategyRules from '@/Pages/Backtests/partials/BacktestStrategyRules.vue';
import CashAllocationChart from '@/Pages/Backtests/partials/CashAllocationChart.vue';
import DrawdownChart from '@/Pages/Backtests/partials/DrawdownChart.vue';
import RollingReturnsChart from '@/Pages/Backtests/partials/RollingReturnsChart.vue';
import TradeLogTable from '@/Pages/Backtests/partials/TradeLogTable.vue';
import { ChartSyncGroup } from '@/utils/chartSyncGroup';
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
    indices: SelectOption[];
    sortByOptions: SelectOption[];
    applyFiltersOnOptions: SelectOption[];
    customFilterValueOptions: SelectOption[];
    customFilterComparatorOptions: SelectOption[];
    rebalanceFrequencyOptions: SelectOption[];
    weightageOptions: SelectOption[];
    cashCallOptions: SelectOption[];
    cashCallIndexOptions: SelectOption[];
}>();

// --- Tabs ---

type Tab = 'results' | 'settings';

function initialTab(): Tab {
    const param = new URLSearchParams(window.location.search).get('tab');
    if (param === 'settings' || param === 'results') {
        return param;
    }

    return props.backtest.status === 'pending' ? 'settings' : 'results';
}

const activeTab = ref<Tab>(initialTab());

function setTab(tab: Tab): void {
    activeTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    // Keep Inertia's history state intact — only the visible URL changes.
    window.history.replaceState(window.history.state, '', url.toString());
}

// --- Settings form ---

const form = useForm({
    name: props.backtest.name,
    max_stocks_to_hold: props.backtest.max_stocks_to_hold,
    worst_rank_held: props.backtest.worst_rank_held,
    apply_hold_above_dma: props.backtest.apply_hold_above_dma,
    hold_above_dma_period: props.backtest.hold_above_dma_period,
    execute_next_trading_day: props.backtest.execute_next_trading_day,
    skip_circuit_trades: props.backtest.skip_circuit_trades,
    rebalance_frequency: props.backtest.rebalance_frequency,
    rebalance_day: props.backtest.rebalance_day,
    weightage: props.backtest.weightage,
    cash_call: props.backtest.cash_call,
    cash_call_index: props.backtest.cash_call_index,
    cash_call_dma_period: props.backtest.cash_call_dma_period,
    cash_return_rate: props.backtest.cash_return_rate,
    start_date: props.backtest.start_date ? props.backtest.start_date.substring(0, 10) : '2011-01-05',
    index: props.backtest.index,
    sort_by: props.backtest.sort_by,
    sort_direction: props.backtest.sort_direction,
    median_volume_one_year: props.backtest.median_volume_one_year,
    minimum_return_one_year: props.backtest.minimum_return_one_year,
    apply_ma: props.backtest.apply_ma,
    above_ma_200: props.backtest.above_ma_200,
    above_ma_100: props.backtest.above_ma_100,
    above_ma_50: props.backtest.above_ma_50,
    above_ma_20: props.backtest.above_ma_20,
    below_ma_200: props.backtest.below_ma_200,
    below_ma_100: props.backtest.below_ma_100,
    below_ma_50: props.backtest.below_ma_50,
    below_ma_20: props.backtest.below_ma_20,
    apply_ema: props.backtest.apply_ema,
    above_ema_200: props.backtest.above_ema_200,
    above_ema_100: props.backtest.above_ema_100,
    above_ema_50: props.backtest.above_ema_50,
    above_ema_20: props.backtest.above_ema_20,
    below_ema_200: props.backtest.below_ema_200,
    below_ema_100: props.backtest.below_ema_100,
    below_ema_50: props.backtest.below_ema_50,
    below_ema_20: props.backtest.below_ema_20,
    away_from_high_one_year: props.backtest.away_from_high_one_year,
    away_from_high_all_time: props.backtest.away_from_high_all_time,
    positive_days_percent_one_year: props.backtest.positive_days_percent_one_year,
    positive_days_percent_nine_months: props.backtest.positive_days_percent_nine_months,
    positive_days_percent_six_months: props.backtest.positive_days_percent_six_months,
    positive_days_percent_three_months: props.backtest.positive_days_percent_three_months,
    positive_days_percent_one_months: props.backtest.positive_days_percent_one_months,
    circuits_one_year: props.backtest.circuits_one_year,
    circuits_nine_months: props.backtest.circuits_nine_months,
    circuits_six_months: props.backtest.circuits_six_months,
    circuits_three_months: props.backtest.circuits_three_months,
    circuits_one_months: props.backtest.circuits_one_months,
    marketcap_from: props.backtest.marketcap_from,
    marketcap_to: props.backtest.marketcap_to,
    apply_pe: props.backtest.apply_pe,
    price_to_earnings_from: props.backtest.price_to_earnings_from,
    price_to_earnings_to: props.backtest.price_to_earnings_to,
    series_eq: props.backtest.series_eq,
    series_be: props.backtest.series_be,
    ignore_above_beta: props.backtest.ignore_above_beta,
    price_from: props.backtest.price_from,
    price_to: props.backtest.price_to,
    apply_factor_two: props.backtest.apply_factor_two,
    factor_two_sort_by: props.backtest.factor_two_sort_by,
    factor_two_sort_direction: props.backtest.factor_two_sort_direction,
    apply_factor_three: props.backtest.apply_factor_three,
    factor_three_sort_by: props.backtest.factor_three_sort_by,
    factor_three_sort_direction: props.backtest.factor_three_sort_direction,
    apply_filters_on: props.backtest.apply_filters_on,
    apply_custom_filter_one: props.backtest.apply_custom_filter_one,
    custom_filter_one_value_one: props.backtest.custom_filter_one_value_one,
    custom_filter_one_operator: props.backtest.custom_filter_one_operator,
    custom_filter_one_value_two: props.backtest.custom_filter_one_value_two,
    apply_custom_filter_two: props.backtest.apply_custom_filter_two,
    custom_filter_two_value_one: props.backtest.custom_filter_two_value_one,
    custom_filter_two_operator: props.backtest.custom_filter_two_operator,
    custom_filter_two_value_two: props.backtest.custom_filter_two_value_two,
    apply_custom_filter_three: props.backtest.apply_custom_filter_three,
    custom_filter_three_value_one: props.backtest.custom_filter_three_value_one,
    custom_filter_three_operator: props.backtest.custom_filter_three_operator,
    custom_filter_three_value_two: props.backtest.custom_filter_three_value_two,
    apply_custom_filter_four: props.backtest.apply_custom_filter_four,
    custom_filter_four_value_one: props.backtest.custom_filter_four_value_one,
    custom_filter_four_operator: props.backtest.custom_filter_four_operator,
    custom_filter_four_value_two: props.backtest.custom_filter_four_value_two,
    apply_custom_filter_five: props.backtest.apply_custom_filter_five,
    custom_filter_five_value_one: props.backtest.custom_filter_five_value_one,
    custom_filter_five_operator: props.backtest.custom_filter_five_operator,
    custom_filter_five_value_two: props.backtest.custom_filter_five_value_two,
});

function save(): void {
    form.transform((data) => data).put(`/backtests/${props.backtest.id}`, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

function saveAndRun(): void {
    form.transform((data) => ({ ...data, run: true })).put(`/backtests/${props.backtest.id}`, {
        onSuccess: () => {
            form.defaults();
            setTab('results');
        },
        onError: () => setTab('settings'),
    });
}

function destroy(): void {
    if (confirm('Are you sure you want to delete this backtest?')) {
        router.delete(`/backtests/${props.backtest.id}`);
    }
}

// --- Chart synchronization (NAV / drawdown / cash share range + crosshair) ---

const chartSync = new ChartSyncGroup();

// --- Section navigation ---

const resultSections = [
    { id: 'bt-overview', label: 'Overview' },
    { id: 'bt-nav', label: 'NAV' },
    { id: 'bt-drawdown', label: 'Drawdown' },
    { id: 'bt-cash', label: 'Cash' },
    { id: 'bt-monthly', label: 'Monthly' },
    { id: 'bt-rolling', label: 'Rolling' },
    { id: 'bt-positions', label: 'Positions' },
    { id: 'bt-trades', label: 'Trades' },
];

function scrollToSection(id: string): void {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// --- Stale results detection ---

const settingsChangedSinceRun = computed<boolean>(() => {
    if (props.backtest.status !== 'completed' || !props.backtest.completed_at) {
        return false;
    }

    return new Date(props.backtest.updated_at).getTime() > new Date(props.backtest.completed_at).getTime();
});

// --- Polling ---

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (props.backtest.status === 'running') {
        startPolling();
    }
});

onUnmounted(() => stopPolling());

watch(() => props.backtest.status, (newStatus, oldStatus) => {
    if (newStatus === 'completed' && oldStatus !== 'completed') {
        // Job just finished — reload triggers deferred prop loading for charts/trades
        stopPolling();
        router.reload();
    } else if (newStatus === 'running') {
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

function formatShortDate(value: string): string {
    return new Date(value).toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });
}

function formatHoldingPeriod(days: number): string {
    if (days < 1) return '<1d';
    if (days < 30) return `${days}d`;
    if (days < 365) return `${Math.max(1, Math.round(days / 30))}mo`;
    const years = Math.floor(days / 365);
    const months = Math.round((days % 365) / 30);
    return months === 0 ? `${years}y` : `${years}y ${months}mo`;
}

function formatCurrencyShort(value: number): string {
    const v = Number(value);
    const abs = Math.abs(v);
    if (abs >= 10000000) return '₹' + (v / 10000000).toFixed(2) + ' Cr';
    if (abs >= 100000) return '₹' + (v / 100000).toFixed(2) + ' L';
    return '₹' + v.toLocaleString('en-IN', { maximumFractionDigits: 0 });
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

// --- Scorecard value classes ---

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
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf || perf.length === 0) return null;
    const winners = perf.filter((s) => s.net_pnl > 0).length;
    return `${winners} winners of ${perf.length} positions`;
});

const totalReturn = computed<number>(() =>
    props.summaryMetrics ? (props.summaryMetrics.final_value - props.backtest.initial_capital) / props.backtest.initial_capital : 0,
);

const chargesPctOfFinal = computed<string | null>(() => {
    const m = props.summaryMetrics;
    if (!m || !m.final_value) return null;
    return ((Number(m.total_charges_paid) / Number(m.final_value)) * 100).toFixed(2);
});

// Calmar = CAGR / |max drawdown| — return earned per unit of worst pain
const calmarRatio = computed<number | null>(() => {
    const m = props.summaryMetrics;
    if (!m || !m.max_drawdown) return null;
    return m.cagr / Math.abs(Number(m.max_drawdown));
});

// Sortino = (annualized return − risk-free) / downside deviation; needs daily NAVs
const sortinoRatio = computed<number | null>(() => {
    const snaps = props.dailySnapshots;
    if (!snaps || snaps.length < 3) return null;

    const returns: number[] = [];
    for (let i = 1; i < snaps.length; i++) {
        const prev = Number(snaps[i - 1].nav);
        const curr = Number(snaps[i].nav);
        if (prev > 0) returns.push((curr - prev) / prev);
    }
    if (returns.length < 2) return null;

    const mean = returns.reduce((a, b) => a + b, 0) / returns.length;
    const downside = Math.sqrt(returns.reduce((a, r) => a + Math.min(0, r) ** 2, 0) / returns.length) * Math.sqrt(252);
    if (downside === 0) return null;

    const riskFree = Number(props.backtest.cash_return_rate) / 100;
    return (mean * 252 - riskFree) / downside;
});

const sortinoValueClass = computed<string>(() => {
    if (sortinoRatio.value === null) return 'text-gray-400';
    if (sortinoRatio.value >= 1) return 'text-emerald-700';
    if (sortinoRatio.value >= 0) return 'text-slate-900';
    return 'text-red-700';
});

const calmarValueClass = computed<string>(() => {
    if (calmarRatio.value === null) return 'text-gray-400';
    if (calmarRatio.value >= 1) return 'text-emerald-700';
    if (calmarRatio.value >= 0) return 'text-slate-900';
    return 'text-red-700';
});

// --- Drawdown episodes (top 5 deepest) ---

interface DrawdownEpisode {
    depth: number;
    peakDate: string;
    troughDate: string;
    recoveryDate: string | null;
    daysUnderwater: number;
}

function diffDays(from: string, to: string): number {
    return Math.round((new Date(to).getTime() - new Date(from).getTime()) / 86400000);
}

const topDrawdowns = computed<DrawdownEpisode[]>(() => {
    const snaps = props.dailySnapshots;
    if (!snaps || snaps.length === 0) return [];

    const episodes: DrawdownEpisode[] = [];
    let peakNav = Number(snaps[0].nav);
    let peakDate = snaps[0].date.substring(0, 10);
    let current: { depth: number; peakDate: string; troughDate: string } | null = null;

    for (const s of snaps) {
        const nav = Number(s.nav);
        const date = s.date.substring(0, 10);

        if (nav >= peakNav) {
            if (current) {
                episodes.push({ ...current, recoveryDate: date, daysUnderwater: diffDays(current.peakDate, date) });
                current = null;
            }
            peakNav = nav;
            peakDate = date;
        } else {
            const dd = peakNav > 0 ? (nav - peakNav) / peakNav : 0;
            if (!current) {
                current = { depth: dd, peakDate, troughDate: date };
            } else if (dd < current.depth) {
                current.depth = dd;
                current.troughDate = date;
            }
        }
    }

    if (current) {
        const lastDate = snaps[snaps.length - 1].date.substring(0, 10);
        episodes.push({ ...current, recoveryDate: null, daysUnderwater: diffDays(current.peakDate, lastDate) });
    }

    return episodes.sort((a, b) => a.depth - b.depth).slice(0, 5);
});

// --- Final holdings (still-open positions at the end of the run) ---

const finalHoldings = computed(() => {
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf) return [];
    return perf.filter((s) => s.still_held).sort((a, b) => b.unrealized_value - a.unrealized_value);
});

const finalCash = computed<number>(() => {
    const m = props.summaryMetrics;
    if (!m) return 0;
    const held = finalHoldings.value.reduce((sum, s) => sum + Number(s.unrealized_value), 0);
    return Math.max(0, Number(m.final_value) - held);
});

function holdingWeight(value: number): string {
    const total = Number(props.summaryMetrics?.final_value ?? 0);
    return total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '—';
}

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

function rollingStats(period: 'one_year' | 'three_year' | 'five_year'): { min: number; median: number; max: number; positivePct: number } {
    const data = props.summaryMetrics?.[`rolling_returns_${period}`];
    if (!data || data.length === 0) return { min: 0, median: 0, max: 0, positivePct: 0 };
    const sorted = data.map((d) => d.return).sort((a, b) => a - b);
    const mid = Math.floor(sorted.length / 2);
    const median = sorted.length % 2 === 0 ? (sorted[mid - 1] + sorted[mid]) / 2 : sorted[mid];
    return {
        min: sorted[0],
        median,
        max: sorted[sorted.length - 1],
        positivePct: (sorted.filter((r) => r > 0).length / sorted.length) * 100,
    };
}

// --- Computed: gainers/losers + monthly returns heatmap ---

const topGainers = computed(() => {
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf) return [];
    return [...perf].filter((s) => s.net_pnl > 0).sort((a, b) => b.pnl_pct - a.pnl_pct).slice(0, 20);
});

const topLosers = computed(() => {
    const perf = props.summaryMetrics?.stock_performance;
    if (!perf) return [];
    return [...perf].filter((s) => s.net_pnl < 0).sort((a, b) => a.pnl_pct - b.pnl_pct).slice(0, 20);
});

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Color intensity buckets so a +12% month reads differently from +0.3%
function monthCellClass(val: number | null): string {
    if (val === null) return 'text-gray-300';
    if (val >= 0.06) return 'bg-green-200 text-green-900';
    if (val >= 0.02) return 'bg-green-100 text-green-800';
    if (val >= 0) return 'bg-green-50 text-green-700';
    if (val > -0.02) return 'bg-red-50 text-red-700';
    if (val > -0.06) return 'bg-red-100 text-red-800';
    return 'bg-red-200 text-red-900';
}

// Calendar-year benchmark returns from the preloaded Nifty 50 series
const benchmarkYearlyReturns = computed<Record<string, number>>(() => {
    const bench = props.defaultBenchmark;
    if (!Array.isArray(bench) || bench.length === 0) return {};

    const byYear: Record<string, { first: number; last: number }> = {};
    for (const point of bench) {
        const year = point.date.substring(0, 4);
        if (!byYear[year]) {
            byYear[year] = { first: point.nav, last: point.nav };
        } else {
            byYear[year].last = point.nav;
        }
    }

    const years = Object.keys(byYear).sort();
    const out: Record<string, number> = {};
    years.forEach((year, idx) => {
        const base = idx > 0 ? byYear[years[idx - 1]].last : byYear[year].first;
        out[year] = base > 0 ? (byYear[year].last - base) / base : 0;
    });

    return out;
});

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
        const benchReturn = benchmarkYearlyReturns.value[year] ?? null;
        return {
            year,
            months,
            yearReturn,
            benchReturn,
            alpha: benchReturn !== null ? yearReturn - benchReturn : null,
        };
    });
});
</script>
