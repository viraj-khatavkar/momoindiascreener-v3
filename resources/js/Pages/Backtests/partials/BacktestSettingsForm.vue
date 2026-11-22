<template>
    <form @submit.prevent="$emit('save')">
        <ErrorAlert v-if="form.errors.run" class="mb-4">
            {{ form.errors.run }}
        </ErrorAlert>
        <ErrorAlert v-if="errorFieldLabels.length > 0" class="mb-4">
            Please fix the following {{ errorFieldLabels.length === 1 ? 'field' : 'fields' }}:
            {{ errorFieldLabels.join(', ') }}.
        </ErrorAlert>
        <ErrorAlert v-if="form.errors.cash_call_backtest_id && (!dmaBasedCashCall || form.cash_call_source !== 'backtest')" class="mb-4">
            {{ form.errors.cash_call_backtest_id }}
        </ErrorAlert>

        <!-- Portfolio -->
        <div class="rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Portfolio</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput
                    v-model="form.name"
                    label="Name"
                    name="name"
                    :error="form.errors.name"
                />
                <div>
                    <TextInput
                        v-model="form.max_stocks_to_hold"
                        type="number"
                        label="Max Stocks to Hold"
                        name="max_stocks_to_hold"
                        :error="form.errors.max_stocks_to_hold"
                    />
                    <p class="mt-1 text-xs text-gray-500">How many stocks the portfolio holds at most</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.worst_rank_held"
                        type="number"
                        label="Worst Rank Held"
                        name="worst_rank_held"
                        :error="form.errors.worst_rank_held"
                    />
                    <p class="mt-1 text-xs text-gray-500">A held stock is sold when its rank falls beyond this</p>
                </div>
                <div>
                    <SelectInput
                        v-model="form.weightage"
                        label="Weightage"
                        name="weightage"
                        :options="weightageOptions"
                        :error="form.errors.weightage"
                    />
                    <p v-if="form.weightage === 'rank_weighted'" class="mt-1 text-xs text-gray-500">Weight is proportional to 1 / rank on the decision date. Ranks 1, 2, and 3 receive about 54.5%, 27.3%, and 18.2%.</p>
                    <p v-else-if="form.weightage === 'price_weighted'" class="mt-1 text-xs text-gray-500">Weight is proportional to the unadjusted close on the decision date. Stocks priced at ₹1,000 and ₹100 receive about 90.91% and 9.09%.</p>
                    <p v-if="form.weightage !== 'equal_weight'" class="mt-1 text-xs text-gray-500">At each rebalance, add or sell shares to restore target weights. Cash rules, trade restrictions, and whole shares can limit the adjustment. Replacement purchases select stocks not already held.</p>
                    <p v-if="form.weightage === 'rank_weighted' || form.weightage === 'price_weighted'" class="mt-1 text-xs text-gray-500">A retained holding without a valid {{ form.weightage === 'rank_weighted' ? 'rank' : 'price' }} keeps its shares. The remaining capital follows the selected weights.</p>
                </div>
                <div>
                    <label class="block text-sm/6 font-medium text-gray-900">Initial Capital</label>
                    <div class="mt-2 rounded-md bg-slate-200/60 px-3 py-1.5 text-sm/6 text-gray-600 ring-1 ring-slate-300">
                        {{ formatCurrency(initialCapital) }}
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Fixed for every backtest</p>
                </div>
            </div>
        </div>

        <!-- Rebalance -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Rebalance</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <SelectInput
                    v-model="form.rebalance_frequency"
                    label="Rebalance Frequency"
                    name="rebalance_frequency"
                    :options="rebalanceFrequencyOptions"
                    :error="form.errors.rebalance_frequency"
                />
                <SelectInput
                    v-if="usesRebalanceMonth(form.rebalance_frequency)"
                    v-model="form.rebalance_month"
                    label="First Rebalance Month"
                    name="rebalance_month"
                    :options="rebalanceMonthOptions"
                    :error="form.errors.rebalance_month"
                />
                <SelectInput
                    v-if="usesWeekday"
                    v-model="form.rebalance_day"
                    :label="form.rebalance_frequency === 'fortnightly' ? 'Every Other Week On' : 'Day of Week'"
                    name="rebalance_day"
                    :options="weekdayOptions"
                    :error="form.errors.rebalance_day"
                />
                <TextInput
                    v-else
                    v-model="form.rebalance_day"
                    type="number"
                    label="Day of Month (1-28)"
                    name="rebalance_day"
                    :error="form.errors.rebalance_day"
                />
            </div>
            <div class="mt-3 space-y-1 text-sm/5 text-gray-600">
                <p>The opening portfolio is selected on the first trading day.</p>
                <p v-if="firstTarget" aria-live="polite">{{ scheduleDescription }}. The first scheduled date is <strong>{{ firstTarget }}</strong>.</p>
                <p>A market holiday moves the decision to the next trading session in that {{ usesWeekday ? 'week' : 'month' }}. If none follows, the last session of the completed {{ usesWeekday ? 'week' : 'month' }} is used.</p>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <Toggle v-model="form.execute_next_trading_day" label="Execute Next Trading Day" />
                    <p class="mt-1 text-xs text-gray-500">Execute each decision at the next trading session’s close, including the opening trades.</p>
                </div>
                <div>
                    <Toggle v-model="form.skip_circuit_trades" label="Skip Circuit-Hit Trades" />
                    <p class="mt-1 text-xs text-gray-500">If a stock closes at ±5%, ±10%, or ±20% on the execution day, skip its buy/sell. Exits retry on the next rebalance; entries slide to the next-ranked stock.</p>
                </div>
            </div>
        </div>

        <!-- Exit Rules -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Exit Rules</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <Toggle v-model="form.apply_hold_above_dma" label="Hold if Above DMA" />
                    <p class="mt-1 text-xs text-gray-500">Don't exit a stock if its price is above its own moving average</p>
                </div>
                <SelectInput
                    v-if="form.apply_hold_above_dma"
                    v-model="form.hold_above_dma_period"
                    label="Hold Above DMA Period"
                    name="hold_above_dma_period"
                    :options="holdDmaPeriodOptions"
                    :error="form.errors.hold_above_dma_period"
                />
                <div>
                    <Toggle v-model="form.exit_before_demerger" label="Exit Before Demerger" />
                    <p class="mt-1 text-xs text-gray-500">Sell a held stock one trading day before its demerger ex-date. The cash call rule controls whether the proceeds buy a replacement, buy gold, or stay in cash. When disabled, spun-off shares are not credited.</p>
                </div>
                <div>
                    <Toggle v-model="form.exit_on_be_series" label="Exit on Move to BE Series" />
                    <p class="mt-1 text-xs text-gray-500">Sell a held stock the day its series changes to BE (trade-to-trade). The cash call rule controls the use of the proceeds. BE stocks are also skipped on entry while this is on.</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700">Assumed Delisting — 100 Trading Days</p>
                    <p class="mt-1 text-xs text-gray-500">If a stock has no valid price for the next 100 market trading days, sell it at its last traded close. This rule uses future data and overrides DMA and circuit protection. Apply normal sale charges and the cash call rule for replacements. Fewer than 100 missing trading days do not trigger an exit.</p>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Per-Stock Stop Loss</h2>
            <Toggle v-model="form.apply_stop_loss" label="Enable Stock Stop Loss" />
            <div v-if="form.apply_stop_loss" class="mt-4 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <TextInput
                        v-model="form.stop_loss_percentage"
                        type="number"
                        label="Stop Loss (%)"
                        name="stop_loss_percentage"
                        :error="form.errors.stop_loss_percentage"
                    />
                    <p class="mt-1 text-xs text-gray-500">Percentage below the average buy price, excluding charges. Adding shares updates the average buy price.</p>
                </div>
                <div>
                    <Toggle v-model="form.trail_stop_loss" label="Trail Below Highest Close" />
                    <p class="mt-1 text-xs text-gray-500">Use the same percentage below the highest close since entry. The stop only moves up.</p>
                </div>
                <SelectInput
                    v-model="form.stop_loss_proceeds"
                    label="After a Stop-Loss Exit"
                    name="stop_loss_proceeds"
                    :options="stopLossProceedsSelectOptions"
                    :error="form.errors.stop_loss_proceeds"
                />
                <div class="space-y-2 text-xs text-gray-600 sm:col-span-2 lg:col-span-3">
                    <p>Check adjusted closes every day. After a close below the stop, exit at the next trading day's close only if it is lower than the previous close. Otherwise, check again.</p>
                    <p v-if="form.stop_loss_proceeds === 'wait_for_rebalance'">Hold the proceeds as cash until a scheduled rebalance. A rebalance on the exit day can use them. The cash earns the selected cash return, and reinvestment follows the cash-call rule.</p>
                    <p v-else>Buy the next eligible stock not already held at the exit day's close. Cash-call rules and entry restrictions still apply. If no stock qualifies, keep the proceeds in cash.</p>
                    <p>Stop-loss exits take priority over Hold if Above DMA. Circuit restrictions still apply. The exited stock cannot be bought again on the same day. Defensive GOLDBEES holdings follow the cash-call rules.</p>
                </div>
            </div>
        </div>

        <!-- Cash Management -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Cash Management</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <SelectInput
                        v-model="form.cash_call"
                        label="Cash Call"
                        name="cash_call"
                        :options="cashCallSelectOptions"
                        :error="form.errors.cash_call"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ cashCallHelpText }}</p>
                </div>
                <TextInput
                    v-model="form.cash_return_rate"
                    type="number"
                    label="Cash Return Rate (% p.a.)"
                    name="cash_return_rate"
                    :error="form.errors.cash_return_rate"
                />
                <div
                    v-if="dmaBasedCashCall"
                    class="grid grid-cols-1 gap-x-8 gap-y-6 sm:col-span-2 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-3"
                >
                    <SelectInput
                        v-model="form.cash_call_source"
                        label="Signal Source"
                        name="cash_call_source"
                        :options="cashCallSourceOptions"
                        :error="form.errors.cash_call_source"
                    />
                    <SelectInput
                        v-if="form.cash_call_source !== 'backtest'"
                        v-model="form.cash_call_index"
                        label="Cash Call Index / Benchmark"
                        name="cash_call_index"
                        :options="cashCallIndexOptions"
                        :error="form.errors.cash_call_index"
                    />
                    <SelectInput
                        v-else
                        v-model="form.cash_call_backtest_id"
                        label="Source Backtest"
                        name="cash_call_backtest_id"
                        :options="sourceBacktestOptions"
                        :error="form.errors.cash_call_backtest_id"
                    />
                    <SelectInput
                        v-model="form.cash_call_dma_period"
                        label="DMA Period"
                        name="cash_call_dma_period"
                        :options="dmaPeriodOptions"
                        :error="form.errors.cash_call_dma_period"
                    />
                    <SelectInput
                        v-if="goldDmaBasedCashCall"
                        v-model="form.cash_call_gold_dma_period"
                        label="GOLDBEES DMA Period"
                        name="cash_call_gold_dma_period"
                        :options="dmaPeriodOptions"
                        :error="form.errors.cash_call_gold_dma_period"
                    />
                </div>
            </div>
            <InfoAlert v-if="dmaBasedCashCall && form.cash_call_source === 'backtest'" class="mt-4">
                This backtest uses the NAV of {{ sourceBacktestName }}. Each run first reruns the source backtest.
                Changes to the source settings or market data can change the results.
                Saved results remain unchanged until you rerun this backtest.
            </InfoAlert>
            <p v-if="dmaBasedCashCall && form.cash_call_source === 'backtest'" class="mt-2 text-xs text-gray-500">
                Select one of your backtests with No cash call. The source must have {{ form.cash_call_dma_period }} trading days of NAV
                by this backtest's first decision date. Start the source earlier if needed.
            </p>
            <p v-if="dmaBasedCashCall && form.cash_call_source === 'backtest' && cashCallBacktestOptions.length === 0" class="mt-2 text-xs text-amber-700">
                No eligible source is available. Create another backtest with No cash call, then select it here.
            </p>
            <p v-if="dmaBasedCashCall" class="mt-4 text-xs text-gray-500">Cash call signals use the rebalance decision date. Trades follow the selected execution schedule. Replacement purchases after demerger, BE-series, or assumed-delisting exits also follow the cash call rule.</p>
        </div>

        <!-- Transaction Costs -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Transaction Costs</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <TextInput
                        v-model="form.brokerage_rate"
                        type="number"
                        label="Brokerage (%)"
                        name="brokerage_rate"
                        :error="form.errors.brokerage_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">Per order, on both buys and sells — 0 for discount brokers</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.stt_rate"
                        type="number"
                        label="STT (%)"
                        name="stt_rate"
                        :error="form.errors.stt_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">Securities Transaction Tax, charged on both buys and sells</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.stamp_charges_rate"
                        type="number"
                        label="Stamp Duty (%)"
                        name="stamp_charges_rate"
                        :error="form.errors.stamp_charges_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">Charged on buys only</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.transaction_charges_rate"
                        type="number"
                        label="Exchange Transaction Charges (%)"
                        name="transaction_charges_rate"
                        :error="form.errors.transaction_charges_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">NSE: 0.00307% on both buys and sells</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.sebi_charges_rate"
                        type="number"
                        label="SEBI Charges (%)"
                        name="sebi_charges_rate"
                        :error="form.errors.sebi_charges_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">0.0001% (₹10 per crore), on both buys and sells</p>
                </div>
                <div>
                    <TextInput
                        v-model="form.gst_rate"
                        type="number"
                        label="GST (%)"
                        name="gst_rate"
                        :error="form.errors.gst_rate"
                    />
                    <p class="mt-1 text-xs text-gray-500">Applied on brokerage + exchange + SEBI charges</p>
                </div>
            </div>
            <p class="mt-4 border-t border-slate-200 pt-3 text-xs text-gray-600">
                Estimated round trip:
                <span class="font-semibold">{{ costEstimate.roundTrip.toFixed(4) }}%</span> of trade value
                (buy {{ costEstimate.buy.toFixed(4) }}% + sell {{ costEstimate.sell.toFixed(4) }}%)
            </p>
        </div>

        <!-- Universe & Ranking -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Universe &amp; Ranking</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <SelectInput
                    v-model="form.index"
                    label="Index Universe"
                    name="index"
                    :options="universeOptions"
                    :error="form.errors.index"
                />
                <SelectInput
                    v-model="form.sort_by"
                    label="Sort By (Factor)"
                    name="sort_by"
                    :options="sortByOptions"
                    :error="form.errors.sort_by"
                />
                <SelectInput
                    v-model="form.sort_direction"
                    label="Sort Direction"
                    name="sort_direction"
                    :options="sortDirectionOptions"
                    :error="form.errors.sort_direction"
                />
                <SelectInput
                    v-model="form.apply_filters_on"
                    label="Apply Filters on"
                    name="apply_filters_on"
                    :options="applyFiltersOnSelectOptions"
                    :error="form.errors.apply_filters_on"
                />
                <div>
                    <label for="start-date-input" class="block text-sm/6 font-medium text-gray-900">
                        Start Date
                    </label>
                    <div class="mt-2">
                        <input
                            id="start-date-input"
                            v-model="form.start_date"
                            type="date"
                            name="start_date"
                            :min="minimumStartDate"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                            :class="{
                                'text-red-900 outline-red-300 placeholder:text-red-400 focus:outline-red-600': form.errors.start_date,
                            }"
                        />
                    </div>
                    <p class="mt-1 text-xs text-gray-500" data-test="sort-start-date-notice">
                        Earliest start for these settings:
                        {{ formatDate(minimumStartDate) }}
                    </p>
                    <p v-if="form.errors.start_date" class="mt-2 text-sm text-red-600" id="start_date-error">
                        {{ form.errors.start_date }}
                    </p>
                </div>
            </div>
            <p class="mt-4 text-sm text-gray-600" data-test="universe-start-date-notice">
                {{ selectedUniverseName }} membership data starts on {{ formatDate(universeMembershipStart) }}.
                Backtests also require price indicators from {{ formatDate(startDateLimits.default) }} or later.
                The first trade uses the first available trading date on or after the selected start date.
            </p>
            <p v-if="limitedRankingNames.length" class="mt-2 text-sm text-gray-600">
                Ranking data limits: {{ limitedRankingNames.join(' · ') }}.
            </p>
            <p v-if="form.index === 'etf'" class="mt-2 text-sm text-gray-600">
                Market cap and P/E are not available for ETFs. Use another ranking factor.
            </p>
            <div v-if="form.index === 'etf'" class="mt-5 rounded-lg border border-slate-200 bg-white p-4">
                <Toggle v-model="form.unique_etf_per_index" label="One ETF per underlying index" />
                <p class="mt-2 text-sm text-gray-600">
                    After filters pass, keep the ETF with the highest one-year median daily trading volume for each index.
                    A retained holding blocks another ETF for the same index. ETFs with no index mapping remain separate.
                    Defensive gold purchases still follow the cash-call rule.
                </p>
                <p v-if="form.errors.unique_etf_per_index" class="mt-2 text-sm text-red-600">{{ form.errors.unique_etf_per_index }}</p>
            </div>
        </div>

        <!-- General Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`general-${sectionRemounts.general}`"
            as="div"
            id="backtest-section-general"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.general"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('general')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">General Filters</span>
                    <span :class="sectionBadges.general.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.general.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                    <TextInput
                        v-model="form.minimum_return_one_year"
                        type="number"
                        label="Minimum Return One Year (%)"
                        name="minimum_return_one_year"
                        :error="form.errors.minimum_return_one_year"
                    />
                    <div>
                        <label class="block text-sm/6 font-medium text-gray-900">
                            Median Daily Volume One Year (in Rupees)
                        </label>
                        <div class="mt-2 grid grid-cols-2 gap-x-2">
                            <TextInput
                                v-model="form.median_volume_one_year"
                                type="number"
                                label=""
                                name="median_volume_one_year"
                                :error="form.errors.median_volume_one_year"
                            />
                            <SelectInput
                                v-model="selectedMedianVolumeOption"
                                label=""
                                name="median_volume_option"
                                :options="medianVolumeOneYearOptions"
                            />
                        </div>
                    </div>
                </div>
            </DisclosurePanel>
        </Disclosure>

        <!-- Moving Average Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`movingAverage-${sectionRemounts.movingAverage}`"
            as="div"
            id="backtest-section-movingAverage"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.movingAverage"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('movingAverage')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Moving Average Filters</span>
                    <span :class="sectionBadges.movingAverage.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.movingAverage.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="form.apply_ma" label="Apply Moving Average Filters" />
                <p class="mt-1 text-xs text-gray-500">Only include stocks trading on the selected side of their simple moving averages</p>
                <template v-if="form.apply_ma">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Toggle v-model="form.above_ma_200" label="Above 200-day MA" />
                        <Toggle v-model="form.above_ma_100" label="Above 100-day MA" />
                        <Toggle v-model="form.above_ma_50" label="Above 50-day MA" />
                        <Toggle v-model="form.above_ma_20" label="Above 20-day MA" />
                        <Toggle v-model="form.below_ma_200" label="Below 200-day MA" />
                        <Toggle v-model="form.below_ma_100" label="Below 100-day MA" />
                        <Toggle v-model="form.below_ma_50" label="Below 50-day MA" />
                        <Toggle v-model="form.below_ma_20" label="Below 20-day MA" />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Exponential Moving Average Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`ema-${sectionRemounts.ema}`"
            as="div"
            id="backtest-section-ema"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.ema"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('ema')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Exponential Moving Average Filters</span>
                    <span :class="sectionBadges.ema.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.ema.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="form.apply_ema" label="Apply EMA Filters" />
                <p class="mt-1 text-xs text-gray-500">Only include stocks trading on the selected side of their exponential moving averages</p>
                <template v-if="form.apply_ema">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Toggle v-model="form.above_ema_200" label="Above 200-day EMA" />
                        <Toggle v-model="form.above_ema_100" label="Above 100-day EMA" />
                        <Toggle v-model="form.above_ema_50" label="Above 50-day EMA" />
                        <Toggle v-model="form.above_ema_20" label="Above 20-day EMA" />
                        <Toggle v-model="form.below_ema_200" label="Below 200-day EMA" />
                        <Toggle v-model="form.below_ema_100" label="Below 100-day EMA" />
                        <Toggle v-model="form.below_ema_50" label="Below 50-day EMA" />
                        <Toggle v-model="form.below_ema_20" label="Below 20-day EMA" />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Away from High Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`awayFromHigh-${sectionRemounts.awayFromHigh}`"
            as="div"
            id="backtest-section-awayFromHigh"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.awayFromHigh"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('awayFromHigh')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Away from High Filters</span>
                    <span :class="sectionBadges.awayFromHigh.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.awayFromHigh.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="awayFromHighEnabled" label="Limit Distance from Highs" />
                <p class="mt-1 text-xs text-gray-500">Only include stocks trading within the given percentage of their all-time or one-year high</p>
                <template v-if="awayFromHighEnabled">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput
                            v-model="form.away_from_high_all_time"
                            type="number"
                            label="Within Away from All Time High (%)"
                            name="away_from_high_all_time"
                            :error="form.errors.away_from_high_all_time"
                        />
                        <TextInput
                            v-model="form.away_from_high_one_year"
                            type="number"
                            label="Within Away from One Year High (%)"
                            name="away_from_high_one_year"
                            :error="form.errors.away_from_high_one_year"
                        />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Positive Days % Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`positiveDays-${sectionRemounts.positiveDays}`"
            as="div"
            id="backtest-section-positiveDays"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.positiveDays"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('positiveDays')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Percentage of Positive Days Filters</span>
                    <span :class="sectionBadges.positiveDays.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.positiveDays.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="positiveDaysEnabled" label="Require Minimum Positive Days" />
                <p class="mt-1 text-xs text-gray-500">Only include stocks whose share of up days over each window is at least the given percentage</p>
                <template v-if="positiveDaysEnabled">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput
                            v-model="form.positive_days_percent_one_year"
                            type="number"
                            label="Positive Days (%) in One Year"
                            name="positive_days_percent_one_year"
                            :error="form.errors.positive_days_percent_one_year"
                        />
                        <TextInput
                            v-model="form.positive_days_percent_nine_months"
                            type="number"
                            label="Positive Days (%) in Nine Months"
                            name="positive_days_percent_nine_months"
                            :error="form.errors.positive_days_percent_nine_months"
                        />
                        <TextInput
                            v-model="form.positive_days_percent_six_months"
                            type="number"
                            label="Positive Days (%) in Six Months"
                            name="positive_days_percent_six_months"
                            :error="form.errors.positive_days_percent_six_months"
                        />
                        <TextInput
                            v-model="form.positive_days_percent_three_months"
                            type="number"
                            label="Positive Days (%) in Three Months"
                            name="positive_days_percent_three_months"
                            :error="form.errors.positive_days_percent_three_months"
                        />
                        <TextInput
                            v-model="form.positive_days_percent_one_months"
                            type="number"
                            label="Positive Days (%) in One Month"
                            name="positive_days_percent_one_months"
                            :error="form.errors.positive_days_percent_one_months"
                        />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Circuit Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`circuits-${sectionRemounts.circuits}`"
            as="div"
            id="backtest-section-circuits"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.circuits"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('circuits')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Circuit Filters</span>
                    <span :class="sectionBadges.circuits.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.circuits.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="circuitsEnabled" label="Limit Circuit Hits" />
                <p class="mt-1 text-xs text-gray-500">Exclude a stock from the ranking when it has hit more price circuits than the given maximum over each window (values up to 300)</p>
                <template v-if="circuitsEnabled">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput
                            v-model="form.circuits_one_year"
                            type="number"
                            label="Maximum Circuits in One Year"
                            name="circuits_one_year"
                            :error="form.errors.circuits_one_year"
                        />
                        <TextInput
                            v-model="form.circuits_nine_months"
                            type="number"
                            label="Maximum Circuits in Nine Months"
                            name="circuits_nine_months"
                            :error="form.errors.circuits_nine_months"
                        />
                        <TextInput
                            v-model="form.circuits_six_months"
                            type="number"
                            label="Maximum Circuits in Six Months"
                            name="circuits_six_months"
                            :error="form.errors.circuits_six_months"
                        />
                        <TextInput
                            v-model="form.circuits_three_months"
                            type="number"
                            label="Maximum Circuits in Three Months"
                            name="circuits_three_months"
                            :error="form.errors.circuits_three_months"
                        />
                        <TextInput
                            v-model="form.circuits_one_months"
                            type="number"
                            label="Maximum Circuits in One Month"
                            name="circuits_one_months"
                            :error="form.errors.circuits_one_months"
                        />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <Disclosure
            v-slot="{ open }"
            :key="`valuation-${sectionRemounts.valuation}`"
            as="div"
            id="backtest-section-valuation"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.valuation"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('valuation')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Market Cap and P/E Filters</span>
                    <span :class="sectionBadges.valuation.active ? badgeActiveClass : badgeInactiveClass">{{ sectionBadges.valuation.text }}</span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="space-y-5 p-4">
                <p class="text-sm text-gray-600">
                    Available from {{ formatDate(startDateLimits.filters.apply_marketcap) }} for stock universes.
                    Bounds are inclusive. A stock with missing data for an active filter does not qualify.
                </p>
                <p v-if="form.index === 'etf'" class="text-sm text-amber-800">ETFs have no market cap or P/E data. Turn off these filters to use the ETF universe.</p>
                <div>
                    <Toggle v-model="form.apply_marketcap" label="Apply Market Cap Filter" />
                    <p v-if="form.errors.apply_marketcap" class="mt-2 text-sm text-red-600">{{ form.errors.apply_marketcap }}</p>
                    <div v-if="form.apply_marketcap" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <TextInput v-model="form.marketcap_from" type="number" label="Minimum Market Cap (₹ crore)" name="marketcap_from" :error="form.errors.marketcap_from" />
                        <TextInput v-model="form.marketcap_to" type="number" label="Maximum Market Cap (₹ crore)" name="marketcap_to" :error="form.errors.marketcap_to" />
                    </div>
                </div>
                <div>
                    <Toggle v-model="form.apply_pe" label="Apply P/E Filter" />
                    <p v-if="form.errors.apply_pe" class="mt-2 text-sm text-red-600">{{ form.errors.apply_pe }}</p>
                    <div v-if="form.apply_pe" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <TextInput v-model="form.price_to_earnings_from" type="number" label="Minimum P/E" name="price_to_earnings_from" :error="form.errors.price_to_earnings_from" />
                        <TextInput v-model="form.price_to_earnings_to" type="number" label="Maximum P/E" name="price_to_earnings_to" :error="form.errors.price_to_earnings_to" />
                    </div>
                </div>
            </DisclosurePanel>
        </Disclosure>

        <!-- Series -->
        <Disclosure
            v-slot="{ open }"
            :key="`series-${sectionRemounts.series}`"
            as="div"
            id="backtest-section-series"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.series"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('series')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Series</span>
                    <span :class="sectionBadges.series.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.series.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <p class="mb-4 text-xs text-gray-500">EQ allows delivery-based and intraday trading; BE (trade-to-trade) allows delivery-based trading only</p>
                <div class="grid grid-cols-1 gap-4">
                    <Toggle v-model="form.series_eq" label="EQ" />
                    <Toggle v-model="form.series_be" label="BE" />
                </div>
                <div
                    v-if="!form.series_eq && !form.series_be"
                    class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200"
                >
                    No series selected — the series filter is skipped entirely and stocks from every series (EQ, BE, and others) are included.
                </div>
            </DisclosurePanel>
        </Disclosure>

        <!-- Ignore Above Beta -->
        <Disclosure
            v-slot="{ open }"
            :key="`beta-${sectionRemounts.beta}`"
            as="div"
            id="backtest-section-beta"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.beta"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('beta')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Ignore Above Beta</span>
                    <span :class="sectionBadges.beta.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.beta.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <Toggle v-model="betaEnabled" label="Exclude High-Beta Stocks" />
                <p class="mt-1 text-xs text-gray-500">Exclude stocks whose beta is above the given value</p>
                <template v-if="betaEnabled">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                        <TextInput
                            v-model="form.ignore_above_beta"
                            type="number"
                            label="Max Beta"
                            name="ignore_above_beta"
                            :error="form.errors.ignore_above_beta"
                        />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Price (CMP) Range -->
        <Disclosure
            v-slot="{ open }"
            :key="`priceRange-${sectionRemounts.priceRange}`"
            as="div"
            id="backtest-section-priceRange"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.priceRange"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('priceRange')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Price (CMP) Range</span>
                    <span :class="sectionBadges.priceRange.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.priceRange.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <p class="mb-4 text-xs text-gray-500">Only stocks whose last closing price falls within this range (inclusive) are ranked</p>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <TextInput
                        v-model="form.price_from"
                        type="number"
                        label="Price From"
                        name="price_from"
                        :error="form.errors.price_from"
                    />
                    <TextInput
                        v-model="form.price_to"
                        type="number"
                        label="Price To"
                        name="price_to"
                        :error="form.errors.price_to"
                    />
                </div>
            </DisclosurePanel>
        </Disclosure>

        <!-- Multi-Factor Combined Ranking -->
        <Disclosure
            v-slot="{ open }"
            :key="`multiFactor-${sectionRemounts.multiFactor}`"
            as="div"
            id="backtest-section-multiFactor"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.multiFactor"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('multiFactor')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Multi-Factor Combined Ranking</span>
                    <span :class="sectionBadges.multiFactor.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.multiFactor.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <PurpleAlert class="mb-4">
                    <h3 class="mb-4 text-sm font-medium text-purple-800">
                        How does multi-factor combined ranking work:
                    </h3>
                    <ul role="list" class="list-disc space-y-1 pl-5">
                        <li>First, all filters except "Sort By" and "Sort Direction" are applied on the stocks present in the selected index filter.</li>
                        <li>Then, the resultant stocks from step 1 are ranked according to the first factor selected in "Sort By".</li>
                        <li>Next, the resultant stocks from step 1 are also ranked according to the second factor selected in "Sort By (Factor Two)".</li>
                        <li>If "Factor Three" is also enabled, the stocks are also ranked by the third factor.</li>
                        <li>Equal values share the average rank. For example, two stocks tied at ranks 2 and 3 both receive rank 2.5.</li>
                        <li>Finally, the combined rank is calculated as the sum of all factor ranks, and stocks are sorted by this combined rank (lowest combined rank = best).</li>
                        <li>If the totals are equal, stock symbols set the order from A to Z. Final portfolio ranks remain 1, 2, 3, and so on.</li>
                    </ul>
                </PurpleAlert>

                <Toggle v-model="form.apply_factor_two" label="Apply Factor Two" />
                <template v-if="form.apply_factor_two">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <SelectInput
                            v-model="form.factor_two_sort_by"
                            label="Sort By (Factor Two)"
                            name="factor_two_sort_by"
                            :options="sortByOptions"
                            :error="form.errors.factor_two_sort_by"
                        />
                        <SelectInput
                            v-model="form.factor_two_sort_direction"
                            label="Sort Direction"
                            name="factor_two_sort_direction"
                            :options="sortDirectionOptions"
                            :error="form.errors.factor_two_sort_direction"
                        />
                    </div>
                </template>

                <div class="mt-8"></div>

                <Toggle v-model="form.apply_factor_three" label="Apply Factor Three" />
                <template v-if="form.apply_factor_three">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <SelectInput
                            v-model="form.factor_three_sort_by"
                            label="Sort By (Factor Three)"
                            name="factor_three_sort_by"
                            :options="sortByOptions"
                            :error="form.errors.factor_three_sort_by"
                        />
                        <SelectInput
                            v-model="form.factor_three_sort_direction"
                            label="Sort Direction"
                            name="factor_three_sort_direction"
                            :options="sortDirectionOptions"
                            :error="form.errors.factor_three_sort_direction"
                        />
                    </div>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Custom Filters -->
        <Disclosure
            v-slot="{ open }"
            :key="`customFilters-${sectionRemounts.customFilters}`"
            as="div"
            id="backtest-section-customFilters"
            class="mt-4 rounded-md border border-gray-300 bg-white"
            :default-open="sectionOpen.customFilters"
        >
            <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3" @click="toggleSection('customFilters')">
                <span class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">Custom Filters</span>
                    <span :class="sectionBadges.customFilters.active ? badgeActiveClass : badgeInactiveClass">
                        {{ sectionBadges.customFilters.text }}
                    </span>
                </span>
                <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
            </DisclosureButton>
            <DisclosurePanel class="p-4">
                <template v-for="(filter, idx) in customFilterKeys" :key="filter.apply">
                    <hr v-if="idx > 0" class="my-4" />
                    <Toggle v-model="form[filter.apply]" :label="`Apply Custom Filter ${filter.label}`" />
                    <template v-if="form[filter.apply]">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                            <SelectInput
                                v-model="form[filter.valueOne]"
                                label="Value One"
                                :name="filter.valueOne"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form[filter.operator]"
                                label="Operator"
                                :name="filter.operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form[filter.valueTwo]"
                                label="Value Two"
                                :name="filter.valueTwo"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>
                </template>
            </DisclosurePanel>
        </Disclosure>

        <!-- Sticky action bar -->
        <div class="sticky bottom-0 z-10 mt-6 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:rounded-t-lg">
            <div class="flex flex-wrap items-center gap-4">
                <button
                    type="button"
                    :disabled="form.processing || runBlocked"
                    class="cursor-pointer rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
                    @click="$emit('saveAndRun')"
                >
                    {{ running ? 'Run in progress…' : 'Save and Run' }}
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="cursor-pointer rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs ring-1 ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-75"
                >
                    Save
                </button>
                <button
                    v-if="form.isDirty"
                    type="button"
                    class="cursor-pointer text-sm font-medium text-gray-500 hover:text-gray-700 hover:underline"
                    @click="form.reset(); form.clearErrors()"
                >
                    Discard changes
                </button>
                <span v-if="form.isDirty" class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    Unsaved changes
                </span>
                <span v-if="Object.keys(form.errors).length > 0" class="text-xs font-medium text-red-600">
                    Please fix the errors above
                </span>
            </div>
        </div>

        <!-- Danger zone -->
        <div class="mt-8 border-t border-gray-200 pt-5">
            <button
                type="button"
                class="cursor-pointer text-sm font-medium text-red-600 hover:text-red-700 hover:underline"
                @click="$emit('delete')"
            >
                Delete this backtest…
            </button>
        </div>
    </form>
