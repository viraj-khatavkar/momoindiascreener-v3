<template>
    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6" aria-labelledby="universe-heading">
            <h2 id="universe-heading" class="mb-5 text-lg font-semibold text-gray-900">Name, universe and ranking</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                <TextInput v-model="form.name" label="Name" name="name" :error="form.errors.name" />
                <SelectInput v-model="form.index" label="Index Universe" name="index" :options="indices" :error="form.errors.index" />
            </div>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <FactorPicker v-model="form.sort_by" label="Sort By (Factor)" name="sort_by" :options="factorOptions" :error="form.errors.sort_by" />
                <SelectInput
                    v-model="form.sort_direction"
                    label="Sort Direction"
                    name="sort_direction"
                    :options="sortDirectionOptions"
                    :error="form.errors.sort_direction"
                />
            </div>
            <div v-if="isEtf" class="mt-5 space-y-2">
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
            <RuleSection :group="groups.general" default-open>
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
            </RuleSection>
            <RuleSection :group="groups.ma" :default-open="showErrors || form.apply_ma">
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
            </RuleSection>
            <RuleSection :group="groups.ema" :default-open="showErrors || form.apply_ema">
                <Toggle v-model="form.apply_ema" label="Apply Exponential Moving Average Filters" />
                <template v-if="form.apply_ema">
                    <p class="mt-3 text-sm text-gray-600">Compare the adjusted close with each selected EMA. All selected conditions must pass.</p>
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
            </RuleSection>
            <RuleSection
                :group="groups.highs"
                :default-open="showErrors || Number(form.away_from_high_all_time) < 100 || Number(form.away_from_high_one_year) < 100"
            >
                <div class="mb-4 text-sm/6 text-gray-600">Set a limit to 100 to ignore that filter.</div>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <TextInput
                        v-model="form.away_from_high_all_time"
                        type="number"
                        label="Maximum distance below the all-time high (%)"
                        name="away_from_high_all_time"
                        :error="form.errors.away_from_high_all_time"
                    />
                    <TextInput
                        v-model="form.away_from_high_one_year"
                        type="number"
                        label="Maximum distance below the one-year high (%)"
                        name="away_from_high_one_year"
                        :error="form.errors.away_from_high_one_year"
                    />
                </div>
            </RuleSection>
            <RuleSection
                :group="groups.positiveDays"
                :default-open="showErrors || screenPeriods.some(([key]) => Number(form[`positive_days_percent_${key}`]) > 0)"
            >
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
            </RuleSection>
            <RuleSection
                :group="groups.circuits"
                :default-open="showErrors || screenPeriods.some(([key]) => Number(form[`circuits_${key}`]) !== unlimitedCircuits)"
            >
                <p class="mb-4 text-sm/6 text-gray-600">
                    Enter the maximum number of circuit days for each period. Leave a field blank for no limit.
                </p>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <TextInput
                        v-for="[key, label] in screenPeriods"
                        :key="key"
                        :model-value="Number(form[`circuits_${key}`]) === unlimitedCircuits ? '' : form[`circuits_${key}`]"
                        @update:model-value="(value) => (form[`circuits_${key}`] = value === '' ? unlimitedCircuits : Number(value))"
                        type="number"
                        :label="`Maximum circuits in ${label.toLowerCase()}`"
                        :name="`circuits_${key}`"
                        placeholder="No limit"
                        :error="form.errors[`circuits_${key}`]"
                    />
                </div>
            </RuleSection>
            <RuleSection
                :group="groups.marketcap"
                :default-open="showErrors || Number(form.marketcap_from) > 0 || Number(form.marketcap_to) < 100000000"
            >
                <p v-if="isEtf" class="mb-4 text-sm/6 text-gray-600">Market cap applies to companies. Choose a stock universe to use these limits.</p>
                <p v-else class="mb-4 text-sm/6 text-gray-600">Include stocks whose market cap falls within these limits, in ₹ crore.</p>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <TextInput
                        v-model="form.marketcap_from"
                        :disabled="isEtf"
                        type="number"
                        label="Minimum market cap"
                        name="marketcap_from"
                        :error="form.errors.marketcap_from"
                    />
                    <TextInput
                        v-model="form.marketcap_to"
                        :disabled="isEtf"
                        type="number"
                        label="Maximum market cap"
                        name="marketcap_to"
                        :error="form.errors.marketcap_to"
                    />
                </div>
            </RuleSection>
            <RuleSection :group="groups.pe" :default-open="showErrors || form.apply_pe">
                <p v-if="isEtf" class="mb-4 text-sm/6 text-gray-600">P/E uses company earnings. Choose a stock universe to use this filter.</p>
                <p v-else class="mb-4 text-sm/6 text-gray-600">An active P/E range excludes stocks with missing P/E data.</p>
                <Toggle v-model="form.apply_pe" :disabled="isEtf" label="Apply P/E filter" />
                <template v-if="form.apply_pe">
                    <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <TextInput
                            v-model="form.price_to_earnings_from"
                            :disabled="isEtf"
                            type="number"
                            label="Minimum P/E"
                            name="price_to_earnings_from"
                            :error="form.errors.price_to_earnings_from"
                        />
                        <TextInput
                            v-model="form.price_to_earnings_to"
                            :disabled="isEtf"
                            type="number"
                            label="Maximum P/E"
                            name="price_to_earnings_to"
                            :error="form.errors.price_to_earnings_to"
                        />
                    </div>
                </template>
            </RuleSection>
            <RuleSection :group="groups.series" id="screen-section-series" :default-open="showErrors || !form.series_eq || !form.series_be">
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
            </RuleSection>
            <RuleSection :group="groups.beta" :default-open="showErrors || Number(form.ignore_above_beta) < 100">
                <div class="mb-4 text-sm/6 text-gray-600">Stocks with beta above this value will be excluded. Keep at 100 to include all stocks.</div>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                    <TextInput
                        v-model="form.ignore_above_beta"
                        type="number"
                        label="Max Beta"
                        name="ignore_above_beta"
                        :error="form.errors.ignore_above_beta"
                    />
                </div>
            </RuleSection>
            <RuleSection :group="groups.price" :default-open="showErrors || Number(form.price_from) > 0 || Number(form.price_to) < 10000000">
                <div class="mb-4 text-sm/6 text-gray-600">
                    Only those stocks will be included in the ranking whose last closing price is between the range (inclusive).
                </div>
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <TextInput v-model="form.price_from" type="number" label="Price From" name="price_from" :error="form.errors.price_from" />
                    <TextInput v-model="form.price_to" type="number" label="Price To" name="price_to" :error="form.errors.price_to" />
                </div>
            </RuleSection>
            <RuleSection
                :group="groups.multiFactor"
                id="screen-section-multi-factor"
                :default-open="showErrors || form.apply_factor_two || form.apply_factor_three"
            >
                <p class="mb-5 text-sm text-gray-600">
                    Each enabled factor ranks the matching stocks. The engine adds these ranks; the lowest total comes first. Equal values share the
                    average rank. Equal totals use stock symbols from A to Z.
                </p>

                <Toggle v-model="form.apply_factor_two" label="Apply Factor Two" />
                <template v-if="form.apply_factor_two">
                    <hr class="my-4" />
                    <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                        <FactorPicker
                            v-model="form.factor_two_sort_by"
                            label="Sort By (Factor Two)"
                            name="factor_two_sort_by"
                            :options="factorOptions"
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
                        <FactorPicker
                            v-model="form.factor_three_sort_by"
                            label="Sort By (Factor Three)"
                            name="factor_three_sort_by"
                            :options="factorOptions"
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
            </RuleSection>
            <RuleSection :group="groups.historical" id="screen-section-historical" :default-open="showErrors || form.apply_historical_date">
                <p class="mb-5 text-sm text-gray-600">
                    Use the latest available date, or select a historical date. For a non-trading date, results use the latest available date before
                    it.
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
            </RuleSection>
            <RuleSection
                :group="groups.custom"
                :default-open="
                    showErrors ||
                    form.apply_custom_filter_one ||
                    form.apply_custom_filter_two ||
                    form.apply_custom_filter_three ||
                    form.apply_custom_filter_four ||
                    form.apply_custom_filter_five
                "
            >
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
            </RuleSection>
        </section>
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import RuleSection from '@/Pages/Screens/partials/RuleSection.vue';
import FactorPicker from '@/Pages/Screens/partials/FactorPicker.vue';
import { screenPeriods, screenRuleGroups, unlimitedCircuits } from '@/utils/screenRules';
import { screenFactorLabel } from '@/utils/screenSortByDisplayName';
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
const groups = computed(() => screenRuleGroups(props.form, props.applyFiltersOnOptions, props.customFilterValueOptions));
const factorOptions = computed(() => props.sortByOptions.map((option) => ({ id: option.id, name: screenFactorLabel(String(option.id)) })));
const isEtf = computed(() => props.form.index === 'etf');
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
