<template>
    <div>
        <Head :title="screen.name" />
        <div class="flex items-start justify-between">
            <PageHeader description="Edit your screen">{{ screen.name }}</PageHeader>
        </div>

        <ErrorAlert v-if="Object.keys(form.errors).length > 0" class="mb-4">
            There are some errors in your form. Please fix them.
        </ErrorAlert>

        <form @submit.prevent="update">
            <!-- Core Settings -->
            <div class="bg-slate-100 p-8">
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
                            <TextInput
                                v-model="form.name"
                                label="Name"
                                name="name"
                                :error="form.errors.name"
                            />
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
                                <div class="mt-2 flex gap-x-2">
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

                <!-- Marketcap Range -->
                <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                    <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-900">Marketcap Range (in crores)</span>
                        <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                    </DisclosureButton>
                    <DisclosurePanel class="p-4">
                        <div class="mb-4 text-sm text-purple-500">
                            This will include stocks between the marketcap range. The filter is applied only on the stocks that are present in the selected index filter.
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

                <!-- Price to Earnings Range -->
                <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                    <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-900">Price to Earnings Range</span>
                        <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                    </DisclosureButton>
                    <DisclosurePanel class="p-4">
                        <div class="mb-4 text-sm text-purple-500">
                            Some stocks have undefined P/E data from NSE. If you apply this filter the stocks which don't have P/E data will be excluded from the ranking.
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

                <!-- Historical Ranks -->
                <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                    <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-900">Historical Ranks</span>
                        <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                    </DisclosureButton>
                    <DisclosurePanel class="p-4">
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

                <!-- Custom Filters -->
                <Disclosure v-slot="{ open }" as="div" class="mt-4 border border-gray-200 rounded-md">
                    <DisclosureButton class="flex w-full items-center justify-between bg-gray-50 px-4 py-3">
                        <span class="text-sm font-semibold text-gray-900">Custom Filters</span>
                        <ChevronDownIcon :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']" />
                    </DisclosureButton>
                    <DisclosurePanel class="p-4">
                        <!-- Custom Filter One -->
                        <Toggle v-model="form.apply_custom_filter_one" label="Apply Custom Filter One" />
                        <template v-if="form.apply_custom_filter_one">
                            <hr class="my-4" />
                            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                                <SelectInput
                                    v-model="form.custom_filter_one_value_one"
                                    label="Value One"
                                    name="custom_filter_one_value_one"
                                    :options="customFilterValueOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_one_operator"
                                    label="Operator"
                                    name="custom_filter_one_operator"
                                    :options="customFilterComparatorOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_one_value_two"
                                    label="Value Two"
                                    name="custom_filter_one_value_two"
                                    :options="customFilterValueOptions"
                                />
                            </div>
                        </template>

                        <hr class="my-4" />

                        <!-- Custom Filter Two -->
                        <Toggle v-model="form.apply_custom_filter_two" label="Apply Custom Filter Two" />
                        <template v-if="form.apply_custom_filter_two">
                            <hr class="my-4" />
                            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                                <SelectInput
                                    v-model="form.custom_filter_two_value_one"
                                    label="Value One"
                                    name="custom_filter_two_value_one"
                                    :options="customFilterValueOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_two_operator"
                                    label="Operator"
                                    name="custom_filter_two_operator"
                                    :options="customFilterComparatorOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_two_value_two"
                                    label="Value Two"
                                    name="custom_filter_two_value_two"
                                    :options="customFilterValueOptions"
                                />
                            </div>
                        </template>

                        <hr class="my-4" />

                        <!-- Custom Filter Three -->
                        <Toggle v-model="form.apply_custom_filter_three" label="Apply Custom Filter Three" />
                        <template v-if="form.apply_custom_filter_three">
                            <hr class="my-4" />
                            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                                <SelectInput
                                    v-model="form.custom_filter_three_value_one"
                                    label="Value One"
                                    name="custom_filter_three_value_one"
                                    :options="customFilterValueOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_three_operator"
                                    label="Operator"
                                    name="custom_filter_three_operator"
                                    :options="customFilterComparatorOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_three_value_two"
                                    label="Value Two"
                                    name="custom_filter_three_value_two"
                                    :options="customFilterValueOptions"
                                />
                            </div>
                        </template>

                        <hr class="my-4" />

                        <!-- Custom Filter Four -->
                        <Toggle v-model="form.apply_custom_filter_four" label="Apply Custom Filter Four" />
                        <template v-if="form.apply_custom_filter_four">
                            <hr class="my-4" />
                            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                                <SelectInput
                                    v-model="form.custom_filter_four_value_one"
                                    label="Value One"
                                    name="custom_filter_four_value_one"
                                    :options="customFilterValueOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_four_operator"
                                    label="Operator"
                                    name="custom_filter_four_operator"
                                    :options="customFilterComparatorOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_four_value_two"
                                    label="Value Two"
                                    name="custom_filter_four_value_two"
                                    :options="customFilterValueOptions"
                                />
                            </div>
                        </template>

                        <hr class="my-4" />

                        <!-- Custom Filter Five -->
                        <Toggle v-model="form.apply_custom_filter_five" label="Apply Custom Filter Five" />
                        <template v-if="form.apply_custom_filter_five">
                            <hr class="my-4" />
                            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-3">
                                <SelectInput
                                    v-model="form.custom_filter_five_value_one"
                                    label="Value One"
                                    name="custom_filter_five_value_one"
                                    :options="customFilterValueOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_five_operator"
                                    label="Operator"
                                    name="custom_filter_five_operator"
                                    :options="customFilterComparatorOptions"
                                />
                                <SelectInput
                                    v-model="form.custom_filter_five_value_two"
                                    label="Value Two"
                                    name="custom_filter_five_value_two"
                                    :options="customFilterValueOptions"
                                />
                            </div>
                        </template>
                    </DisclosurePanel>
                </Disclosure>
            </template>

            <!-- Action Buttons -->
            <div class="mt-4 flex gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
                >
                    Update & Apply Filters
                </button>
                <button
                    type="button"
                    :disabled="deleting"
                    class="cursor-pointer rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-75"
                    @click="destroy"
                >
                    Delete
                </button>
            </div>
        </form>

        <ErrorAlert v-if="Object.keys(form.errors).length > 0" class="mt-4">
            There are some errors in your form. Please fix them.
        </ErrorAlert>

        <Results :screen="screen" :results="results" :columns="columns" />
    </div>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import PurpleAlert from '@/Components/Alerts/PurpleAlert.vue';