</template>

<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import PurpleAlert from '@/Components/Alerts/PurpleAlert.vue';
import { formatCompactNumber, formatCurrency, formatDate } from '@/utils/format';
import { firstRebalanceTarget, rebalanceDescription, rebalanceMonthOptions, suggestedRebalanceMonth, usesRebalanceMonth, weekdayOptions } from '@/utils/backtestRebalance';
import type { SelectOption } from '@/types/SelectOption';

const props = defineProps<{
    form: InertiaForm<Record<string, any>>;
    running: boolean;
    runBlocked: boolean;
    initialCapital: number | string;
    indices: SelectOption[];
    sortByOptions: SelectOption[];
    startDateLimits: { default: string; universes: Record<string, string>; sorts: Record<string, string>; filters: Record<string, string> };
    applyFiltersOnOptions: SelectOption[];
    customFilterValueOptions: SelectOption[];
    customFilterComparatorOptions: SelectOption[];
    rebalanceFrequencyOptions: SelectOption[];
    savedRebalanceMonth: number | null;
    weightageOptions: SelectOption[];
    cashCallOptions: SelectOption[];
    stopLossProceedsOptions: SelectOption[];
    cashCallIndexOptions: SelectOption[];
    cashCallBacktestOptions: SelectOption[];
}>();

