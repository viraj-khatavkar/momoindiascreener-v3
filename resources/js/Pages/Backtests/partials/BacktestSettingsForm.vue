<template>
    <form @submit.prevent="$emit('save')">
        <ErrorAlert v-if="Object.keys(form.errors).length > 0" class="mb-4">
            There are some errors in your form. Please fix them.
        </ErrorAlert>

        <!-- Backtest-Specific Settings -->
        <div class="bg-slate-100 p-8">
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput
                    v-model="form.name"
                    label="Name"
                    name="name"
                    :error="form.errors.name"
                />
                <TextInput
                    v-model="form.max_stocks_to_hold"
                    type="number"
                    label="Max Stocks to Hold"
                    name="max_stocks_to_hold"
                    :error="form.errors.max_stocks_to_hold"
                />
                <TextInput
                    v-model="form.worst_rank_held"
                    type="number"
                    label="Worst Rank Held"
                    name="worst_rank_held"
                    :error="form.errors.worst_rank_held"
                />
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
                    <Toggle v-model="form.execute_next_trading_day" label="Execute Next Trading Day" />
                    <p class="mt-1 text-xs text-gray-500">Decide on rebalance day, execute trades at next day's close</p>
                </div>
                <div>
                    <Toggle v-model="form.skip_circuit_trades" label="Skip Circuit-Hit Trades" />
                    <p class="mt-1 text-xs text-gray-500">If a stock closes at ±5%, ±10%, or ±20% on the execution day, skip its buy/sell. Exits retry on the next rebalance; entries slide to the next-ranked stock.</p>
                </div>
                <SelectInput
                    v-model="form.rebalance_frequency"
                    label="Rebalance Frequency"
                    name="rebalance_frequency"
                    :options="rebalanceFrequencyOptions"
                    :error="form.errors.rebalance_frequency"
                />
                <TextInput
                    v-model="form.rebalance_day"
                    type="number"
                    :label="form.rebalance_frequency === 'weekly' ? 'Day of Week (1=Mon, 5=Fri)' : 'Day of Month (1-28)'"
                    name="rebalance_day"
                    :error="form.errors.rebalance_day"
                />
                <SelectInput
                    v-model="form.weightage"
                    label="Weightage"
                    name="weightage"
                    :options="weightageOptions"
                    :error="form.errors.weightage"
                />
                <SelectInput
                    v-model="form.cash_call"
                    label="Cash Call"
                    name="cash_call"
                    :options="cashCallOptions"
                    :error="form.errors.cash_call"
                />
                <SelectInput
                    v-if="dmaBasedCashCall"
                    v-model="form.cash_call_index"
                    label="Cash Call Index / Benchmark"
                    name="cash_call_index"
                    :options="cashCallIndexOptions"
                    :error="form.errors.cash_call_index"
                />
                <SelectInput
                    v-if="dmaBasedCashCall"
                    v-model="form.cash_call_dma_period"
                    label="DMA Period"
                    name="cash_call_dma_period"
                    :options="dmaPeriodOptions"
                    :error="form.errors.cash_call_dma_period"
                />
                <TextInput
                    v-model="form.cash_return_rate"
                    type="number"
                    label="Cash Return Rate (% p.a.)"
                    name="cash_return_rate"
                    :error="form.errors.cash_return_rate"
                />
                <TextInput
                    v-model="form.start_date"
                    type="date"
                    label="Start Date"
                    name="start_date"
                    :error="form.errors.start_date"
                />
                <div>
                    <label class="block text-sm/6 font-medium text-gray-900">Initial Capital</label>
                    <div class="mt-2 rounded-md bg-white px-3 py-2 text-sm text-gray-900 ring-1 ring-gray-300">
                        &#8377;50,00,000
                    </div>
                </div>
            </div>
        </div>

        <!-- Core Settings -->
        <div class="mt-4 bg-slate-100 p-8">
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
            </div>
        </div>

        <!-- Show More Filters Toggle -->
        <div class="mt-4">
            <Toggle v-model="showMoreFilters" label="Show More Filters" />
        </div>

        <template v-if="showMoreFilters">
            <!-- General Filters -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md" default-open>
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">General Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                        <SelectInput
                            v-model="form.apply_filters_on"
                            label="Apply Filters on"
                            name="apply_filters_on"
                            :options="applyFiltersOnOptions"
                            :error="form.errors.apply_filters_on"
                        />
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
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Moving Average Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <Toggle v-model="form.apply_ma" label="Apply Moving Average Filters" />
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
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Exponential Moving Average Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <Toggle v-model="form.apply_ema" label="Apply EMA Filters" />
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
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Away from High Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        Note: Keep value as 100 if you want to ignore any of the away from high filter
                    </div>
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
                </DisclosurePanel>
            </Disclosure>

            <!-- Positive Days % Filters -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Percentage of Positive Days Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        Note: Keep value as 0 if you want to ignore any of the percentage of positive days filter
                    </div>
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
                </DisclosurePanel>
            </Disclosure>

            <!-- Circuit Filters -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Circuit Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        A stock will be excluded from the ranking if it has exceeded the maximum number of circuits mentioned in the value of the below input.
                    </div>
                    <div class="mb-4 text-sm text-purple-500">
                        Keep value greater than 250 if you want to ignore any of the circuit filters.
                    </div>
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
                </DisclosurePanel>
            </Disclosure>

            <!-- Marketcap and P/E filters hidden from UI but values still submitted with defaults -->

            <!-- Series -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Series</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        <ul>
                            <li>EQ: Delivery-based and intraday trading is allowed</li>
                            <li>BE: Only delivery-based trading is permitted; intraday trading is not allowed.</li>
                        </ul>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <Toggle v-model="form.series_eq" label="EQ" />
                        <Toggle v-model="form.series_be" label="BE" />
                    </div>
                </DisclosurePanel>
            </Disclosure>

            <!-- Ignore Above Beta -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Ignore Above Beta</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        Stocks with beta above this value will be excluded. Keep at 100 to include all stocks.
                    </div>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                        <TextInput
                            v-model="form.ignore_above_beta"
                            type="number"
                            label="Max Beta"
                            name="ignore_above_beta"
                            :error="form.errors.ignore_above_beta"
                        />
                    </div>
                </DisclosurePanel>
            </Disclosure>

            <!-- Price (CMP) Range -->
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Price (CMP) Range</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                </DisclosureButton>
                <DisclosurePanel class="p-4">
                    <div class="mb-4 text-sm text-purple-500">
                        Only those stocks will be included in the ranking whose last closing price is between the range (inclusive).
                    </div>
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
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Multi-Factor Combined Ranking</span>
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
            <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                    <span class="text-sm font-semibold text-gray-900">Custom Filters</span>
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
        </template>

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
import { computed, ref, watch } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import PurpleAlert from '@/Components/Alerts/PurpleAlert.vue';
import type { SelectOption } from '@/types/SelectOption';

const props = defineProps<{
    form: InertiaForm<Record<string, any>>;
    running: boolean;
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

const dmaBasedCashCalls = [
    'full_cash_below_index_dma',
    'only_exits_below_index_dma',
    'allocate_to_gold_below_index_dma',
    'only_exits_allocate_to_gold_below_index_dma',
];

const dmaBasedCashCall = computed(() => dmaBasedCashCalls.includes(props.form.cash_call));

const showMoreFilters = ref(false);

const matchingOption = medianVolumeOneYearOptions.filter(
    (option) => option.id == props.form.median_volume_one_year,
);

const selectedMedianVolumeOption = ref(
    matchingOption.length === 1 ? props.form.median_volume_one_year : 'custom',
);

watch(selectedMedianVolumeOption, (newValue) => {
    if (newValue !== 'custom') {
        props.form.median_volume_one_year = newValue;
    }
});
</script>