import Results from '@/Pages/Screens/partials/Results.vue';
import type { Screen } from '@/types/app/Models/Screen';
import type { BacktestNseInstrumentPriceResource } from '@/types/app/Resources/BacktestNseInstrumentPriceResource';
import type { SelectOption } from '@/types/SelectOption';
import type { ScreenResultColumn } from '@/types/ScreenResultColumn';

const props = defineProps<{
    screen: Screen;
    indices: SelectOption[];
    sortByOptions: SelectOption[];
    applyFiltersOnOptions: SelectOption[];
    customFilterValueOptions: SelectOption[];
    customFilterComparatorOptions: SelectOption[];
    results: (BacktestNseInstrumentPriceResource & Record<string, any>)[];
    columns: ScreenResultColumn[];
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

const showMoreFilters = ref(false);
const deleting = ref(false);

const matchingOption = medianVolumeOneYearOptions.filter(
    (option) => option.id == props.screen.median_volume_one_year,
);

const selectedMedianVolumeOption = ref(
    matchingOption.length === 1 ? props.screen.median_volume_one_year : 'custom',
);

const form = useForm({
    name: props.screen.name,
    index: props.screen.index,
    sort_by: props.screen.sort_by,
    sort_direction: props.screen.sort_direction,
    median_volume_one_year: props.screen.median_volume_one_year,
    minimum_return_one_year: props.screen.minimum_return_one_year,
    apply_ma: props.screen.apply_ma,
    above_ma_200: props.screen.above_ma_200,
    above_ma_100: props.screen.above_ma_100,
    above_ma_50: props.screen.above_ma_50,
    above_ma_20: props.screen.above_ma_20,
    below_ma_200: props.screen.below_ma_200,
    below_ma_100: props.screen.below_ma_100,
    below_ma_50: props.screen.below_ma_50,
    below_ma_20: props.screen.below_ma_20,
    away_from_high_one_year: props.screen.away_from_high_one_year,
    away_from_high_all_time: props.screen.away_from_high_all_time,
    positive_days_percent_one_year: props.screen.positive_days_percent_one_year,
    positive_days_percent_nine_months: props.screen.positive_days_percent_nine_months,
    positive_days_percent_six_months: props.screen.positive_days_percent_six_months,
    positive_days_percent_three_months: props.screen.positive_days_percent_three_months,
    positive_days_percent_one_months: props.screen.positive_days_percent_one_months,
    circuits_one_year: props.screen.circuits_one_year,
    circuits_nine_months: props.screen.circuits_nine_months,
    circuits_six_months: props.screen.circuits_six_months,
    circuits_three_months: props.screen.circuits_three_months,
    circuits_one_months: props.screen.circuits_one_months,
    marketcap_from: props.screen.marketcap_from,
    marketcap_to: props.screen.marketcap_to,
    apply_pe: props.screen.apply_pe,
    price_to_earnings_from: props.screen.price_to_earnings_from,
    price_to_earnings_to: props.screen.price_to_earnings_to,
    series_eq: props.screen.series_eq,
    series_be: props.screen.series_be,
    ignore_above_beta: props.screen.ignore_above_beta,
    price_from: props.screen.price_from,
    price_to: props.screen.price_to,
    apply_factor_two: props.screen.apply_factor_two,
    factor_two_sort_by: props.screen.factor_two_sort_by,
    factor_two_sort_direction: props.screen.factor_two_sort_direction,
    apply_factor_three: props.screen.apply_factor_three,
    factor_three_sort_by: props.screen.factor_three_sort_by,
    factor_three_sort_direction: props.screen.factor_three_sort_direction,
    apply_filters_on: props.screen.apply_filters_on,
    apply_historical_date: props.screen.apply_historical_date,
    historical_date: props.screen.historical_date,
    apply_custom_filter_one: props.screen.apply_custom_filter_one,
    custom_filter_one_value_one: props.screen.custom_filter_one_value_one,
    custom_filter_one_operator: props.screen.custom_filter_one_operator,
    custom_filter_one_value_two: props.screen.custom_filter_one_value_two,
    apply_custom_filter_two: props.screen.apply_custom_filter_two,
    custom_filter_two_value_one: props.screen.custom_filter_two_value_one,
    custom_filter_two_operator: props.screen.custom_filter_two_operator,
    custom_filter_two_value_two: props.screen.custom_filter_two_value_two,
    apply_custom_filter_three: props.screen.apply_custom_filter_three,
    custom_filter_three_value_one: props.screen.custom_filter_three_value_one,
    custom_filter_three_operator: props.screen.custom_filter_three_operator,
    custom_filter_three_value_two: props.screen.custom_filter_three_value_two,
    apply_custom_filter_four: props.screen.apply_custom_filter_four,
    custom_filter_four_value_one: props.screen.custom_filter_four_value_one,
    custom_filter_four_operator: props.screen.custom_filter_four_operator,
    custom_filter_four_value_two: props.screen.custom_filter_four_value_two,
    apply_custom_filter_five: props.screen.apply_custom_filter_five,
    custom_filter_five_value_one: props.screen.custom_filter_five_value_one,
    custom_filter_five_operator: props.screen.custom_filter_five_operator,
    custom_filter_five_value_two: props.screen.custom_filter_five_value_two,
});

watch(selectedMedianVolumeOption, (newValue) => {
    if (newValue !== 'custom') {
        form.median_volume_one_year = newValue;
    }
});

function update() {
    if (!form.apply_historical_date) {
        form.historical_date = '2010-01-04';
    }

    form.put(`/screens/${props.screen.id}`, {
        preserveScroll: true,
    });
}

function destroy() {
    if (confirm('Are you sure you want to delete this screen?')) {
        deleting.value = true;
        router.delete(`/screens/${props.screen.id}`);
    }
}
</script>