defineEmits<{
    save: [];
    saveAndRun: [];
    delete: [];
}>();

const holdDmaPeriodOptions = [
    { id: '20', name: '20 DMA' },
    { id: '50', name: '50 DMA' },
    { id: '100', name: '100 DMA' },
    { id: '200', name: '200 DMA' },
];

const stopLossProceedsSelectOptions = computed(() =>
    props.stopLossProceedsOptions.map((option) => ({
        id: option.id,
        name: option.id === 'wait_for_rebalance' ? 'Hold cash until rebalance' : 'Buy next stock on exit day',
    })),
);

const dmaPeriodOptions = [
    { id: '20', name: '20 DMA' },
    { id: '50', name: '50 DMA' },
    { id: '100', name: '100 DMA' },
    { id: '200', name: '200 DMA' },
];

const sortDirectionOptions = [
    { id: 'desc', name: 'Highest to Lowest' },
    { id: 'asc', name: 'Lowest to Highest' },
];

const activeSorts = computed<(string | null)[]>(() => [
    props.form.sort_by,
    props.form.apply_factor_two ? props.form.factor_two_sort_by : null,
    props.form.apply_factor_three ? props.form.factor_three_sort_by : null,
]);

const universeMembershipStart = computed(() => props.startDateLimits.universes[props.form.index] ?? props.startDateLimits.default);
const universeMinimumStart = computed(() => universeMembershipStart.value > props.startDateLimits.default
    ? universeMembershipStart.value : props.startDateLimits.default);
