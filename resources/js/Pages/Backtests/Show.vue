<template>
    <div>
        <Head :title="backtest.name" />

        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
            <PageHeader
                class="mb-0! min-w-0 break-words [&>p]:mt-2"
                :description="backtest.status === 'completed' ? backtestPeriod : 'Configure, run, and analyze your backtest'"
            >
                {{ backtest.name }}
            </PageHeader>
            <div class="flex flex-wrap items-center gap-3">
                <span v-if="readOnly" class="rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">Example backtest</span>
                <span :class="statusBadgeClass(backtest.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                    {{ backtest.status }}
                </span>
                <DuplicateBacktestButton
                    v-if="!readOnly"
                    :backtest-id="backtest.id"
                    :disabled="form.isDirty || form.processing"
                    :title="form.isDirty ? 'Save settings before duplicating' : 'Copy saved settings to a new backtest'"
                />
                <button
                    v-if="!readOnly"
                    type="button"
                    data-test="save-and-run"
                    :disabled="runBlocked || form.processing"
                    class="inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-75"
                    :class="
                        reviewingCompletedRun
                            ? 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                            : 'bg-purple-600 text-white shadow-xs hover:bg-purple-500'
                    "
                    @click="saveAndRun"
                >
                    <PlayIcon class="h-4 w-4" aria-hidden="true" />
                    {{ backtest.status === 'running' ? 'Running…' : reviewingCompletedRun ? 'Re-run backtest' : 'Save and Run' }}
                </button>
            </div>
        </div>
        <p v-if="form.isDirty" class="mt-2 text-sm text-gray-500">Save settings before you duplicate this backtest.</p>
        <InfoAlert v-if="!readOnly && activeBacktest" class="mt-4">
            “{{ activeBacktest.name }}” is running. You can start another backtest after this run sequence finishes.
        </InfoAlert>

        <!-- Tabs -->
        <div v-if="!readOnly" class="mt-4 border-b border-gray-200">
            <nav class="-mb-px flex gap-6" role="tablist" aria-label="Backtest tabs">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'results'"
                    class="cursor-pointer border-b-2 px-1 py-3 text-sm font-medium"
                    :class="
                        activeTab === 'results'
                            ? 'border-purple-600 text-purple-700'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                    "
                    @click="setTab('results')"
                >
                    Results
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'settings'"
                    class="cursor-pointer border-b-2 px-1 py-3 text-sm font-medium"
                    :class="
                        activeTab === 'settings'
                            ? 'border-purple-600 text-purple-700'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                    "
                    @click="setTab('settings')"
                >
                    <span class="inline-flex items-center gap-1.5">
                        Settings
                        <span v-if="form.isDirty" class="h-1.5 w-1.5 rounded-full bg-amber-500" title="Unsaved changes"></span>
                        <span v-if="form.isDirty" class="sr-only">(unsaved changes)</span>
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
                    v-if="!readOnly"
                    type="button"
                    :disabled="runBlocked || form.processing"
                    class="cursor-pointer rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-75"
                    @click="saveAndRun"
                >
                    Run with new settings
                </button>
            </div>

            <!-- Strategy rules (collapsed to a single header row once completed) -->
            <div v-if="backtest.status !== 'completed'" class="mt-6 rounded-xl bg-slate-50 p-6 ring-1 ring-slate-200">
                <div class="flex items-center justify-between" :class="showStrategyRules ? 'mb-4' : ''">
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase hover:text-gray-700"
                        :aria-expanded="showStrategyRules"
                        @click="showStrategyRules = !showStrategyRules"
                    >
                        Strategy Rules
                        <svg
                            class="h-4 w-4 text-gray-400 transition-transform"
                            :class="showStrategyRules ? 'rotate-180' : ''"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <button
                        v-if="!readOnly"
                        type="button"
                        class="cursor-pointer text-xs font-medium text-purple-600 hover:text-purple-700 hover:underline"
                        @click="setTab('settings')"
                    >
                        Edit settings →
                    </button>
                </div>
                <BacktestStrategyRules v-if="showStrategyRules" :backtest="backtest" />
            </div>

            <!-- Running state -->
            <div v-if="backtest.status === 'running'" class="mt-6">
                <div
                    class="relative overflow-hidden rounded-2xl bg-linear-to-br from-gray-900 to-gray-950 p-8 shadow-xl ring-1 ring-white/10"
                    role="progressbar"
                    :aria-valuenow="backtest.progress > 0 ? backtest.progress : undefined"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-live="polite"
                    :aria-label="backtest.progress === 0 ? progressStageLabel : `Backtest ${backtest.progress}% complete`"
                >
                    <!-- Animated background grid -->
                    <div
                        class="pointer-events-none absolute inset-0 opacity-[0.03]"
                        style="
                            background-image:
                                linear-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                                linear-gradient(90deg, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
                            background-size: 24px 24px;
                        "
                    />

                    <!-- Glow behind progress -->
                    <div
                        class="pointer-events-none absolute -top-20 left-0 h-40 w-40 rounded-full bg-purple-500/20 blur-3xl transition-all duration-1000"
                        :style="`left: ${Math.max(0, backtest.progress - 10)}%`"
                    />

                    <!-- Top row: percentage + status -->
                    <div class="relative flex items-end justify-between">
                        <div v-if="backtest.progress > 0" class="flex items-baseline gap-3">
                            <span class="text-5xl font-black tracking-tight text-white tabular-nums">{{ backtest.progress }}</span>
                            <span class="text-lg font-medium text-white/40">%</span>
                        </div>
                        <span v-else class="text-3xl font-black tracking-tight text-white">{{ sourceIsRunning ? 'Source first' : 'Starting' }}</span>
                        <div class="flex items-center gap-2 pb-2">
                            <span class="relative flex h-2.5 w-2.5">
                                <span
                                    class="absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75 motion-safe:animate-ping"
                                ></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-purple-500"></span>
                            </span>
                            <span class="text-sm font-medium text-white/60">{{ progressStageLabel }}</span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="relative mt-6 h-3 w-full overflow-hidden rounded-full bg-white/10">
                        <div
                            v-if="backtest.progress === 0"
                            class="absolute inset-0 bg-linear-to-r from-violet-500/30 via-purple-500 to-fuchsia-500/30 motion-safe:animate-pulse"
                        />
                        <!-- Track glow -->
                        <div
                            v-else
                            class="absolute inset-y-0 left-0 rounded-full bg-linear-to-r from-violet-500 via-purple-500 to-fuchsia-500 transition-all duration-700 ease-out"
                            :style="`width: ${backtest.progress}%`"
                        />
                        <!-- Shimmer overlay -->
                        <div
                            class="absolute inset-y-0 left-0 overflow-hidden rounded-full transition-all duration-700 ease-out"
                            :style="`width: ${backtest.progress}%`"
                        >
                            <div
                                class="absolute inset-0 -translate-x-full animate-shimmer bg-linear-to-r from-transparent via-white/25 to-transparent"
                            />
                        </div>
                    </div>

                    <!-- Bottom detail chips -->
                    <div class="mt-5 flex flex-wrap items-center gap-3 text-xs">
                        <span class="rounded-full bg-white/10 px-3 py-1 font-medium text-white/50">
                            {{ progressStageLabel }}
                        </span>
                        <span v-if="sourceIsRunning" class="text-white/40">
                            This backtest will start only after the source completes successfully. You can leave this page.
                        </span>
                        <span v-else-if="backtest.progress === 0" class="text-white/40">
                            Your run will start when a worker is free. You can leave this page.
                        </span>
                        <span v-else-if="backtest.progress < 5" class="text-white/40"> Loading prices and strategy data </span>
                        <span v-else-if="backtest.progress < 95" class="text-white/30"> {{ simulatedDaysPct }}% of trading days processed </span>
                    </div>
                </div>
            </div>

            <!-- Failed state -->
            <div v-else-if="backtest.status === 'failed'" class="mt-6 space-y-4">
                <ErrorAlert>
                    This run failed. It will not retry. Select Save and Run to start a new run. If it fails again, contact support and give backtest
                    #{{ backtest.id }}.
                </ErrorAlert>
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        :disabled="runBlocked || form.processing"
                        class="cursor-pointer rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 disabled:cursor-not-allowed disabled:opacity-75"
                        @click="saveAndRun"
                    >
                        {{ form.processing ? 'Queuing…' : 'Save and Run' }}
                    </button>
                    <button
                        v-if="backtest.error_message"
                        type="button"
                        class="cursor-pointer text-xs font-medium text-gray-500 hover:text-gray-700 hover:underline"
                        :aria-expanded="showErrorDetails"
                        @click="showErrorDetails = !showErrorDetails"
                    >
                        {{ showErrorDetails ? 'Hide technical details' : 'Show technical details' }}
                    </button>
                </div>
                <pre
                    v-if="showErrorDetails && backtest.error_message"
                    class="overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs leading-relaxed text-gray-300"
                    >{{ backtest.error_message }}</pre
                >
            </div>

            <!-- Pending state -->
            <div v-else-if="backtest.status === 'pending'" class="mt-6">
                <InfoAlert>
                    This backtest hasn't been run yet. Review the rules above, tweak them in the
                    <button type="button" class="cursor-pointer font-semibold text-blue-700 underline" @click="setTab('settings')">
                        Settings tab</button
                    >, then click "Save and Run".
                </InfoAlert>
            </div>

            <!-- Completed state -->
            <div v-else-if="backtest.status === 'completed' && summaryMetrics" class="mt-6 space-y-8">
                <InfoAlert v-if="summaryMetrics.total_trades === 0"
                    >This run produced no trades. Check the filters and cash rules before running again.</InfoAlert
                >
                <nav
                    class="sticky top-26 z-20 grid grid-cols-4 gap-1 rounded-xl border border-slate-200 bg-white/95 p-1 shadow-sm backdrop-blur sm:top-16"
                    aria-label="Results sections"
                >
                    <button
                        v-for="section in resultSections"
                        :key="section.id"
                        type="button"
                        class="min-w-0 cursor-pointer rounded-lg px-2 py-3 text-sm leading-tight font-medium transition-colors sm:px-4"
                        :class="activeSection === section.id ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                        :aria-current="activeSection === section.id ? 'location' : undefined"
                        @click="scrollToSection(section.id)"
                    >
                        {{ section.label }}
                    </button>
                </nav>
                <section id="bt-overview" class="scroll-mt-48 space-y-3 sm:scroll-mt-36 sm:space-y-5" aria-labelledby="overview-heading">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 id="overview-heading" class="text-xl font-semibold tracking-tight text-slate-900">Performance overview</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ backtestDuration }} · After trading charges</p>
                        </div>
                        <div class="flex w-full items-center gap-3 sm:block sm:w-auto">
                            <label for="result-benchmark" class="shrink-0 text-xs font-medium text-slate-600 sm:mb-1 sm:block">Compare with</label>
                            <select
                                id="result-benchmark"
                                v-model="selectedBenchmark"
                                class="min-h-10 min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-purple-500 focus:ring-purple-500 sm:min-w-56"
                            >
                                <option value="">No benchmark</option>
                                <option v-for="option in benchmarkOptions" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="loadingBenchmark" class="text-sm text-purple-700" role="status">Loading {{ selectedBenchmarkName }}…</p>
                    <div
                        v-else-if="benchmarkError"
                        class="flex flex-wrap items-center gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"
                        role="status"
                    >
                        {{ benchmarkError
                        }}<button type="button" class="font-semibold underline" @click="fetchBenchmark(selectedBenchmark)">Try again</button>
                    </div>
                    <p
                        v-else-if="selectedBenchmark && dailySnapshots.length > 0 && benchmarkData.length === 0"
                        class="text-sm text-amber-800"
                        role="status"
                    >
                        No benchmark data is available for this period. Choose another benchmark.
                    </p>
                    <p v-else-if="benchmarkCoverageNotice" class="text-sm text-amber-800" role="status">{{ benchmarkCoverageNotice }}</p>
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <dl
                            aria-label="Performance statistics"
                            class="grid grid-cols-2 gap-px bg-slate-200 lg:grid-cols-4"
                            data-test="performance-metrics"
                        >
                            <ResultMetric
                                headline
                                label="CAGR"
                                :value="formatPercent(summaryMetrics.cagr)"
                                :tone="metricTone(summaryMetrics.cagr)"
                                detail="Annualised return"
                            >
                                <template v-if="benchmarkMetrics.cagr !== null" #default>
                                    <span class="flex flex-wrap items-baseline gap-x-2 tabular-nums" data-test="benchmark-comparison">
                                        <span>{{ selectedBenchmarkName }} {{ formatPercent(benchmarkMetrics.cagr) }}</span>
                                        <span
                                            v-if="benchmarkDelta !== null"
                                            class="font-semibold"
                                            :class="returnColor(benchmarkDelta)"
                                            data-test="cagr-difference"
                                        >
                                            <span class="sr-only">CAGR difference in percentage points </span
                                            >{{ formatPercentagePoints(benchmarkDelta) }}
                                        </span>
                                    </span>
                                </template>
                            </ResultMetric>
                            <ResultMetric
                                headline
                                label="Maximum drawdown"
                                :value="formatPercent(summaryMetrics.max_drawdown)"
                                :tone="metricTone(summaryMetrics.max_drawdown)"
                            >
                                <span v-if="benchmarkMetrics.maxDrawdown !== null" class="block tabular-nums">
                                    {{ selectedBenchmarkName }} {{ formatPercent(benchmarkMetrics.maxDrawdown) }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex items-baseline gap-1 text-left font-medium text-purple-700 hover:underline"
                                    @click="scrollToSection('bt-drawdown')"
                                >
                                    {{
                                        summaryMetrics.max_drawdown_end_date
                                            ? 'Trough ' + formatDate(summaryMetrics.max_drawdown_end_date)
                                            : 'View drawdown'
                                    }}
                                    <span aria-hidden="true">→</span><span class="sr-only"> · View loss and recovery</span>
                                </button>
                            </ResultMetric>
                            <ResultMetric
                                headline
                                label="Annualised volatility"
                                :tone="metricTone(annualizedVolatility, 'risk')"
                                detail="Standard deviation of daily returns"
                            >
                                <template #value>
                                    <Deferred data="dailySnapshots">
                                        <template #fallback
                                            ><span role="status"
                                                ><span class="sr-only">Loading annualised volatility</span><span aria-hidden="true">…</span></span
                                            ></template
                                        >
                                        {{ annualizedVolatility !== null ? (annualizedVolatility * 100).toFixed(1) + '%' : '—' }}
                                    </Deferred>
                                </template>
                            </ResultMetric>
                            <ResultMetric
                                headline
                                label="Sharpe ratio"
                                :value="summaryMetrics.sharpe_ratio != null ? Number(summaryMetrics.sharpe_ratio).toFixed(2) : '—'"
                                :tone="metricTone(summaryMetrics.sharpe_ratio, 'ratio')"
                                detail="Excess return over cash per unit of volatility"
                            />
                        </dl>
                        <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-t border-slate-200 px-4 py-3 text-sm text-slate-600 sm:px-5">
                            <span>Portfolio value</span>
                            <span class="font-semibold text-slate-900 tabular-nums">
                                {{ formatCurrencyShort(backtest.initial_capital) }} <span aria-hidden="true">→</span><span class="sr-only">to</span>
                                {{ formatCurrencyShort(summaryMetrics.final_value) }}
                            </span>
                            <span class="tabular-nums">{{ growthMultiplier }}×</span>
                        </p>
                        <details class="group border-t border-slate-200 px-4 sm:px-5" data-test="risk-statistics">
                            <summary class="cursor-pointer py-3 text-sm font-semibold text-slate-800">
                                Additional statistics
                                <span class="ml-2 text-xs font-normal text-slate-500 group-open:hidden">Sortino, Calmar, Ulcer index, K-ratio</span>
                            </summary>
                            <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <ResultMetric
                                    data-test="sortino-ratio"
                                    label="Sortino ratio"
                                    :value="sortinoRatio !== null ? sortinoRatio.toFixed(2) : '—'"
                                    :tone="metricTone(sortinoRatio, 'ratio')"
                                    help="Return above the configured cash return, relative to downside variation. This is the saved value from the run."
                                />
                                <ResultMetric
                                    label="Calmar ratio"
                                    :value="calmarRatio !== null ? calmarRatio.toFixed(2) : '—'"
                                    :tone="metricTone(calmarRatio, 'ratio')"
                                    help="Annualised return divided by the magnitude of the maximum drawdown."
                                />
                                <ResultMetric
                                    label="Ulcer index"
                                    :value="summaryMetrics.ulcer_index != null ? Number(summaryMetrics.ulcer_index).toFixed(2) : '—'"
                                    :tone="metricTone(summaryMetrics.ulcer_index, 'risk')"
                                    help="Measures the depth and persistence of losses below previous portfolio highs. Lower values indicate less time or depth below those highs."
                                />
                                <ResultMetric
                                    label="K-ratio"
                                    :value="summaryMetrics.k_ratio != null ? Number(summaryMetrics.k_ratio).toFixed(2) : '—'"
                                    :tone="metricTone(summaryMetrics.k_ratio, 'ratio')"
                                    help="Measures the consistency of growth in log NAV. Read it with returns, drawdown and the test period."
                                />
                            </dl>
                            <p class="py-4 text-sm text-slate-600">
                                {{ summaryMetrics.total_trades.toLocaleString('en-IN') }} transactions ·
                                {{ formatCurrencyShort(summaryMetrics.total_charges_paid) }} total charges
                                <span v-if="chargesPctOfCapital !== null">({{ chargesPctOfCapital }}% of initial capital)</span>
                            </p>
                        </details>
                    </div>
                    <details class="rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3 sm:px-5">
                        <summary class="cursor-pointer text-sm font-medium text-slate-700">
                            {{ settingsChangedSinceRun ? 'Current saved strategy rules' : 'Strategy rules' }}
                            <span v-if="!settingsChangedSinceRun" class="ml-2 text-xs font-normal text-slate-500">{{ strategySummary }}</span>
                        </summary>
                        <div class="mt-4">
                            <BacktestStrategyRules :backtest="backtest" /><button
                                v-if="!readOnly"
                                type="button"
                                class="mt-4 text-sm font-semibold text-purple-700 hover:underline"
                                @click="setTab('settings')"
                            >
                                Edit settings →
                            </button>
                        </div>
                    </details>
                    <div id="bt-nav" class="scroll-mt-48 sm:scroll-mt-36">
                        <Deferred data="dailySnapshots">
                            <template #fallback>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                    <div class="mb-4 flex items-center justify-between">
                                        <div class="h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                                        <div class="h-8 w-48 animate-pulse rounded bg-gray-100"></div>
                                    </div>
                                    <div class="h-[500px] animate-pulse rounded-lg bg-gray-100"></div>
                                </div>
                            </template>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-900">Portfolio growth</h2>
                                        <p class="mt-1 text-sm text-slate-500">
                                            NAV uses a starting base of 100. Strategy values include trading charges.
                                        </p>
                                    </div>
                                    <a
                                        v-if="!readOnly"
                                        :href="`/backtests/${backtest.id}/csv/nav`"
                                        class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-purple-700 hover:bg-purple-50"
                                        >Download NAV CSV</a
                                    >
                                </div>
                                <BacktestNavChart
                                    ref="navChart"
                                    :daily-snapshots="dailySnapshots"
                                    :benchmark-data="benchmarkData"
                                    :benchmark-name="selectedBenchmarkName"
                                    :sync-group="chartSync"
                                />

                                <p class="mt-4 text-sm text-slate-500">
                                    Strategy summary figures use the full backtest period, even when the chart is zoomed.
                                </p>
                            </div>
                        </Deferred>
                    </div>
                </section>
                <section id="bt-risk" class="scroll-mt-48 space-y-6 sm:scroll-mt-36" aria-labelledby="risk-heading">
                    <div class="flex flex-wrap items-end justify-between gap-3 border-t border-slate-200 pt-8">
                        <div>
                            <h2 id="risk-heading" class="text-2xl font-semibold tracking-tight text-slate-900">Returns &amp; risk</h2>
                            <p class="mt-2 text-sm text-slate-500">Check consistency, losses and recovery across the full backtest.</p>
                        </div>
                        <div class="flex gap-4 text-sm font-medium text-purple-700">
                            <button v-if="monthlyReturns.length" type="button" class="py-2 hover:underline" @click="scrollToSection('bt-monthly')">
                                Monthly returns</button
                            ><button v-if="hasRollingReturns" type="button" class="py-2 hover:underline" @click="scrollToSection('bt-rolling')">
                                Rolling returns
                            </button>
                        </div>
                    </div>
                    <div id="bt-drawdown" class="scroll-mt-48 sm:scroll-mt-36">
                        <Deferred data="dailySnapshots">
                            <template #fallback>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                    <div class="mb-4 h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                                    <div class="h-[420px] animate-pulse rounded-lg bg-gray-100"></div>
                                </div>
                            </template>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                <h3 class="text-lg font-semibold text-slate-900">Drawdown &amp; recovery</h3>
                                <p class="mt-1 mb-5 text-sm text-slate-500">
                                    How far the portfolio fell from its previous high, and how long recovery took.
                                </p>
                                <DrawdownChart
                                    :daily-snapshots="dailySnapshots"
                                    :sync-group="chartSync"
                                    :max-drawdown-start-date="summaryMetrics?.max_drawdown_start_date"
                                    :max-drawdown-end-date="summaryMetrics?.max_drawdown_end_date"
                                />

                                <!-- Deepest drawdown episodes -->
                                <div v-if="topDrawdowns.length > 0" class="mt-5">
                                    <h3 class="mb-2 text-xs font-semibold tracking-wide text-gray-400 uppercase">Deepest Drawdowns</h3>
                                    <div class="relative overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead>
                                                <tr class="border-b border-gray-200">
                                                    <th class="pr-4 pb-2 text-right font-medium text-gray-500">Depth</th>
                                                    <th class="px-4 pb-2 text-left font-medium text-gray-500">Peak</th>
                                                    <th class="px-4 pb-2 text-left font-medium text-gray-500">Trough</th>
                                                    <th class="px-4 pb-2 text-left font-medium text-gray-500">Recovered</th>
                                                    <th class="pb-2 pl-4 text-right font-medium text-gray-500">Time below peak</th>
                                                    <th class="pb-2 pl-4"><span class="sr-only">Chart</span></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <tr v-for="dd in topDrawdowns" :key="dd.peakDate">
                                                    <td class="py-2 pr-4 text-right font-semibold whitespace-nowrap text-red-700 tabular-nums">
                                                        {{ (dd.depth * 100).toFixed(1) }}%
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ formatDate(dd.peakDate) }}</td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ formatDate(dd.troughDate) }}</td>
                                                    <td
                                                        class="px-4 py-2 whitespace-nowrap"
                                                        :class="dd.recoveryDate ? 'text-gray-700' : 'font-medium text-amber-600'"
                                                    >
                                                        {{ dd.recoveryDate ? formatDate(dd.recoveryDate) : 'Not yet' }}
                                                    </td>
                                                    <td class="py-2 pl-4 text-right whitespace-nowrap text-gray-600 tabular-nums">
                                                        {{ dd.daysUnderwater.toLocaleString('en-IN') }} days
                                                        <span class="text-gray-400">({{ formatHoldingPeriod(dd.daysUnderwater) }})</span>
                                                    </td>
                                                    <td class="py-2 pl-4 text-right whitespace-nowrap">
                                                        <button
                                                            type="button"
                                                            class="rounded-lg border border-purple-200 px-3 py-2 text-sm font-medium text-purple-700 hover:bg-purple-50"
                                                            :aria-label="`View drawdown from ${formatDate(dd.peakDate)}`"
                                                            @click="viewDrawdown(dd)"
                                                        >
                                                            View period
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </Deferred>
                    </div>
                    <div id="bt-monthly" class="scroll-mt-48 sm:scroll-mt-36">
                        <Deferred data="dailySnapshots"
                            ><template #fallback
                                ><div
                                    class="h-64 animate-pulse rounded-xl bg-slate-100"
                                    role="status"
                                    aria-label="Loading monthly returns" /></template
                            ><BacktestMonthlyReturns
                                v-if="monthlyReturns.length && periodBounds"
                                :rows="monthlyReturns"
                                :start-date="periodBounds.start"
                                :end-date="periodBounds.end"
                                :benchmark-name="selectedBenchmark ? selectedBenchmarkName : ''"
                                :benchmark-returns="benchmarkMonthlyReturns"
                        /></Deferred>
                    </div>
                    <div id="bt-rolling" class="grid scroll-mt-48 grid-cols-1 gap-6 sm:scroll-mt-36">
                        <div v-if="hasRollingReturns" class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                            <h2 class="mb-4 text-sm font-semibold tracking-wide text-gray-500 uppercase">Rolling Returns (Annualized)</h2>
                            <!-- Rolling periods have a shorter history and keep their own visible range. -->
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
                                        <td class="py-2.5 text-right" :class="returnColor(rollingStats(period.key).min)">
                                            {{ formatPercent(rollingStats(period.key).min) }}
                                        </td>
                                        <td class="py-2.5 text-right" :class="returnColor(rollingStats(period.key).median)">
                                            {{ formatPercent(rollingStats(period.key).median) }}
                                        </td>
                                        <td class="py-2.5 text-right" :class="returnColor(rollingStats(period.key).max)">
                                            {{ formatPercent(rollingStats(period.key).max) }}
                                        </td>
                                        <td class="py-2.5 text-right text-gray-700 tabular-nums">
                                            {{ rollingStats(period.key).positivePct.toFixed(0) }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section id="bt-portfolio" class="scroll-mt-48 space-y-6 sm:scroll-mt-36" aria-labelledby="portfolio-heading">
                    <div class="border-t border-slate-200 pt-8">
                        <h2 id="portfolio-heading" class="text-2xl font-semibold tracking-tight text-slate-900">Portfolio</h2>
                        <p class="mt-2 text-sm text-slate-500">Holdings at the end of the run, allocation over time, and completed positions.</p>
                    </div>
                    <details
                        class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6"
                        @toggle="showAllocation = ($event.target as HTMLDetailsElement).open"
                    >
                        <summary class="cursor-pointer text-base font-semibold text-slate-800">
                            Allocation over time<span class="mt-1 block text-sm font-normal text-slate-500"
                                >Cash and market-cap history · {{ cashStats.avg.toFixed(1) }}% average cash</span
                            >
                        </summary>
                        <div v-if="showAllocation" class="mt-6 space-y-6">
                            <div id="bt-cash" class="scroll-mt-48 sm:scroll-mt-36">
                                <Deferred data="dailySnapshots">
                                    <template #fallback>
                                        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                            <div class="mb-4 h-4 w-48 animate-pulse rounded bg-gray-200"></div>
                                            <div class="h-[300px] animate-pulse rounded-lg bg-gray-100"></div>
                                        </div>
                                    </template>
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                        <div class="flex items-center justify-between" :class="showCashChart ? 'mb-4' : ''">
                                            <h2 class="text-sm font-semibold tracking-wide text-gray-500 uppercase">Cash Allocation Over Time</h2>
                                            <button
                                                v-if="cashStaysNegligible"
                                                type="button"
                                                class="cursor-pointer text-xs font-medium text-purple-600 hover:underline"
                                                @click="forceShowCashChart = !forceShowCashChart"
                                            >
                                                {{ showCashChart ? 'Hide chart' : 'Show chart anyway' }}
                                            </button>
                                        </div>
                                        <p v-if="!showCashChart" class="text-sm text-gray-500">
                                            The strategy stayed fully invested — cash never exceeded
                                            {{ cashStats.max.toFixed(1) }}% of the portfolio ({{ cashStats.avg.toFixed(1) }}% on average).
                                        </p>
                                        <CashAllocationChart v-if="showCashChart" :daily-snapshots="dailySnapshots" :sync-group="chartSync" />
                                    </div>
                                </Deferred>
                            </div>
                            <div id="bt-market-cap" class="scroll-mt-48 sm:scroll-mt-36">
                                <Deferred data="marketCapAllocation">
                                    <template #fallback>
                                        <div
                                            class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6"
                                            role="status"
                                            aria-label="Loading market cap allocation"
                                        >
                                            <div class="mb-4 h-4 w-56 animate-pulse rounded bg-gray-200"></div>
                                            <div class="h-[400px] animate-pulse rounded-lg bg-gray-100"></div>
                                        </div>
                                    </template>
                                    <MarketCapAllocationChart v-if="marketCapAllocation" :allocation="marketCapAllocation" />
                                </Deferred>
                            </div>
                        </div>
                    </details>
                    <div id="bt-positions" class="scroll-mt-48 space-y-6 sm:scroll-mt-36">
                        <!-- Final Holdings (IMMEDIATE — from summaryMetrics) -->
                        <div
                            v-if="finalHoldings.length > 0"
                            data-test="final-holdings"
                            class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6"
                        >
                            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="text-sm font-semibold tracking-wide text-gray-500 uppercase">
                                    Final Holdings
                                    <span class="ml-2 text-xs font-normal text-gray-400 normal-case"
                                        >({{ finalHoldings.length }} positions on the last day)</span
                                    >
                                </h2>
                            </div>
                            <p class="mb-3 text-xs text-gray-500">
                                Cost held covers the remaining shares. Total P&L includes partial sales; its percentage uses all purchase costs for
                                the position.
                            </p>
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm lg:hidden">
                                <label for="holdings-sort" class="text-slate-600">Sort by</label>
                                <select
                                    id="holdings-sort"
                                    :value="holdingsSortKey"
                                    class="min-h-10 rounded border border-slate-300 py-1 pr-8 pl-2"
                                    @change="setHoldingsSort(($event.target as HTMLSelectElement).value as HoldingsSortKey)"
                                >
                                    <option value="unrealized_value">Value / weight</option>
                                    <option value="symbol">Symbol</option>
                                    <option value="entry_date">Entry date</option>
                                    <option value="holding_days">Time held</option>
                                    <option value="remaining_cost">Cost held</option>
                                    <option value="pnl_pct">Total P&amp;L %</option>
                                </select>
                                <button type="button" class="min-h-10 px-1 font-medium text-purple-700" @click="setHoldingsSort(holdingsSortKey)">
                                    {{ holdingsSortDir === 'asc' ? 'Ascending ↑' : 'Descending ↓' }}
                                </button>
                            </div>
                            <div class="relative overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="sticky left-0 z-10 bg-white pr-4 pb-2 text-left font-medium text-gray-500">
                                                <button type="button" class="cursor-pointer hover:text-gray-700" @click="setHoldingsSort('symbol')">
                                                    Symbol{{ holdingsSortIndicator('symbol') }}
                                                </button>
                                            </th>
                                            <th class="hidden px-4 pb-2 text-left font-medium text-gray-500 lg:table-cell">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer hover:text-gray-700"
                                                    @click="setHoldingsSort('entry_date')"
                                                >
                                                    Entry{{ holdingsSortIndicator('entry_date') }}
                                                </button>
                                            </th>
                                            <th class="hidden px-4 pb-2 text-right font-medium text-gray-500 lg:table-cell">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer hover:text-gray-700"
                                                    @click="setHoldingsSort('holding_days')"
                                                >
                                                    Held{{ holdingsSortIndicator('holding_days') }}
                                                </button>
                                            </th>
                                            <th class="hidden px-4 pb-2 text-right font-medium text-gray-500 lg:table-cell">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer hover:text-gray-700"
                                                    @click="setHoldingsSort('remaining_cost')"
                                                >
                                                    Cost held{{ holdingsSortIndicator('remaining_cost') }}
                                                </button>
                                            </th>
                                            <th class="hidden px-4 pb-2 text-right font-medium text-gray-500 lg:table-cell">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer hover:text-gray-700"
                                                    @click="setHoldingsSort('unrealized_value')"
                                                >
                                                    Value{{ holdingsSortIndicator('unrealized_value') }}
                                                </button>
                                            </th>
                                            <th class="px-1 pb-2 text-right font-medium text-gray-500 lg:px-4">Weight</th>
                                            <th
                                                class="hidden px-4 pb-2 text-right font-medium text-gray-500 lg:table-cell"
                                                title="Net profit or loss from partial sales within this open position"
                                            >
                                                Realised P&L
                                            </th>
                                            <th
                                                class="hidden px-4 pb-2 text-right font-medium text-gray-500 lg:table-cell"
                                                title="Current value less the cost of the remaining shares, including buy charges"
                                            >
                                                Unrealised P&L
                                            </th>
                                            <th
                                                class="pb-2 pl-4 text-right font-medium text-gray-500"
                                                title="Realised plus unrealised P&L. Percentage uses total purchase cost, including buy charges."
                                            >
                                                <button type="button" class="cursor-pointer hover:text-gray-700" @click="setHoldingsSort('pnl_pct')">
                                                    Total P&L{{ holdingsSortIndicator('pnl_pct') }}
                                                </button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template v-for="position in finalHoldings" :key="position.symbol">
                                            <tr class="hover:bg-gray-50">
                                                <td class="sticky left-0 z-10 bg-white py-2 pr-1 lg:pr-4">
                                                    <a
                                                        :href="`/instruments/${position.symbol}`"
                                                        target="_blank"
                                                        class="hidden font-medium text-gray-900 hover:text-purple-700 hover:underline lg:inline"
                                                    >
                                                        {{ position.symbol }}
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-h-9 items-center gap-1 text-left font-semibold text-slate-900 lg:hidden"
                                                        :aria-label="`Details for ${position.symbol}`"
                                                        :aria-expanded="expandedHolding === position.symbol"
                                                        :aria-controls="`holding-details-${position.symbol}`"
                                                        @click="expandedHolding = expandedHolding === position.symbol ? null : position.symbol"
                                                    >
                                                        <span class="wrap-anywhere">{{ position.symbol }}</span
                                                        ><span aria-hidden="true" class="text-purple-700">{{
                                                            expandedHolding === position.symbol ? '▾' : '▸'
                                                        }}</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="mt-1 block cursor-pointer py-2 text-xs font-medium text-purple-700 hover:underline"
                                                        :aria-label="`View trades for ${position.symbol}`"
                                                        @click="viewPositionTrades(position)"
                                                    >
                                                        View trades
                                                    </button>
                                                </td>
                                                <td class="hidden px-4 py-2 whitespace-nowrap text-gray-600 lg:table-cell">
                                                    {{ formatMonthYear(position.entry_date) }}
                                                </td>
                                                <td class="hidden px-4 py-2 text-right whitespace-nowrap text-gray-600 lg:table-cell">
                                                    {{ formatHoldingPeriod(position.holding_days) }}
                                                </td>
                                                <td class="hidden px-4 py-2 text-right whitespace-nowrap text-gray-600 tabular-nums lg:table-cell">
                                                    {{ formatCurrencyShort(position.remaining_cost) }}
                                                </td>
                                                <td
                                                    class="hidden px-4 py-2 text-right font-medium whitespace-nowrap text-gray-900 tabular-nums lg:table-cell"
                                                >
                                                    {{ formatCurrencyShort(position.unrealized_value) }}
                                                </td>
                                                <td class="px-1 py-2 text-right whitespace-nowrap text-gray-700 tabular-nums lg:px-4">
                                                    {{ holdingWeight(position.unrealized_value) }}
                                                </td>
                                                <td
                                                    class="hidden px-4 py-2 text-right whitespace-nowrap tabular-nums lg:table-cell"
                                                    :class="position.realized_pnl >= 0 ? 'text-emerald-700' : 'text-red-700'"
                                                >
                                                    {{ formatCurrencyShort(position.realized_pnl) }}
                                                </td>
                                                <td
                                                    class="hidden px-4 py-2 text-right whitespace-nowrap tabular-nums lg:table-cell"
                                                    :class="position.unrealized_pnl >= 0 ? 'text-emerald-700' : 'text-red-700'"
                                                >
                                                    {{ formatCurrencyShort(position.unrealized_pnl) }}
                                                </td>
                                                <td class="py-2 pl-4 text-right whitespace-nowrap">
                                                    <div
                                                        class="font-semibold tabular-nums"
                                                        :class="position.net_pnl >= 0 ? 'text-emerald-700' : 'text-red-700'"
                                                    >
                                                        {{ position.pnl_pct >= 0 ? '+' : '' }}{{ position.pnl_pct.toFixed(1) }}%
                                                    </div>
                                                    <div
                                                        class="text-xs tabular-nums"
                                                        :class="position.net_pnl >= 0 ? 'text-emerald-600' : 'text-red-600'"
                                                    >
                                                        {{ formatCurrencyShort(position.net_pnl) }}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="expandedHolding === position.symbol"
                                                :id="`holding-details-${position.symbol}`"
                                                class="lg:hidden"
                                            >
                                                <td colspan="3" class="rounded-md bg-slate-50 p-3">
                                                    <dl
                                                        class="grid grid-cols-2 gap-3 text-xs [&_dd]:mt-1 [&_dd]:font-medium [&_dd]:tabular-nums [&_dt]:text-slate-600"
                                                    >
                                                        <div>
                                                            <dt>Entry date</dt>
                                                            <dd>{{ formatDate(position.entry_date) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt>Time held</dt>
                                                            <dd>{{ formatHoldingPeriod(position.holding_days) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt>Cost held</dt>
                                                            <dd>{{ formatCurrencyShort(position.remaining_cost) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt>Value</dt>
                                                            <dd>{{ formatCurrencyShort(position.unrealized_value) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt>Realised P&amp;L</dt>
                                                            <dd :class="returnColor(position.realized_pnl)">
                                                                {{ formatCurrencyShort(position.realized_pnl) }}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt>Unrealised P&amp;L</dt>
                                                            <dd :class="returnColor(position.unrealized_pnl)">
                                                                {{ formatCurrencyShort(position.unrealized_pnl) }}
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                    <a
                                                        :href="`/instruments/${position.symbol}`"
                                                        target="_blank"
                                                        class="mt-2 inline-flex min-h-9 items-center font-medium text-purple-700"
                                                        >View {{ position.symbol }} instrument ↗</a
                                                    >
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="hidden lg:table-footer-group">
                                        <tr class="border-t border-gray-200">
                                            <td class="py-2 pr-4 font-medium text-gray-500" colspan="4">Cash</td>
                                            <td class="px-4 py-2 text-right font-medium whitespace-nowrap text-gray-900 tabular-nums">
                                                {{ formatCurrencyShort(finalCash) }}
                                            </td>
                                            <td class="px-4 py-2 text-right whitespace-nowrap text-gray-700 tabular-nums">
                                                {{ holdingWeight(finalCash) }}
                                            </td>
                                            <td colspan="3"></td>
                                        </tr>
                                        <tr class="border-t border-gray-200">
                                            <td class="py-2 pr-4 font-semibold text-gray-700" colspan="3">Total</td>
                                            <td class="px-4 py-2 text-right font-semibold whitespace-nowrap text-gray-900 tabular-nums">
                                                {{ formatCurrencyShort(holdingsTotals.invested) }}
                                            </td>
                                            <td class="px-4 py-2 text-right font-semibold whitespace-nowrap text-gray-900 tabular-nums">
                                                {{ formatCurrencyShort(summaryMetrics.final_value) }}
                                            </td>
                                            <td class="px-4 py-2 text-right font-semibold whitespace-nowrap text-gray-700 tabular-nums">100.0%</td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <dl class="mt-1 divide-y divide-slate-200 border-t border-slate-200 text-sm lg:hidden">
                                <div class="flex justify-between gap-2 py-2">
                                    <dt>Cash ({{ holdingWeight(finalCash) }})</dt>
                                    <dd class="tabular-nums">{{ formatCurrencyShort(finalCash) }}</dd>
                                </div>
                                <div class="flex justify-between gap-2 py-2 font-semibold">
                                    <dt>Total portfolio value</dt>
                                    <dd class="tabular-nums">{{ formatCurrencyShort(summaryMetrics.final_value) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Top Gainers & Losers (IMMEDIATE — from summaryMetrics) -->
                        <div v-if="closedPositions" data-test="position-statistics" class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Completed position outcomes</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Each position runs from first purchase to full exit. Figures include charges and exclude open holdings.
                                </p>
                            </div>
                            <dl class="grid gap-3 sm:grid-cols-3">
                                <ResultMetric
                                    label="Completed positions"
                                    :value="closedPositions.count.toLocaleString('en-IN')"
                                    :detail="winnersLosersLabel ?? undefined"
                                />
                                <ResultMetric
                                    label="Win rate"
                                    :value="winRate !== null ? winRate.toFixed(1) + '%' : '—'"
                                    help="Winning positions divided by all completed positions, including breakeven. Read this together with average win and loss; a rate below 50% can still be profitable."
                                />
                                <ResultMetric
                                    label="Profit factor"
                                    :value="hasProfitsWithoutLosses ? '∞' : profitFactor !== null ? profitFactor.toFixed(2) + '×' : '—'"
                                    help="Total net profits divided by total net losses from completed positions. Infinity means profits with no losses."
                                />
                            </dl>
                            <details class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Position averages</summary>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <ResultMetric
                                        label="Average win / loss"
                                        :value="
                                            avgWinLoss
                                                ? `${avgWinLoss.win !== null ? formatCurrencyShort(avgWinLoss.win) : '—'} / ${avgWinLoss.loss !== null ? formatCurrencyShort(avgWinLoss.loss) : '—'}`
                                                : '—'
                                        "
                                    />
                                    <ResultMetric
                                        label="Average profit / position"
                                        :value="expectancy !== null ? formatCurrencyShort(expectancy) : '—'"
                                    />
                                    <ResultMetric
                                        label="Average completed holding"
                                        :value="avgHoldingDays !== null ? formatHoldingPeriod(avgHoldingDays) : '—'"
                                    />
                                </dl>
                            </details>
                        </div>
                        <div v-if="positionPerformance" data-test="completed-positions">
                            <p class="mb-3 text-sm text-gray-600">
                                Each position runs from its first purchase to its full exit. Open holdings are excluded from these lists.
                            </p>
                            <p class="mb-3 text-xs text-gray-500">
                                Percentages show profit as a share of total purchase cost, including buy charges. This cost can include cash reused
                                after partial sales.
                            </p>
                            <p v-if="closedPositions?.count === 0" class="mb-3 text-sm text-gray-500">
                                No positions were fully exited during this backtest.
                            </p>
                            <div class="mb-3 flex items-center justify-end gap-2 text-xs">
                                <span class="text-gray-500">Rank by:</span>
                                <div class="inline-flex rounded-md border border-gray-300 bg-white">
                                    <button
                                        type="button"
                                        class="cursor-pointer px-3 py-1 font-medium first:rounded-l-md last:rounded-r-md"
                                        :class="gainersSortMode === 'pct' ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                                        :aria-pressed="gainersSortMode === 'pct'"
                                        @click="gainersSortMode = 'pct'"
                                    >
                                        % purchase cost
                                    </button>
                                    <button
                                        type="button"
                                        class="cursor-pointer px-3 py-1 font-medium first:rounded-l-md last:rounded-r-md"
                                        :class="gainersSortMode === 'abs' ? 'bg-purple-600 text-white' : 'text-gray-700 hover:bg-gray-50'"
                                        :aria-pressed="gainersSortMode === 'abs'"
                                        @click="gainersSortMode = 'abs'"
                                    >
                                        ₹ P&L
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <!-- Top 20 Gainers -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                    <h2 class="mb-4 text-sm font-semibold tracking-wide text-emerald-700 uppercase">Top 20 Winners</h2>
                                    <p v-if="topGainers.length === 0" class="text-sm text-gray-500">No completed winning positions.</p>
                                    <ul data-test="top-winners" class="divide-y divide-gray-100">
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
                                                        >{{ position.symbol }}</a
                                                    >
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-500">
                                                    {{ formatMonthYear(position.entry_date) }}
                                                    <span class="text-gray-300">→</span>
                                                    {{ position.exit_date ? formatMonthYear(position.exit_date) : '—' }}
                                                    <span class="mx-1 text-gray-300">·</span>
                                                    <span class="font-medium text-gray-600">{{ formatHoldingPeriod(position.holding_days) }}</span>
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-400">
                                                    {{ formatCurrencyShort(position.purchase_cost) }} purchase cost
                                                </div>
                                                <button
                                                    type="button"
                                                    class="mt-1 rounded py-1.5 text-sm font-medium text-purple-700 hover:underline"
                                                    :aria-label="`View trades for ${position.symbol} from ${formatDate(position.entry_date)}`"
                                                    @click="viewPositionTrades(position)"
                                                >
                                                    View trades →
                                                </button>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="font-semibold text-emerald-700 tabular-nums">+{{ position.pnl_pct.toFixed(1) }}%</div>
                                                <div class="mt-0.5 text-xs text-emerald-600 tabular-nums">
                                                    {{ formatCurrencyShort(position.net_pnl) }}
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Top 20 Losers -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                    <h2 class="mb-4 text-sm font-semibold tracking-wide text-red-700 uppercase">Top 20 Losers</h2>
                                    <p v-if="topLosers.length === 0" class="text-sm text-gray-500">No completed losing positions.</p>
                                    <ul data-test="top-losers" class="divide-y divide-gray-100">
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
                                                        >{{ position.symbol }}</a
                                                    >
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-500">
                                                    {{ formatMonthYear(position.entry_date) }}
                                                    <span class="text-gray-300">→</span>
                                                    {{ position.exit_date ? formatMonthYear(position.exit_date) : '—' }}
                                                    <span class="mx-1 text-gray-300">·</span>
                                                    <span class="font-medium text-gray-600">{{ formatHoldingPeriod(position.holding_days) }}</span>
                                                </div>
                                                <div class="mt-0.5 text-xs text-gray-400">
                                                    {{ formatCurrencyShort(position.purchase_cost) }} purchase cost
                                                </div>
                                                <button
                                                    type="button"
                                                    class="mt-1 rounded py-1.5 text-sm font-medium text-purple-700 hover:underline"
                                                    :aria-label="`View trades for ${position.symbol} from ${formatDate(position.entry_date)}`"
                                                    @click="viewPositionTrades(position)"
                                                >
                                                    View trades →
                                                </button>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="font-semibold text-red-700 tabular-nums">{{ position.pnl_pct.toFixed(1) }}%</div>
                                                <div class="mt-0.5 text-xs text-red-600 tabular-nums">
                                                    {{ formatCurrencyShort(position.net_pnl) }}
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="border-t border-slate-200 pt-8" aria-labelledby="trades-heading">
                    <h2 id="trades-heading" class="mb-2 text-2xl font-semibold tracking-tight text-slate-900">Trade log</h2>
                    <p class="mb-6 text-sm text-slate-500">Check each purchase and sale, its reason, and its charges.</p>
                    <div id="bt-trades" class="scroll-mt-48 sm:scroll-mt-36">
                        <Deferred :data="['trades', 'tradeLogSummary']">
                            <template #fallback>
                                <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                    <div class="mb-4 h-4 w-24 animate-pulse rounded bg-gray-200"></div>
                                    <div class="space-y-2">
                                        <div v-for="n in 8" :key="n" class="h-8 animate-pulse rounded bg-gray-100"></div>
                                    </div>
                                </div>
                            </template>
                            <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6">
                                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <h2 class="text-sm font-semibold tracking-wide text-gray-500 uppercase">
                                        Trade Log
                                        <span v-if="trades && tradeLogSummary" class="ml-2 text-xs font-normal text-slate-600 normal-case">
                                            <template v-if="hasTradeFilters"
                                                >{{ trades.total.toLocaleString('en-IN') }} matching trades of
                                                {{ tradeLogSummary.total.toLocaleString('en-IN') }}</template
                                            >
                                            <template v-else>{{ tradeLogSummary.total.toLocaleString('en-IN') }} transactions</template>
                                        </span>
                                    </h2>
                                    <a
                                        v-if="!readOnly"
                                        :href="`/backtests/${backtest.id}/csv/trades`"
                                        class="text-xs font-medium text-purple-600 hover:underline"
                                    >
                                        Download all trades CSV
                                    </a>
                                </div>
                                <TradeLogTable
                                    v-if="trades && tradeLogSummary"
                                    :trades="trades"
                                    :summary="tradeLogSummary"
                                    :filters="tradeFilters"
                                    :focus-position="focusedPosition"
                                />
                            </div>
                        </Deferred>
                    </div>
                </section>
            </div>
        </div>
        <!-- ============ SETTINGS TAB ============ -->
        <div v-if="!readOnly" v-show="activeTab === 'settings'" class="mt-6">
            <InfoAlert v-if="settingsLocked" class="mb-4"> Settings are locked until this backtest and any dependent backtest finish. </InfoAlert>
            <fieldset :disabled="settingsLocked" :inert="settingsLocked" class="min-w-0">
                <BacktestSettingsForm
                    :form="form"
                    :running="backtest.status === 'running'"
                    :run-blocked="runBlocked"
                    :initial-capital="backtest.initial_capital"
                    :indices="indices"
                    :sort-by-options="sortByOptions"
                    :start-date-limits="startDateLimits"
                    :apply-filters-on-options="applyFiltersOnOptions"
                    :custom-filter-value-options="customFilterValueOptions"
                    :custom-filter-comparator-options="customFilterComparatorOptions"
                    :rebalance-frequency-options="rebalanceFrequencyOptions"
                    :weightage-options="weightageOptions"
                    :cash-call-options="cashCallOptions"
                    :stop-loss-proceeds-options="stopLossProceedsOptions"
                    :cash-call-index-options="cashCallIndexOptions"
                    :cash-call-backtest-options="cashCallBacktestOptions"
                    @save="save"
                    @save-and-run="saveAndRun"
                    @delete="destroy"
                />
            </fieldset>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { PlayIcon } from '@heroicons/vue/20/solid';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import { formatCurrencyShort, formatDate, formatHoldingPeriod, formatMonthYear, formatPercent } from '@/utils/format';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import ResultMetric from '@/Pages/Backtests/partials/ResultMetric.vue';
import BacktestMonthlyReturns from '@/Pages/Backtests/partials/BacktestMonthlyReturns.vue';
import BacktestNavChart from '@/Pages/Backtests/partials/BacktestNavChart.vue';
import BacktestSettingsForm from '@/Pages/Backtests/partials/BacktestSettingsForm.vue';
import BacktestStrategyRules from '@/Pages/Backtests/partials/BacktestStrategyRules.vue';
import CashAllocationChart from '@/Pages/Backtests/partials/CashAllocationChart.vue';
import DrawdownChart from '@/Pages/Backtests/partials/DrawdownChart.vue';
import DuplicateBacktestButton from '@/Pages/Backtests/partials/DuplicateBacktestButton.vue';
import MarketCapAllocationChart from '@/Pages/Backtests/partials/MarketCapAllocationChart.vue';
import RollingReturnsChart from '@/Pages/Backtests/partials/RollingReturnsChart.vue';
import TradeLogTable from '@/Pages/Backtests/partials/TradeLogTable.vue';
import { ChartSyncGroup } from '@/utils/chartSyncGroup';
import type { Backtest } from '@/types/app/Models/Backtest';
import type { BacktestPosition, BacktestSummaryMetric } from '@/types/app/Models/BacktestSummaryMetric';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';
import type { PaginatedBacktestTrades, PositionTradeScope, TradeLogFilters, TradeLogSummary } from '@/types/BacktestTradeLog';
import type { SelectOption } from '@/types/SelectOption';
import type { MarketCapAllocation } from '@/types/MarketCapAllocation';

interface BenchmarkPoint {
    date: string;
    nav: number;
}

const props = withDefaults(
    defineProps<{
        backtest: Backtest;
        readOnly?: boolean;
        settingsLocked: boolean;
        activeBacktest: Pick<Backtest, 'id' | 'name'> | null;
        summaryMetrics: BacktestSummaryMetric | null;
        // Deferred Inertia props — absent on the first render while their groups load.
        dailySnapshots?: BacktestDailySnapshot[];
        defaultBenchmark?: BenchmarkPoint[] | null;
        trades?: PaginatedBacktestTrades | null;
        tradeLogSummary?: TradeLogSummary | null;
        tradeFilters: TradeLogFilters;
        marketCapAllocation?: MarketCapAllocation | null;
        benchmarkOptions: SelectOption[];
        indices: SelectOption[];
        sortByOptions: SelectOption[];
        startDateLimits: { default: string; universes: Record<string, string>; sorts: Record<string, string>; filters: Record<string, string> };
        applyFiltersOnOptions: SelectOption[];
        customFilterValueOptions: SelectOption[];
        customFilterComparatorOptions: SelectOption[];
        rebalanceFrequencyOptions: SelectOption[];
        weightageOptions: SelectOption[];
        cashCallOptions: SelectOption[];
        stopLossProceedsOptions: SelectOption[];
        cashCallIndexOptions: SelectOption[];
        cashCallBacktestOptions: SelectOption[];
        cashCallBacktest: Pick<Backtest, 'id' | 'name' | 'status' | 'progress'> | null;
    }>(),
    {
        dailySnapshots: () => [],
        defaultBenchmark: () => [],
        readOnly: false,
    },
);

// --- Tabs ---

type Tab = 'results' | 'settings';

function initialTab(): Tab {
    if (props.readOnly) {
        return 'results';
    }

    const param = new URLSearchParams(window.location.search).get('tab');
    if (param === 'settings' || param === 'results') {
        return param;
    }

    return props.backtest.status === 'pending' ? 'settings' : 'results';
}

const activeTab = ref<Tab>(initialTab());
const runBlocked = computed(() => props.settingsLocked || props.backtest.status === 'running' || props.activeBacktest !== null);

function replaceResultParameter(key: string, value: string | null): void {
    const url = new URL(window.location.href);
    if (value === null) url.searchParams.delete(key);
    else url.searchParams.set(key, value);
    router.replace({ url: url.pathname + url.search + url.hash, preserveState: true, preserveScroll: true });
}

function setTab(tab: Tab): void {
    if (props.readOnly && tab === 'settings') {
        return;
    }

    activeTab.value = tab;
    replaceResultParameter('tab', tab);
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
    exit_before_demerger: props.backtest.exit_before_demerger,
    exit_on_be_series: props.backtest.exit_on_be_series,
    apply_stop_loss: props.backtest.apply_stop_loss,
    stop_loss_percentage: props.backtest.stop_loss_percentage,
    trail_stop_loss: props.backtest.trail_stop_loss,
    stop_loss_proceeds: props.backtest.stop_loss_proceeds,
    rebalance_frequency: props.backtest.rebalance_frequency,
    rebalance_day: props.backtest.rebalance_day,
    weightage: props.backtest.weightage,
    cash_call: props.backtest.cash_call,
    cash_call_source: props.backtest.cash_call_source ?? 'index',
    cash_call_backtest_id: props.backtest.cash_call_backtest_id ?? '',
    cash_call_index: props.backtest.cash_call_index,
    cash_call_dma_period: props.backtest.cash_call_dma_period,
    cash_call_gold_dma_period: props.backtest.cash_call_gold_dma_period,
    cash_return_rate: props.backtest.cash_return_rate,
    brokerage_rate: props.backtest.brokerage_rate,
    stt_rate: props.backtest.stt_rate,
    transaction_charges_rate: props.backtest.transaction_charges_rate,
    sebi_charges_rate: props.backtest.sebi_charges_rate,
    gst_rate: props.backtest.gst_rate,
    stamp_charges_rate: props.backtest.stamp_charges_rate,
    start_date: props.backtest.start_date ? props.backtest.start_date.substring(0, 10) : '2011-01-05',
    index: props.backtest.index,
    unique_etf_per_index: props.backtest.unique_etf_per_index,
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
    apply_marketcap: props.backtest.apply_marketcap,
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

const reviewingCompletedRun = computed(() => activeTab.value === 'results' && props.backtest.status === 'completed' && !form.isDirty);

function save(): void {
    if (props.settingsLocked || form.processing) {
        return;
    }

    form.transform((data) => data).put(`/backtests/${props.backtest.id}`, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

function saveAndRun(): void {
    if (runBlocked.value || form.processing) {
        return;
    }

    form.transform((data) => ({ ...data, run: true })).put(`/backtests/${props.backtest.id}`, {
        onSuccess: () => {
            form.defaults();
            setTab('results');
        },
        onError: () => setTab('settings'),
    });
}

function destroy(): void {
    if (props.settingsLocked) {
        return;
    }

    if (confirm(`Delete "${props.backtest.name}"? This permanently removes the backtest and all of its results, trades, and snapshots.`)) {
        router.delete(`/backtests/${props.backtest.id}`);
    }
}

// --- Collapsible cards & error details ---

const showStrategyRules = ref(props.backtest.status !== 'completed');
const showErrorDetails = ref(false);

// --- Unsaved-changes guards ---

function handleBeforeUnload(event: BeforeUnloadEvent): void {
    if (form.isDirty) {
        event.preventDefault();
    }
}

// Block in-app navigation away from this page while the form is dirty.
// Same-path visits (polling reloads, deferred loads, saves) pass through.
const removeNavigationGuard = router.on('before', (event) => {
    const visit = event.detail.visit;
    if (form.isDirty && visit.method === 'get' && visit.url.pathname !== window.location.pathname) {
        return confirm('You have unsaved settings changes. Leave without saving?');
    }
});

// --- Chart synchronization (NAV / drawdown / cash share range + crosshair) ---

const chartSync = new ChartSyncGroup();

// --- Section navigation ---

const resultSections = computed(() => [
    { id: 'bt-overview', label: 'Overview' },
    { id: 'bt-risk', label: 'Returns & risk' },
    { id: 'bt-portfolio', label: 'Portfolio' },
    { id: 'bt-trades', label: 'Trade log' },
]);
const navChart = ref<InstanceType<typeof BacktestNavChart> | null>(null);
const focusedPosition = ref<PositionTradeScope | null>(null);
const hasTradeFilters = computed(() => {
    const { symbol, search, type, reason, year } = props.tradeFilters;
    return Boolean(symbol || search || type !== 'all' || reason || year);
});
const showAllocation = ref(false);
const strategySummary = computed(() =>
    [
        props.indices.find((option) => String(option.id) === props.backtest.index)?.name ?? props.backtest.index,
        `${props.backtest.max_stocks_to_hold} stock target`,
        props.rebalanceFrequencyOptions.find((option) => String(option.id) === props.backtest.rebalance_frequency)?.name ??
            props.backtest.rebalance_frequency,
        props.weightageOptions.find((option) => String(option.id) === props.backtest.weightage)?.name ?? props.backtest.weightage,
    ].join(' · '),
);

function scrollToSection(id: string): void {
    document
        .getElementById(id)
        ?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
}

function viewDrawdown(episode: { peakDate: string; recoveryDate: string | null }): void {
    if (!periodBounds.value) return;
    navChart.value?.showPeriod(episode.peakDate, episode.recoveryDate ?? periodBounds.value.end.substring(0, 10));
    scrollToSection('bt-nav');
}

function viewPositionTrades(position: BacktestPosition): void {
    const end = position.exit_date ?? periodBounds.value?.end;
    if (!end) return;
    focusedPosition.value = { symbol: position.symbol, from: position.entry_date.substring(0, 10), through: end.substring(0, 10) };
    scrollToSection('bt-trades');
}

// --- Scrollspy ---

const activeSection = ref<string>('bt-overview');
let sectionObserver: IntersectionObserver | null = null;

function setupScrollspy(): void {
    sectionObserver?.disconnect();
    if (props.backtest.status !== 'completed') {
        return;
    }

    sectionObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            }
        },
        // Track the band just below the sticky app header + section nav.
        { rootMargin: '-130px 0px -65% 0px', threshold: 0 },
    );

    for (const section of resultSections.value) {
        const el = document.getElementById(section.id);
        if (el) {
            sectionObserver.observe(el);
        }
    }
}

// --- Stale results detection ---

// Compares the strategy-settings timestamp against the run start, so renames
// never flag results as stale and mid-run saves correctly do.
const settingsChangedSinceRun = computed<boolean>(() => {
    if (props.backtest.status !== 'completed' || !props.backtest.settings_changed_at || !props.backtest.started_at) {
        return false;
    }

    return new Date(props.backtest.settings_changed_at).getTime() > new Date(props.backtest.started_at).getTime();
});

// --- Polling ---

let pollInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!props.readOnly && (props.backtest.status === 'running' || props.activeBacktest)) {
        startPolling();
    }

    window.addEventListener('beforeunload', handleBeforeUnload);
    nextTick(setupScrollspy);
});

onUnmounted(() => {
    stopPolling();
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeNavigationGuard();
    sectionObserver?.disconnect();
});

watch(
    () => props.backtest.status,
    (newStatus, oldStatus) => {
        if (newStatus === 'completed' && oldStatus !== 'completed') {
            // Job just finished — reload triggers deferred prop loading for charts/trades
            router.reload();
        }
    },
);

watch(
    () => [props.backtest.status, props.activeBacktest?.id],
    () => {
        if (!props.readOnly && (props.backtest.status === 'running' || props.activeBacktest)) startPolling();
        else stopPolling();
    },
);

function startPolling(): void {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({ only: ['backtest', 'cashCallBacktest', 'settingsLocked', 'activeBacktest'] });
    }, 2000);
}

function stopPolling(): void {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

// --- Benchmark ---

const resultPage = usePage();
const selectedBenchmark = computed({
    get(): string {
        const slug = new URL(resultPage.url, window.location.origin).searchParams.get('benchmark');
        if (slug === 'none') return '';
        return slug && props.benchmarkOptions.some((option) => option.id === slug) ? slug : 'nifty-50';
    },
    set(slug: string) {
        replaceResultParameter('benchmark', slug === 'nifty-50' ? null : slug || 'none');
    },
});
const benchmarkData = ref<BenchmarkPoint[]>([]);
const loadingBenchmark = ref(false);
const benchmarkError = ref('');
let benchmarkRequest: AbortController | null = null;

const selectedBenchmarkName = computed(
    () => props.benchmarkOptions.find((option) => String(option.id) === selectedBenchmark.value)?.name ?? 'Benchmark',
);

async function fetchBenchmark(slug: string): Promise<void> {
    benchmarkRequest?.abort();
    benchmarkData.value = [];
    benchmarkError.value = '';
    loadingBenchmark.value = false;
    if (!slug || props.backtest.status !== 'completed') return;
    if (slug === 'nifty-50' && props.defaultBenchmark !== null) {
        benchmarkData.value = props.defaultBenchmark;
        return;
    }
    const request = new AbortController();
    benchmarkRequest = request;
    loadingBenchmark.value = true;
    try {
        const basePath = props.readOnly ? '/public-backtests' : '/backtests';
        const response = await fetch(`${basePath}/${props.backtest.id}/benchmark?slug=${encodeURIComponent(slug)}`, {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) throw new Error('Benchmark request failed');
        const data: unknown = await response.json();
        if (
            !Array.isArray(data) ||
            data.some((point) => !point || typeof point.date !== 'string' || !Number.isFinite(Number(point.nav)) || Number(point.nav) <= 0)
        )
            throw new Error('Invalid benchmark data');
        if (request.signal.aborted) return;
        benchmarkData.value = data.map((point) => ({ date: point.date, nav: Number(point.nav) }));
    } catch {
        if (!request.signal.aborted) benchmarkError.value = 'The benchmark could not be loaded. Your saved strategy results are still available.';
    } finally {
        if (!request.signal.aborted) loadingBenchmark.value = false;
    }
}

watch(
    [
        selectedBenchmark,
        () => props.backtest.id,
        () => props.backtest.status,
        () => (selectedBenchmark.value === 'nifty-50' ? props.defaultBenchmark : null),
    ],
    ([slug]) => fetchBenchmark(slug),
    { immediate: true },
);
onUnmounted(() => benchmarkRequest?.abort());

const benchmarkCoversPeriod = computed(() => {
    if (!periodBounds.value || benchmarkData.value.length < 2) return false;
    return (
        benchmarkData.value[0].date.substring(0, 10) === periodBounds.value.start.substring(0, 10) &&
        benchmarkData.value[benchmarkData.value.length - 1].date.substring(0, 10) === periodBounds.value.end.substring(0, 10)
    );
});
const benchmarkCoverageNotice = computed(() => {
    if (!benchmarkData.value.length || benchmarkCoversPeriod.value) return '';
    return `${selectedBenchmarkName.value} covers ${formatDate(benchmarkData.value[0].date)} to ${formatDate(benchmarkData.value[benchmarkData.value.length - 1].date)}. A CAGR difference is unavailable because the periods differ.`;
});
const benchmarkMetrics = computed(() => {
    const data = benchmarkData.value;
    if (data.length < 2) return { cagr: null, alpha: null, maxDrawdown: null };
    const first = data[0];
    const last = data[data.length - 1];
    const years = (new Date(last.date).getTime() - new Date(first.date).getTime()) / (365.25 * 24 * 60 * 60 * 1000);
    const annual = years > 0 && first.nav > 0 ? Math.pow(last.nav / first.nav, 1 / years) - 1 : NaN;
    const cagr = Number.isFinite(annual) ? annual : null;
    const alpha = cagr !== null && props.summaryMetrics?.cagr != null && benchmarkCoversPeriod.value ? props.summaryMetrics.cagr - cagr : null;
    let peak = 0;
    let maxDrawdown = 0;
    for (const point of data) {
        peak = Math.max(peak, point.nav);
        if (peak > 0) maxDrawdown = Math.min(maxDrawdown, (point.nav - peak) / peak);
    }
    return { cagr, alpha, maxDrawdown };
});
const benchmarkDelta = computed(() => benchmarkMetrics.value.alpha);

function formatPercentagePoints(value: number | null): string {
    return value === null || !Number.isFinite(value) ? '—' : `${value >= 0 ? '+' : ''}${(value * 100).toFixed(2)} pp`;
}

// --- Formatters (shared ones come from @/utils/format) ---

function statusBadgeClass(status: string): Record<string, boolean> {
    return {
        'bg-gray-100 text-gray-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'running',
        'bg-emerald-100 text-emerald-700': status === 'completed',
        'bg-red-100 text-red-700': status === 'failed',
    };
}

function metricTone(value: number | string | null | undefined, positiveTone: 'positive' | 'risk' | 'ratio' = 'positive') {
    const numeric = Number(value);
    if (!Number.isFinite(numeric) || numeric === 0) return 'neutral';

    return numeric < 0 ? 'negative' : positiveTone;
}

function returnColor(value: number): string {
    if (value > 0) return 'text-emerald-700';
    if (value < 0) return 'text-red-700';

    return 'text-slate-600';
}

// --- Computed: derived metrics ---

// Honest stages: 0% is the queue wait, 1-4% is preparation, 5-94% is the
// trading-day simulation, 95-97% is metrics, and 98-99% is market cap allocation.
const sourceIsRunning = computed(() => props.backtest.progress === 0 && props.cashCallBacktest?.status === 'running');

const progressStageLabel = computed(() => {
    const p = props.backtest.progress;
    if (p === 0 && props.cashCallBacktest?.status === 'running') {
        return `Running source backtest: ${props.cashCallBacktest.name} (${props.cashCallBacktest.progress}%)`;
    }
    if (p === 0) return 'Waiting for a worker';
    if (p < 5) return 'Preparing market data';
    if (p < 95) return 'Simulating trades';
    if (p < 98) return 'Computing metrics';
    return 'Calculating market cap allocation';
});

const simulatedDaysPct = computed(() => Math.max(0, Math.min(100, Math.round(((props.backtest.progress - 5) / 89) * 100))));

const growthMultiplier = computed(() => {
    const capital = Number(props.backtest.initial_capital);
    if (!props.summaryMetrics || !capital) return '0.0';
    return (Number(props.summaryMetrics.final_value) / capital).toFixed(1);
});

// Period comes from the summary record (available immediately) with the
// deferred snapshots as a fallback for runs predating the persisted dates.
const periodBounds = computed<{ start: string; end: string } | null>(() => {
    const m = props.summaryMetrics;
    if (m?.start_date && m?.end_date) {
        return { start: m.start_date, end: m.end_date };
    }
    if (props.dailySnapshots && props.dailySnapshots.length >= 2) {
        return { start: props.dailySnapshots[0].date, end: props.dailySnapshots[props.dailySnapshots.length - 1].date };
    }

    return null;
});

const backtestPeriod = computed(() => {
    if (!periodBounds.value) return '';
    return formatDate(periodBounds.value.start) + ' → ' + formatDate(periodBounds.value.end);
});

const backtestYears = computed(() => {
    if (!periodBounds.value) return '0';
    const first = new Date(periodBounds.value.start);
    const last = new Date(periodBounds.value.end);
    return ((last.getTime() - first.getTime()) / (365.25 * 24 * 60 * 60 * 1000)).toFixed(1);
});

const backtestDuration = computed(() => {
    if (!periodBounds.value) return '—';
    const days = diffDays(periodBounds.value.start, periodBounds.value.end);
    return days < 365 ? `${days} ${days === 1 ? 'day' : 'days'}` : `${backtestYears.value} years`;
});

const cashStats = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return { min: 0, avg: 0, max: 0 };
    const pcts = props.dailySnapshots.map((s) => {
        const total = Number(s.total_value);
        return total > 0 ? (Number(s.cash) / total) * 100 : 0;
    });
    return { min: Math.min(...pcts), avg: pcts.reduce((a, b) => a + b, 0) / pcts.length, max: Math.max(...pcts) };
});

// A fully-invested no-cash-call strategy renders a flat-line cash chart —
// collapse it to a one-line summary unless the user asks for it.
const forceShowCashChart = ref(false);
const cashStaysNegligible = computed(() => props.backtest.cash_call === 'no_cash_call' && cashStats.value.max < 10);
const showCashChart = computed(() => !cashStaysNegligible.value || forceShowCashChart.value);

const positionPerformance = computed(() => props.summaryMetrics?.stock_performance ?? null);
const closedPositions = computed(() => positionPerformance.value?.closed ?? null);
const winRate = computed(() => closedPositions.value?.winners_percentage ?? null);
const profitFactor = computed(() => closedPositions.value?.profit_factor ?? null);
const hasProfitsWithoutLosses = computed(() => (closedPositions.value?.total_profit ?? 0) > 0 && closedPositions.value?.total_loss === 0);

const winnersLosersLabel = computed<string | null>(() => {
    const closed = closedPositions.value;
    if (!closed) return null;
    return `${closed.winners} winning / ${closed.count} completed · ${closed.breakeven} breakeven`;
});

// Denominated against initial capital — a fixed base that doesn't shrink the
// apparent cost as the strategy performs better.
const chargesPctOfCapital = computed<string | null>(() => {
    const m = props.summaryMetrics;
    const capital = Number(props.backtest.initial_capital);
    if (!m || !capital) return null;
    return ((Number(m.total_charges_paid) / capital) * 100).toFixed(2);
});

// Calmar = CAGR / |max drawdown| — return earned per unit of worst pain.
// max_drawdown arrives as a decimal string, so coerce before the zero guard.
const calmarRatio = computed<number | null>(() => {
    const m = props.summaryMetrics;
    if (!m || m.cagr === null || Number(m.max_drawdown) === 0) return null;
    return Number(m.cagr) / Math.abs(Number(m.max_drawdown));
});

// Daily NAV return series for volatility.
const dailyReturns = computed<number[]>(() => {
    const snaps = props.dailySnapshots;
    if (!snaps || snaps.length < 3) return [];

    const returns: number[] = [];
    for (let i = 1; i < snaps.length; i++) {
        const prev = Number(snaps[i - 1].nav);
        const curr = Number(snaps[i].nav);
        if (prev > 0) returns.push((curr - prev) / prev);
    }

    return returns;
});

// Read the value saved with this run, independent of later settings changes.
const sortinoRatio = computed<number | null>(() => {
    const value = props.summaryMetrics?.sortino_ratio;
    return value === null || value === undefined ? null : Number(value);
});

const annualizedVolatility = computed<number | null>(() => {
    const returns = dailyReturns.value;
    if (returns.length < 2) return null;

    const mean = returns.reduce((a, b) => a + b, 0) / returns.length;
    const variance = returns.reduce((a, r) => a + (r - mean) ** 2, 0) / (returns.length - 1);
    return Math.sqrt(variance) * Math.sqrt(252);
});

const avgWinLoss = computed(() => {
    const closed = closedPositions.value;
    return closed && closed.count > 0 ? { win: closed.average_win, loss: closed.average_loss } : null;
});

const expectancy = computed<number | null>(() => closedPositions.value?.expectancy ?? null);
const avgHoldingDays = computed<number | null>(() => {
    const days = closedPositions.value?.average_holding_days;
    return days === null || days === undefined ? null : Math.round(days);
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
    let peakNav = 100;
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

// Match the saved peak and trough. The initial peak is NAV 100 before entry costs.

// "Peak 23 Jan 2018 → low 19 Mar 2020 (fell over 2y 2mo)"

// "New high again on 24 Aug 2020 — 2y 7mo underwater in total"
// or, while the drawdown is still open when the backtest ends:
// "Still underwater at backtest end — no new high for 2y 3mo"

// --- Final holdings (still-open positions at the end of the run) ---

type HoldingsSortKey = 'symbol' | 'entry_date' | 'holding_days' | 'remaining_cost' | 'unrealized_value' | 'pnl_pct';

const holdingsSortKey = ref<HoldingsSortKey>('unrealized_value');
const holdingsSortDir = ref<'asc' | 'desc'>('desc');
const expandedHolding = ref<string | null>(null);

function setHoldingsSort(key: HoldingsSortKey): void {
    if (holdingsSortKey.value === key) {
        holdingsSortDir.value = holdingsSortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        holdingsSortKey.value = key;
        holdingsSortDir.value = key === 'symbol' || key === 'entry_date' ? 'asc' : 'desc';
    }
}

function holdingsSortIndicator(key: HoldingsSortKey): string {
    if (holdingsSortKey.value !== key) return '';
    return holdingsSortDir.value === 'asc' ? ' ↑' : ' ↓';
}

const finalHoldings = computed(() => {
    const holdings = positionPerformance.value?.open_positions ?? [];
    const key = holdingsSortKey.value;
    const dir = holdingsSortDir.value === 'asc' ? 1 : -1;

    return [...holdings].sort((a, b) => {
        const av = a[key];
        const bv = b[key];
        if (typeof av === 'string' || typeof bv === 'string') {
            return String(av).localeCompare(String(bv)) * dir;
        }
        return ((av as number) - (bv as number)) * dir;
    });
});

const holdingsTotals = computed(() => ({
    invested: finalHoldings.value.reduce((sum, p) => sum + Number(p.remaining_cost), 0),
}));

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

const gainersSortMode = ref<'pct' | 'abs'>('abs');
const topGainers = computed(() => positionPerformance.value?.top_winners[gainersSortMode.value === 'pct' ? 'pnl_pct' : 'net_pnl'] ?? []);
const topLosers = computed(() => positionPerformance.value?.top_losers[gainersSortMode.value === 'pct' ? 'pnl_pct' : 'net_pnl'] ?? []);

// Benchmark returns per calendar month ('YYYY-MM' keyed) for heatmap tooltips.
const benchmarkMonthlyReturns = computed<Record<string, number>>(() => {
    const bench = benchmarkData.value;
    if (!Array.isArray(bench) || bench.length === 0) return {};

    const values = new Map(bench.map((point) => [point.date.substring(0, 10), Number(point.nav)]));
    const monthEnds = new Map<string, string>();
    for (const snapshot of props.dailySnapshots) {
        monthEnds.set(snapshot.date.substring(0, 7), snapshot.date.substring(0, 10));
    }
    const out: Record<string, number> = {};
    let baselineDate = props.dailySnapshots[0]?.date.substring(0, 10);
    for (const [key, endDate] of monthEnds) {
        const base = values.get(baselineDate);
        const end = values.get(endDate);
        if (base !== undefined && base > 0 && end !== undefined) {
            out[key] = (end - base) / base;
        }
        baselineDate = endDate;
    }

    return out;
});

const monthlyReturns = computed(() => {
    if (!props.dailySnapshots || props.dailySnapshots.length === 0) return [];

    const byMonth: Record<string, { firstNav: number; lastNav: number }> = {};
    for (const s of props.dailySnapshots) {
        const key = s.date.substring(0, 7);
        const nav = Number(s.nav);
        if (!byMonth[key]) {
            byMonth[key] = { firstNav: nav, lastNav: nav };
        } else {
            byMonth[key].lastNav = nav;
        }
    }

    const sortedKeys = Object.keys(byMonth).sort();
    const monthlyData: Record<string, number> = {};
    for (let i = 0; i < sortedKeys.length; i++) {
        const key = sortedKeys[i];
        const prevNav = i > 0 ? byMonth[sortedKeys[i - 1]].lastNav : 100;
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
                    yearStartNav = prevKey ? byMonth[prevKey].lastNav : 100;
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

watch(
    () => [props.backtest.status, activeTab.value, props.summaryMetrics],
    () => nextTick(setupScrollspy),
);
</script>
