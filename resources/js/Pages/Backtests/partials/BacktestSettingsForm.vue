<template>
    <form @submit.prevent="$emit('save')">
        <ErrorAlert v-if="errorFieldLabels.length > 0" class="mb-4">
            Please fix the following {{ errorFieldLabels.length === 1 ? 'field' : 'fields' }}:
            {{ errorFieldLabels.join(', ') }}.
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
                <SelectInput
                    v-model="form.weightage"
                    label="Weightage"
                    name="weightage"
                    :options="weightageOptions"
                    :error="form.errors.weightage"
                />
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
                    v-if="form.rebalance_frequency === 'weekly'"
                    v-model="form.rebalance_day"
                    label="Day of Week"
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
                <div>
                    <Toggle v-model="form.execute_next_trading_day" label="Execute Next Trading Day" />
                    <p class="mt-1 text-xs text-gray-500">Decide on rebalance day, execute trades at next day's close</p>
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
                    <p class="mt-1 text-xs text-gray-500">Sell a held stock one trading day before its demerger ex-date and buy a replacement. When disabled, the ex-date price drop is booked as a loss — spun-off shares are not credited.</p>
                </div>
                <div>
                    <Toggle v-model="form.exit_on_be_series" label="Exit on Move to BE Series" />
                    <p class="mt-1 text-xs text-gray-500">Sell a held stock the day its series changes to BE (trade-to-trade) and buy a replacement. BE stocks are also skipped on entry while this is on.</p>
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
                    <p class="mt-1 text-xs text-gray-500">{{ cashCallHelp[form.cash_call] }}</p>
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
                        v-model="form.cash_call_index"
                        label="Cash Call Index / Benchmark"
                        name="cash_call_index"
                        :options="cashCallIndexOptions"
                        :error="form.errors.cash_call_index"
                    />
                    <SelectInput
                        v-model="form.cash_call_dma_period"
                        label="DMA Period"
                        name="cash_call_dma_period"
                        :options="dmaPeriodOptions"
                        :error="form.errors.cash_call_dma_period"
                    />
                </div>
            </div>
        </div>

        <!-- Universe & Ranking -->
        <div class="mt-4 rounded-xl bg-slate-100 p-6 ring-1 ring-slate-200">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-600">Universe &amp; Ranking</h2>
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <SelectInput
                    v-model="form.index"
                    label="Index Universe"
                    name="index"
                    :options="indices"
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
                            min="2011-01-05"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                            :class="{
                                'text-red-900 outline-red-300 placeholder:text-red-400 focus:outline-red-600': form.errors.start_date,
                            }"
                        />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Price data available from 5 Jan 2011</p>
                    <p v-if="form.errors.start_date" class="mt-2 text-sm text-red-600" id="start_date-error">
                        {{ form.errors.start_date }}
                    </p>
                </div>
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

        <!-- Marketcap and P/E filters hidden from UI but values still submitted with defaults -->

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
                        <li>Finally, the combined rank is calculated as the sum of all factor ranks, and stocks are sorted by this combined rank (lowest combined rank = best).</li>
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
                    :disabled="form.processing || running"
                    class="cursor-pointer rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
                    @click="$emit('saveAndRun')"
                >
                    {{ running ? 'Run in progress…' : 'Save & Run Backtest' }}
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
import PurpleAlert from '@/Components/Alerts/PurpleAlert.vue';
import { formatCompactNumber, formatCurrency } from '@/utils/format';
import type { SelectOption } from '@/types/SelectOption';

const props = defineProps<{
    form: InertiaForm<Record<string, any>>;
    running: boolean;
    initialCapital: number | string;
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

const dmaPeriodOptions = [
    { id: '20', name: '20 DMA' },
    { id: '50', name: '50 DMA' },
    { id: '100', name: '100 DMA' },
    { id: '200', name: '200 DMA' },
];

const weekdayOptions = [
    { id: '1', name: 'Monday' },
    { id: '2', name: 'Tuesday' },
    { id: '3', name: 'Wednesday' },
    { id: '4', name: 'Thursday' },
    { id: '5', name: 'Friday' },
];

const sortDirectionOptions = [
    { id: 'desc', name: 'Highest to Lowest' },
    { id: 'asc', name: 'Lowest to Highest' },
];

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

const dmaBasedCashCalls = [
    'full_cash_below_index_dma',
    'only_exits_below_index_dma',
    'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma',
];

const dmaBasedCashCall = computed(() => dmaBasedCashCalls.includes(props.form.cash_call));

const cashCallLabels: Record<string, string> = {
    no_cash_call: 'No cash call',
    cash_call_if_not_enough_stocks: 'Hold cash when not enough stocks qualify',
    full_cash_below_index_dma: 'Full cash below index DMA',
    only_exits_below_index_dma: 'Only exits below index DMA (no new buys)',
    allocate_to_gold_below_index_dma: 'Rotate to gold below index DMA',
    only_exits_allocate_to_gold_below_index_dma: 'Exits to gold below index DMA',
};

const cashCallHelp: Record<string, string> = {
    no_cash_call: 'Always stay fully invested — a replacement is bought whenever a stock is sold',
    cash_call_if_not_enough_stocks: 'When fewer stocks qualify than the portfolio size, the shortfall stays in cash instead of forcing entries',
    full_cash_below_index_dma: 'Sell everything and hold cash while the index trades below its DMA',
    only_exits_below_index_dma: 'While the index is below its DMA, exits still happen but no new stocks are bought',
    allocate_to_gold_below_index_dma: 'Sell everything and hold gold while the index trades below its DMA',
    only_exits_allocate_to_gold_below_index_dma: 'While the index is below its DMA, exit proceeds are parked in gold and no new stocks are bought',
};

const cashCallSelectOptions = computed(() =>
    props.cashCallOptions.map((option) => ({
        id: option.id,
        name: cashCallLabels[option.id] ?? option.name,
    })),
);

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

// --- Rebalance day: weekly select vs monthly number ---

watch(
    () => props.form.rebalance_frequency,
    (frequency) => {
        if (frequency === 'weekly' && Number(props.form.rebalance_day) > 5) {
            props.form.rebalance_day = 1;
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
    rebalance_frequency: 'Rebalance Frequency',
    rebalance_day: 'Rebalance Day',
    cash_call: 'Cash Call',
    cash_call_index: 'Cash Call Index / Benchmark',
    cash_call_dma_period: 'Cash Call DMA Period',
    cash_return_rate: 'Cash Return Rate (% p.a.)',
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