const selectedUniverseName = computed(() => props.indices.find((option) => option.id === props.form.index)?.name ?? 'Selected universe');
const universeOptions = computed(() => props.indices.map((option) => {
    const membershipStart = props.startDateLimits.universes[option.id] ?? props.startDateLimits.default;
    const start = membershipStart > props.startDateLimits.default ? membershipStart : props.startDateLimits.default;
    return { ...option, name: `${option.name} · from ${formatDate(start)}` };
}));
const limitedRankingNames = computed(() => [...new Set(activeSorts.value)].flatMap((sort) => {
    const date = sort ? props.startDateLimits.sorts[sort] : null;
    return date ? [`${props.sortByOptions.find((option) => option.id === sort)?.name ?? sort}: ${formatDate(date)}`] : [];
}));
const activeDateFilters = computed(() => Object.keys(props.startDateLimits.filters).filter((field) => props.form[field]));
const minimumStartDate = computed(() => {
    const dates = [universeMinimumStart.value,
        ...activeSorts.value.flatMap((sort) => sort && props.startDateLimits.sorts[sort] ? [props.startDateLimits.sorts[sort]] : []),
        ...activeDateFilters.value.map((field) => props.startDateLimits.filters[field]),
    ];
    return dates.sort().at(-1) ?? props.startDateLimits.default;
});

