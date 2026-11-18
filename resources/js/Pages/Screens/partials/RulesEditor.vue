<template>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6" aria-labelledby="universe-heading">
            <h2 id="universe-heading" class="mb-5 text-lg font-semibold text-gray-900">Name, universe and ranking</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <TextInput v-model="form.name" label="Name" name="name" :error="form.errors.name" />
                <SelectInput v-model="form.index" label="Index Universe" name="index" :options="indices" :error="form.errors.index" />
            </div>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <SelectInput
                    v-model="form.sort_by"
                    show-selected-label
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
            <div v-if="form.index === 'etf'" class="mt-5 space-y-2">
                <Toggle v-model="form.unique_etf_per_index" label="One ETF per underlying index" />
                <p class="text-sm text-gray-600">
                    After the other filters, keep the ETF with the highest median daily volume over one year for each underlying index.
                </p>
                <p v-if="form.errors.unique_etf_per_index" class="text-sm text-red-600">{{ form.errors.unique_etf_per_index }}</p>
            </div>
        </section>
        <section aria-labelledby="filters-heading">
            <h2 id="filters-heading" class="text-lg font-semibold text-gray-900">Filters</h2>
            <p class="mt-1 mb-5 text-sm text-gray-600">Open a group to review its limits. Settings take effect when you select Save and Apply.</p>
            <Disclosure v-slot="{ open }" as="div" class="mt-3 overflow-hidden rounded-lg border border-gray-200" default-open>
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">General Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="grid gap-6 md:grid-cols-2">
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
                        <TextInput
                            v-model="form.median_volume_one_year"
                            type="number"
                            label="Median Daily Volume One Year (₹)"
                            name="median_volume_one_year"
                            :error="form.errors.median_volume_one_year"
                        />
                        <SelectInput
                            v-model="selectedMedianVolumeOption"
                            label="Volume preset"
                            name="median_volume_option"
                            :options="medianVolumeOneYearOptions"
                        />
                    </div>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || form.apply_ma"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Moving Average Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || form.apply_ema"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Exponential Moving Average Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <Toggle v-model="form.apply_ema" label="Apply Exponential Moving Average Filters" />
                    <template v-if="form.apply_ema">
                        <p class="mt-3 text-sm text-gray-600">
                            Compare the adjusted close with each selected EMA. All selected conditions must pass.
                        </p>
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || Number(form.away_from_high_all_time) < 100 || Number(form.away_from_high_one_year) < 100"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Away from High Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">Set a limit to 100 to ignore that filter.</div>
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="
                    showErrors || Object.entries(form.data()).some(([key, value]) => key.startsWith('positive_days_percent_') && Number(value) > 0)
                "
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Percentage of Positive Days Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">Set a minimum to 0 to ignore that filter.</div>
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || Object.entries(form.data()).some(([key, value]) => key.startsWith('circuits_') && Number(value) < 300)"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Circuit Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">
                        A stock will be excluded from the ranking if it has exceeded the maximum number of circuits mentioned in the value of the
                        below input.
                    </div>
                    <div class="mb-4 text-sm/6 text-gray-600">Keep value greater than 250 if you want to ignore any of the circuit filters.</div>
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || Number(form.marketcap_from) > 0 || Number(form.marketcap_to) < 100000000"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Marketcap Range (in crores)</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">
                        This will include stocks between the marketcap range. The filter is applied only on the stocks that are present in the
                        selected index filter.
                    </div>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput
                            v-model="form.marketcap_from"
                            type="number"
                            label="Marketcap From"
                            name="marketcap_from"
                            :error="form.errors.marketcap_from"
                        />
                        <TextInput
                            v-model="form.marketcap_to"
                            type="number"
                            label="Marketcap To"
                            name="marketcap_to"
                            :error="form.errors.marketcap_to"
                        />
                    </div>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || form.apply_pe"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Price to Earnings Range</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">
                        Some stocks have undefined P/E data from NSE. If you apply this filter the stocks which don't have P/E data will be excluded
                        from the ranking.
                    </div>
                    <Toggle v-model="form.apply_pe" label="Apply Price to Earnings Filter" />
                    <template v-if="form.apply_pe">
                        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                            <TextInput
                                v-model="form.price_to_earnings_from"
                                type="number"
                                label="Price to Earnings From"
                                name="price_to_earnings_from"
                                :error="form.errors.price_to_earnings_from"
                            />
                            <TextInput
                                v-model="form.price_to_earnings_to"
                                type="number"
                                label="Price to Earnings To"
                                name="price_to_earnings_to"
                                :error="form.errors.price_to_earnings_to"
                            />
                        </div>
                    </template>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                id="screen-section-series"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || !form.series_eq || !form.series_be"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Series</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm text-gray-600">
                        <ul>
                            <li>EQ: Delivery-based and intraday trading is allowed</li>
                            <li>BE: Only delivery-based trading is permitted; intraday trading is not allowed.</li>
                        </ul>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Toggle v-model="form.series_eq" label="EQ" />
                        <Toggle v-model="form.series_be" label="BE" />
                    </div>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || Number(form.ignore_above_beta) < 100"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Ignore Above Beta</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || Number(form.price_from) > 0 || Number(form.price_to) < 10000000"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Price (CMP) Range</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <div class="mb-4 text-sm/6 text-gray-600">
                        Only those stocks will be included in the ranking whose last closing price is between the range (inclusive).
                    </div>
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput v-model="form.price_from" type="number" label="Price From" name="price_from" :error="form.errors.price_from" />
                        <TextInput v-model="form.price_to" type="number" label="Price To" name="price_to" :error="form.errors.price_to" />
                    </div>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                id="screen-section-multi-factor"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || form.apply_factor_two || form.apply_factor_three"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Multi-Factor Combined Ranking</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <p class="mb-5 text-sm text-gray-600">
                        Each enabled factor ranks the matching stocks. The engine adds these ranks; the lowest total comes first. Equal values share
                        the average rank. Equal totals use stock symbols from A to Z.
                    </p>

                    <Toggle v-model="form.apply_factor_two" label="Apply Factor Two" />
                    <template v-if="form.apply_factor_two">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                            <SelectInput
                                v-model="form.factor_two_sort_by"
                                show-selected-label
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

                    <Toggle v-model="form.apply_factor_three" label="Apply Factor Three" class="mt-6 border-t border-gray-100 pt-6" />
                    <template v-if="form.apply_factor_three">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                            <SelectInput
                                v-model="form.factor_three_sort_by"
                                show-selected-label
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
            <Disclosure
                v-slot="{ open }"
                as="div"
                id="screen-section-historical"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="showErrors || form.apply_historical_date"
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Historical Ranks</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <p class="mb-5 text-sm text-gray-600">
                        Use the latest available date, or select a historical date. For a non-trading date, results use the latest available date
                        before it.
                    </p>

                    <Toggle v-model="form.apply_historical_date" label="Apply Historical Date" />
                    <template v-if="form.apply_historical_date">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                            <TextInput
                                v-model="form.historical_date"
                                type="date"
                                label="Historical Date"
                                name="historical_date"
                                :error="form.errors.historical_date"
                            />
                        </div>
                    </template>
                </DisclosurePanel>
            </Disclosure>
            <Disclosure
                v-slot="{ open }"
                as="div"
                class="mt-3 overflow-hidden rounded-lg border border-gray-200"
                :default-open="
                    showErrors ||
                    form.apply_custom_filter_one ||
                    form.apply_custom_filter_two ||
                    form.apply_custom_filter_three ||
                    form.apply_custom_filter_four ||
                    form.apply_custom_filter_five
                "
            >
                <DisclosureButton
                    class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
                >
                    <span class="text-sm font-semibold text-gray-900">Custom Filters</span>
                    <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'size-5 shrink-0 text-gray-500 transition-transform']" />
                </DisclosureButton>
                <DisclosurePanel class="bg-white p-4 sm:p-6">
                    <!-- Custom Filter One -->
                    <Toggle v-model="form.apply_custom_filter_one" label="Apply Custom Filter One" />
                    <template v-if="form.apply_custom_filter_one">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-[minmax(0,1fr)_12rem_minmax(0,1fr)]">
                            <SelectInput
                                v-model="form.custom_filter_one_value_one"
                                label="Value One"
                                name="custom_filter_one_value_one"
                                :error="form.errors.custom_filter_one_value_one"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_one_operator"
                                label="Operator"
                                name="custom_filter_one_operator"
                                :error="form.errors.custom_filter_one_operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_one_value_two"
                                label="Value Two"
                                name="custom_filter_one_value_two"
                                :error="form.errors.custom_filter_one_value_two"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>

                    <hr class="my-4" />

                    <!-- Custom Filter Two -->
                    <Toggle v-model="form.apply_custom_filter_two" label="Apply Custom Filter Two" />
                    <template v-if="form.apply_custom_filter_two">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-[minmax(0,1fr)_12rem_minmax(0,1fr)]">
                            <SelectInput
                                v-model="form.custom_filter_two_value_one"
                                label="Value One"
                                name="custom_filter_two_value_one"
                                :error="form.errors.custom_filter_two_value_one"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_two_operator"
                                label="Operator"
                                name="custom_filter_two_operator"
                                :error="form.errors.custom_filter_two_operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_two_value_two"
                                label="Value Two"
                                name="custom_filter_two_value_two"
                                :error="form.errors.custom_filter_two_value_two"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>

                    <hr class="my-4" />

                    <!-- Custom Filter Three -->
                    <Toggle v-model="form.apply_custom_filter_three" label="Apply Custom Filter Three" />
                    <template v-if="form.apply_custom_filter_three">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-[minmax(0,1fr)_12rem_minmax(0,1fr)]">
                            <SelectInput
                                v-model="form.custom_filter_three_value_one"
                                label="Value One"
                                name="custom_filter_three_value_one"
                                :error="form.errors.custom_filter_three_value_one"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_three_operator"
                                label="Operator"
                                name="custom_filter_three_operator"
                                :error="form.errors.custom_filter_three_operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_three_value_two"
                                label="Value Two"
                                name="custom_filter_three_value_two"
                                :error="form.errors.custom_filter_three_value_two"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>

                    <hr class="my-4" />

                    <!-- Custom Filter Four -->
                    <Toggle v-model="form.apply_custom_filter_four" label="Apply Custom Filter Four" />
                    <template v-if="form.apply_custom_filter_four">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-[minmax(0,1fr)_12rem_minmax(0,1fr)]">
                            <SelectInput
                                v-model="form.custom_filter_four_value_one"
                                label="Value One"
                                name="custom_filter_four_value_one"
                                :error="form.errors.custom_filter_four_value_one"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_four_operator"
                                label="Operator"
                                name="custom_filter_four_operator"
                                :error="form.errors.custom_filter_four_operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_four_value_two"
                                label="Value Two"
                                name="custom_filter_four_value_two"
                                :error="form.errors.custom_filter_four_value_two"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>

                    <hr class="my-4" />

                    <!-- Custom Filter Five -->
                    <Toggle v-model="form.apply_custom_filter_five" label="Apply Custom Filter Five" />
                    <template v-if="form.apply_custom_filter_five">
                        <hr class="my-4" />
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-[minmax(0,1fr)_12rem_minmax(0,1fr)]">
                            <SelectInput
                                v-model="form.custom_filter_five_value_one"
                                label="Value One"
                                name="custom_filter_five_value_one"
                                :error="form.errors.custom_filter_five_value_one"
                                :options="customFilterValueOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_five_operator"
                                label="Operator"
                                name="custom_filter_five_operator"
                                :error="form.errors.custom_filter_five_operator"
                                :options="customFilterComparatorOptions"
                            />
                            <SelectInput
                                v-model="form.custom_filter_five_value_two"
                                label="Value Two"
                                name="custom_filter_five_value_two"
                                :error="form.errors.custom_filter_five_value_two"
                                :options="customFilterValueOptions"
                            />
                        </div>
                    </template>
                </DisclosurePanel>
            </Disclosure>
        </section>
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import type { InertiaForm } from '@inertiajs/vue3';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import type { SelectOption } from '@/types/SelectOption';
import type { ScreenSettings } from '@/types/ScreenSettings';
const props = defineProps<{
    form: InertiaForm<ScreenSettings>;
    showErrors?: boolean;
    indices: SelectOption[];
    sortByOptions: SelectOption[];
    applyFiltersOnOptions: SelectOption[];
    customFilterValueOptions: SelectOption[];
    customFilterComparatorOptions: SelectOption[];
}>();
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
const customVolume = ref(false);
const selectedMedianVolumeOption = computed({
    get: () =>
        customVolume.value
            ? 'custom'
            : (medianVolumeOneYearOptions.find((option) => option.id === String(props.form.median_volume_one_year))?.id ?? 'custom'),
    set: (value: string) => {
        customVolume.value = value === 'custom';
        if (value !== 'custom') props.form.median_volume_one_year = Number(value);
    },
});
</script>