watch([() => props.form.index, activeSorts, activeDateFilters], ([universe, sorts, filters], previous) => {
    const [previousUniverse, previousSorts, previousFilters] = previous ?? [];
    const changedUniverse = previousUniverse !== undefined && universe !== previousUniverse;
    const hasNewLimitedSort = sorts.some((sort, index) =>
        sort !== null && props.startDateLimits.sorts[sort] !== undefined && sort !== previousSorts?.[index],
    );
    const enabledDateFilter = filters.some((field) => !previousFilters?.includes(field));
    if (changedUniverse || (previousUniverse !== undefined && (hasNewLimitedSort || enabledDateFilter)) || props.form.start_date < minimumStartDate.value) {
        props.form.start_date = minimumStartDate.value;
    }
}, { immediate: true });

const medianVolumeOneYearOptions = [
    { id: '1000000', name: '10 Lakh' },
    { id: '2000000', name: '20 Lakh' },
    { id: '5000000', name: '50 Lakh' },
    { id: '10000000', name: '1 Crore' },
    { id: '20000000', name: '2 Crore' },
    { id: '50000000', name: '5 Crore' },
    { id: '100000000', name: '10 Crore' },
    { id: 'custom', name: 'Custom' },
];

const customFilterKeys = [
    { label: 'One', apply: 'apply_custom_filter_one', valueOne: 'custom_filter_one_value_one', operator: 'custom_filter_one_operator', valueTwo: 'custom_filter_one_value_two' },
    { label: 'Two', apply: 'apply_custom_filter_two', valueOne: 'custom_filter_two_value_one', operator: 'custom_filter_two_operator', valueTwo: 'custom_filter_two_value_two' },
    { label: 'Three', apply: 'apply_custom_filter_three', valueOne: 'custom_filter_three_value_one', operator: 'custom_filter_three_operator', valueTwo: 'custom_filter_three_value_two' },
    { label: 'Four', apply: 'apply_custom_filter_four', valueOne: 'custom_filter_four_value_one', operator: 'custom_filter_four_operator', valueTwo: 'custom_filter_four_value_two' },
    { label: 'Five', apply: 'apply_custom_filter_five', valueOne: 'custom_filter_five_value_one', operator: 'custom_filter_five_operator', valueTwo: 'custom_filter_five_value_two' },
];

// --- Cash call ---

const cashCallSourceOptions = [
    { id: 'index', name: 'Market index' },
    { id: 'backtest', name: 'Another backtest’s NAV' },
];

const sourceBacktestOptions = computed(() => [
    { id: '', name: 'Select a source backtest' },
    ...props.cashCallBacktestOptions,
]);

const sourceBacktestName = computed(() => props.cashCallBacktestOptions
    .find((option) => String(option.id) === String(props.form.cash_call_backtest_id))?.name ?? 'the selected source backtest');

const dmaBasedCashCalls = [
    'full_cash_below_index_dma',
    'only_exits_below_index_dma',
    'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_above_dma_below_index_dma',
];

const dmaBasedCashCall = computed(() => dmaBasedCashCalls.includes(props.form.cash_call));
const goldDmaBasedCashCall = computed(() => props.form.cash_call === 'only_exits_allocate_to_gold_above_dma_below_index_dma');

const cashCallLabels: Record<string, string> = {
    no_cash_call: 'No cash call',
    cash_call_if_not_enough_stocks: 'Hold cash when not enough stocks qualify',
    full_cash_below_index_dma: 'Full cash below index DMA',
    only_exits_below_index_dma: 'Only exits below index DMA (no new buys)',
    allocate_to_gold_below_index_dma: 'Rotate to gold below index DMA',
    only_exits_allocate_to_gold_below_index_dma: 'Exits to gold below index DMA',
    only_exits_allocate_to_gold_above_dma_below_index_dma: 'Exits to gold below index DMA and gold above its DMA',
};

const cashCallHelp = computed<Record<string, string>>(() => ({
    no_cash_call: 'Keep capital in stocks. Use eligible replacements or add to existing stocks. Keep holdings if no replacement is available. Whole-share quantities and trade restrictions can leave unused cash.',
    cash_call_if_not_enough_stocks: props.form.weightage === 'equal_weight'
        ? 'Divide available cash by the empty stock slots. Buy only eligible new stocks and keep the unused portions in cash. For example, 4 empty slots and 2 entries use half the available cash. Holdings that remain in the portfolio keep their share quantities.'
        : 'At each rebalance, keep the portion for unfilled stock slots in cash and apply the selected weights to the invested portion. For example, 8 holdings out of 10 target 80% in stocks and 20% in cash. Actual cash also depends on trade restrictions, charges, and whole shares.',
    full_cash_below_index_dma: 'Sell everything and hold cash while the index trades below its DMA',
    only_exits_below_index_dma: 'While the index is below its DMA, exits still happen but no new stocks are bought',
    allocate_to_gold_below_index_dma: 'Sell everything and hold gold while the index trades below its DMA',
    only_exits_allocate_to_gold_below_index_dma: 'While the index is below its DMA, exit proceeds are parked in gold and no new stocks are bought',
    only_exits_allocate_to_gold_above_dma_below_index_dma: 'Below the index DMA, only exit stocks. Buy GOLDBEES with available cash only when its adjusted close is above its selected DMA. Otherwise, keep the cash and any existing gold. Sell gold when the index recovers.',
}));

const cashCallHelpText = computed(() => {
    const help = cashCallHelp.value[props.form.cash_call];
    return props.form.cash_call_source === 'backtest' ? help?.replaceAll('index', 'source NAV') : help;
});

const cashCallSelectOptions = computed(() =>
    props.cashCallOptions.map((option) => ({
        id: option.id,
        name: (cashCallLabels[option.id] ?? option.name).replace('index DMA', props.form.cash_call_source === 'backtest' ? 'source NAV DMA' : 'index DMA'),
    })),
);

// --- Transaction costs (rates are % of trade value; GST applies on brokerage + exchange + SEBI) ---

const costEstimate = computed(() => {
    const brokerage = Number(props.form.brokerage_rate) || 0;
    const stt = Number(props.form.stt_rate) || 0;
    const txn = Number(props.form.transaction_charges_rate) || 0;
    const sebi = Number(props.form.sebi_charges_rate) || 0;
    const gst = Number(props.form.gst_rate) || 0;
    const stamp = Number(props.form.stamp_charges_rate) || 0;

    const gstCharge = (gst / 100) * (brokerage + txn + sebi);
    const buy = brokerage + stt + stamp + txn + sebi + gstCharge;
    const sell = brokerage + stt + txn + sebi + gstCharge;

    return { buy, sell, roundTrip: buy + sell };
});

// --- Apply filters on ---

const applyFiltersOnLabels: Record<string, string> = {
    all: 'All stocks of selected index',
    top_decile: 'Top 10% by rank',
    top_two_decile: 'Top 20% by rank',
    top_three_decile: 'Top 30% by rank',
    top_four_decile: 'Top 40% by rank',
    top_five_decile: 'Top 50% by rank',
    top_50: 'Top 50 stocks',
    top_100: 'Top 100 stocks',
};

const applyFiltersOnSelectOptions = computed(() =>
    props.applyFiltersOnOptions.map((option) => ({
        id: option.id,
        name: applyFiltersOnLabels[option.id] ?? option.name,
    })),
);

const usesWeekday = computed(() => props.form.rebalance_frequency === 'weekly' || props.form.rebalance_frequency === 'fortnightly');
const firstTarget = computed(() =>
    firstRebalanceTarget(props.form.rebalance_frequency, props.form.start_date, Number(props.form.rebalance_day), props.form.rebalance_month),
);
const scheduleDescription = computed(() =>
    rebalanceDescription(props.form.rebalance_frequency, Number(props.form.rebalance_day), props.form.rebalance_month),
);

watch(
    () => props.form.rebalance_frequency,
    (frequency) => {
        if (usesWeekday.value && !weekdayOptions.some(({ id }) => id === Number(props.form.rebalance_day))) {
            props.form.rebalance_day = 1;
        }
        if (usesRebalanceMonth(frequency)) {
            props.form.rebalance_month ??= suggestedRebalanceMonth(props.form.start_date, Number(props.form.rebalance_day));
        } else {
            props.form.rebalance_month = props.savedRebalanceMonth;
        }
    },
);

// --- Median volume preset (two-way sync) ---

const initialMedianVolumeMatch = medianVolumeOneYearOptions.find(
    (option) => option.id !== 'custom' && String(option.id) === String(props.form.median_volume_one_year),
);

const selectedMedianVolumeOption = ref(initialMedianVolumeMatch ? initialMedianVolumeMatch.id : 'custom');

watch(selectedMedianVolumeOption, (newValue) => {
    if (newValue !== 'custom' && String(props.form.median_volume_one_year) !== String(newValue)) {
        props.form.median_volume_one_year = Number(newValue);
    }
});

watch(
    () => props.form.median_volume_one_year,
    (newValue) => {
        const match = medianVolumeOneYearOptions.find(
            (option) => option.id !== 'custom' && String(option.id) === String(newValue),
        );
        selectedMedianVolumeOption.value = match ? match.id : 'custom';
    },
);

// --- Sentinel-backed filters exposed as explicit toggles ---
// Form values keep the sentinel convention (100 / 0 / 300 / 100) so the backend contract is untouched.

const positiveDaysFields = [
    'positive_days_percent_one_year',
    'positive_days_percent_nine_months',
    'positive_days_percent_six_months',
    'positive_days_percent_three_months',
    'positive_days_percent_one_months',
];

const circuitFields = [
    'circuits_one_year',
    'circuits_nine_months',
    'circuits_six_months',
    'circuits_three_months',
    'circuits_one_months',
];

const awayFromHighActive = computed(
    () => Number(props.form.away_from_high_all_time) < 100 || Number(props.form.away_from_high_one_year) < 100,
);
const positiveDaysActive = computed(() => positiveDaysFields.some((field) => Number(props.form[field]) > 0));
// Anything below the 300 sentinel counts as active so 251-299 inputs are never silently wiped.
const circuitsActive = computed(() => circuitFields.some((field) => Number(props.form[field]) < 300));
const betaActive = computed(() => Number(props.form.ignore_above_beta) < 100);

const awayFromHighEnabled = ref(awayFromHighActive.value);
const positiveDaysEnabled = ref(positiveDaysActive.value);
const circuitsEnabled = ref(circuitsActive.value);
const betaEnabled = ref(betaActive.value);

watch(awayFromHighActive, (active) => (awayFromHighEnabled.value = active));
// Upward-only sync: the positive-days "off" sentinel (0) is also what a cleared
// number input coerces to, so auto-disabling here would collapse the panel and
// wipe the other fields while the user is mid-edit.
watch(positiveDaysActive, (active) => {
    if (active) {
        positiveDaysEnabled.value = true;
    }
});
watch(circuitsActive, (active) => (circuitsEnabled.value = active));
watch(betaActive, (active) => (betaEnabled.value = active));

watch(awayFromHighEnabled, (enabled) => {
    if (enabled) {
        if (!awayFromHighActive.value) {
            props.form.away_from_high_all_time = 25;
            props.form.away_from_high_one_year = 25;
        }
    } else {
        props.form.away_from_high_all_time = 100;
        props.form.away_from_high_one_year = 100;
    }
});

watch(positiveDaysEnabled, (enabled) => {
    if (enabled) {
        if (!positiveDaysActive.value) {
            props.form.positive_days_percent_one_year = 50;
        }
    } else {
        positiveDaysFields.forEach((field) => (props.form[field] = 0));
    }
});

watch(circuitsEnabled, (enabled) => {
    if (enabled) {
        if (!circuitsActive.value) {
            circuitFields.forEach((field) => (props.form[field] = 10));
        }
    } else {
        circuitFields.forEach((field) => (props.form[field] = 300));
    }
});

watch(betaEnabled, (enabled) => {
    if (enabled) {
        if (!betaActive.value) {
            props.form.ignore_above_beta = 1.5;
        }
    } else {
        // The server serializes this decimal as '100.00' — write the same shape
        // so an on/off round-trip doesn't leave the form phantom-dirty.
        props.form.ignore_above_beta = '100.00';
    }
});

// --- Accordion badges, open state, and error auto-expand ---

type SectionKey =
    | 'general'
    | 'movingAverage'
    | 'ema'
    | 'awayFromHigh'
    | 'positiveDays'
    | 'valuation'
    | 'circuits'
    | 'series'
    | 'beta'
    | 'priceRange'
    | 'multiFactor'
    | 'customFilters';

const sectionKeysList: SectionKey[] = [
    'general',
    'movingAverage',
    'ema',
    'awayFromHigh',
    'positiveDays',
    'valuation',
    'circuits',
    'series',
    'beta',
    'priceRange',
    'multiFactor',
    'customFilters',
];

const badgeActiveClass = 'rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700';
const badgeInactiveClass = 'rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500';

const maToggleFields = ['above_ma_200', 'above_ma_100', 'above_ma_50', 'above_ma_20', 'below_ma_200', 'below_ma_100', 'below_ma_50', 'below_ma_20'];
const emaToggleFields = ['above_ema_200', 'above_ema_100', 'above_ema_50', 'above_ema_20', 'below_ema_200', 'below_ema_100', 'below_ema_50', 'below_ema_20'];

const sectionBadges = computed<Record<SectionKey, { text: string; active: boolean }>>(() => {
    const minimumReturn = Number(props.form.minimum_return_one_year);
    const volumeBadge = `Vol ≥ ₹${formatCompactNumber(Number(props.form.median_volume_one_year))}`;
    const maCount = maToggleFields.filter((field) => props.form[field]).length;
    const emaCount = emaToggleFields.filter((field) => props.form[field]).length;
    const positiveDaysCount = positiveDaysFields.filter((field) => Number(props.form[field]) > 0).length;
    const circuitsCount = circuitFields.filter((field) => Number(props.form[field]) < 300).length;
    const awayParts: string[] = [];
    if (Number(props.form.away_from_high_all_time) < 100) {
        awayParts.push(`ATH ≤ ${props.form.away_from_high_all_time}%`);
    }
    if (Number(props.form.away_from_high_one_year) < 100) {
        awayParts.push(`1Y ≤ ${props.form.away_from_high_one_year}%`);
    }
    const priceRangeIsActive = Number(props.form.price_from) > 0 || Number(props.form.price_to) < 10000000;
    const extraFactors = (props.form.apply_factor_two ? 1 : 0) + (props.form.apply_factor_three ? 1 : 0);
    const customFiltersCount = customFilterKeys.filter((filter) => props.form[filter.apply]).length;

    return {
        general: {
            text: minimumReturn !== 0 ? `${volumeBadge} · Ret ≥ ${minimumReturn}%` : volumeBadge,
            active: true,
        },
        movingAverage: {
            text: props.form.apply_ma ? (maCount > 0 ? `${maCount} active` : 'On') : 'Off',
            active: Boolean(props.form.apply_ma),
        },
        ema: {
            text: props.form.apply_ema ? (emaCount > 0 ? `${emaCount} active` : 'On') : 'Off',
            active: Boolean(props.form.apply_ema),
        },
        awayFromHigh: {
            text: awayParts.length > 0 ? awayParts.join(' · ') : 'Off',
            active: awayParts.length > 0,
        },
        positiveDays: {
            text: positiveDaysCount > 0 ? `${positiveDaysCount} active` : 'Off',
            active: positiveDaysCount > 0,
        },
        valuation: {
            text: [props.form.apply_marketcap && 'Market cap', props.form.apply_pe && 'P/E'].filter(Boolean).join(' + ') || 'Off',
            active: Boolean(props.form.apply_marketcap || props.form.apply_pe),
        },
        circuits: {
            text: circuitsCount > 0 ? `${circuitsCount} active` : 'Off',
            active: circuitsCount > 0,
        },
        series:
            props.form.series_eq && props.form.series_be
                ? { text: 'EQ + BE', active: false }
                : props.form.series_eq
                    ? { text: 'BE off', active: true }
                    : props.form.series_be
                        ? { text: 'EQ off', active: true }
                        : { text: 'None', active: true },
        beta: {
            text: betaActive.value ? `β ≤ ${props.form.ignore_above_beta}` : 'Off',
            active: betaActive.value,
        },
        priceRange: {
            text: priceRangeIsActive
                ? `₹${formatCompactNumber(Number(props.form.price_from))} – ₹${formatCompactNumber(Number(props.form.price_to))}`
                : 'Off',
            active: priceRangeIsActive,
        },
        multiFactor: {
            text: extraFactors > 0 ? `${1 + extraFactors} factors` : 'Off',
            active: extraFactors > 0,
        },
        customFilters: {
            text: customFiltersCount > 0 ? `${customFiltersCount} active` : 'Off',
            active: customFiltersCount > 0,
        },
    };
});

const sectionOpen = reactive<Record<SectionKey, boolean>>(
    Object.fromEntries(sectionKeysList.map((section) => [section, sectionBadges.value[section].active])) as Record<SectionKey, boolean>,
);

const sectionRemounts = reactive<Record<SectionKey, number>>(
    Object.fromEntries(sectionKeysList.map((section) => [section, 0])) as Record<SectionKey, number>,
);

function toggleSection(section: SectionKey): void {
    sectionOpen[section] = !sectionOpen[section];
}

const sectionFields: Record<SectionKey, string[]> = {
    general: ['median_volume_one_year', 'minimum_return_one_year'],
    movingAverage: ['apply_ma', ...maToggleFields],
    ema: ['apply_ema', ...emaToggleFields],
    awayFromHigh: ['away_from_high_all_time', 'away_from_high_one_year'],
    positiveDays: positiveDaysFields,
    valuation: ['apply_marketcap', 'marketcap_from', 'marketcap_to', 'apply_pe', 'price_to_earnings_from', 'price_to_earnings_to'],
    circuits: circuitFields,
    series: ['series_eq', 'series_be'],
    beta: ['ignore_above_beta'],
    priceRange: ['price_from', 'price_to'],
    multiFactor: ['apply_factor_two', 'factor_two_sort_by', 'factor_two_sort_direction', 'apply_factor_three', 'factor_three_sort_by', 'factor_three_sort_direction'],
    customFilters: customFilterKeys.flatMap((filter) => [filter.apply, filter.valueOne, filter.operator, filter.valueTwo]),
};

const fieldSection: Record<string, SectionKey> = {};
sectionKeysList.forEach((section) => {
    sectionFields[section].forEach((field) => {
        fieldSection[field] = section;
    });
});

watch(
    () => props.form.errors,
    async (errors) => {
        const errorKeys = Object.keys(errors ?? {});
        if (errorKeys.length === 0) {
            return;
        }
        const section = errorKeys.map((key) => fieldSection[key]).find((match) => match !== undefined);
        if (!section) {
            return;
        }
        if (!sectionOpen[section]) {
            sectionOpen[section] = true;
            sectionRemounts[section] += 1;
        }
        await nextTick();
        document.getElementById(`backtest-section-${section}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    { deep: true },
);

// --- Human-readable labels for the validation error summary ---

const fieldLabels: Record<string, string> = {
    name: 'Name',
    max_stocks_to_hold: 'Max Stocks to Hold',
    worst_rank_held: 'Worst Rank Held',
    weightage: 'Weightage',
    apply_hold_above_dma: 'Hold if Above DMA',
    hold_above_dma_period: 'Hold Above DMA Period',
    execute_next_trading_day: 'Execute Next Trading Day',
    skip_circuit_trades: 'Skip Circuit-Hit Trades',
    exit_before_demerger: 'Exit Before Demerger',
    exit_on_be_series: 'Exit on Move to BE Series',
    apply_stop_loss: 'Enable Stock Stop Loss',
    stop_loss_percentage: 'Stop Loss (%)',
    trail_stop_loss: 'Trail Below Highest Close',
    stop_loss_proceeds: 'After a Stop-Loss Exit',
    rebalance_frequency: 'Rebalance Frequency',
    rebalance_day: 'Rebalance Day',
    rebalance_month: 'First Rebalance Month',
    cash_call: 'Cash Call',
    cash_call_index: 'Cash Call Index / Benchmark',
    cash_call_source: 'Signal Source',
    cash_call_backtest_id: 'Source Backtest',
    cash_call_dma_period: 'Cash Call DMA Period',
    cash_call_gold_dma_period: 'GOLDBEES DMA Period',
    cash_return_rate: 'Cash Return Rate (% p.a.)',
    brokerage_rate: 'Brokerage (%)',
    stt_rate: 'STT (%)',
    transaction_charges_rate: 'Exchange Transaction Charges (%)',
    sebi_charges_rate: 'SEBI Charges (%)',
    gst_rate: 'GST (%)',
    stamp_charges_rate: 'Stamp Duty (%)',
    apply_marketcap: 'Market Cap Filter',
    apply_pe: 'P/E Filter',
    marketcap_from: 'Minimum Market Cap',
    marketcap_to: 'Maximum Market Cap',
    price_to_earnings_from: 'Minimum P/E',
    price_to_earnings_to: 'Maximum P/E',
    unique_etf_per_index: 'One ETF per underlying index',
    start_date: 'Start Date',
    index: 'Index Universe',
    sort_by: 'Sort By (Factor)',
    sort_direction: 'Sort Direction',
    apply_filters_on: 'Apply Filters on',
    median_volume_one_year: 'Median Daily Volume One Year',
    minimum_return_one_year: 'Minimum Return One Year (%)',
    away_from_high_all_time: 'Within Away from All Time High (%)',
    away_from_high_one_year: 'Within Away from One Year High (%)',
    positive_days_percent_one_year: 'Positive Days (%) in One Year',
    positive_days_percent_nine_months: 'Positive Days (%) in Nine Months',
    positive_days_percent_six_months: 'Positive Days (%) in Six Months',
    positive_days_percent_three_months: 'Positive Days (%) in Three Months',
    positive_days_percent_one_months: 'Positive Days (%) in One Month',
    circuits_one_year: 'Maximum Circuits in One Year',
    circuits_nine_months: 'Maximum Circuits in Nine Months',
    circuits_six_months: 'Maximum Circuits in Six Months',
    circuits_three_months: 'Maximum Circuits in Three Months',
    circuits_one_months: 'Maximum Circuits in One Month',
    series_eq: 'Series EQ',
    series_be: 'Series BE',
    ignore_above_beta: 'Max Beta',
    price_from: 'Price From',
    price_to: 'Price To',
    apply_factor_two: 'Apply Factor Two',
    factor_two_sort_by: 'Sort By (Factor Two)',
    factor_two_sort_direction: 'Sort Direction (Factor Two)',
    apply_factor_three: 'Apply Factor Three',
    factor_three_sort_by: 'Sort By (Factor Three)',
    factor_three_sort_direction: 'Sort Direction (Factor Three)',
};

function prettifyFieldKey(key: string): string {
    return key
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

const errorFieldLabels = computed(() =>
    Object.keys(props.form.errors).map((key) => fieldLabels[key] ?? prettifyFieldKey(key)),
);
</script>
