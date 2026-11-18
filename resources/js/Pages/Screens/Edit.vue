<template>
    <div class="pb-4">
        <Head :title="screen.name" />
        <Link
            href="/screens"
            class="mb-4 inline-flex min-h-9 items-center rounded text-sm font-medium text-gray-500 hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >← All screens</Link
        >
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <PageHeader class="mb-0! max-w-full min-w-0 [&_h1]:break-words" description="Your saved screen">{{ screen.name }}</PageHeader>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="exportUrl && !form.isDirty && !form.processing && !deleting"
                    :href="exportUrl"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:min-h-10"
                    >Historical ZIP</Link
                >
                <DuplicateScreenButton v-if="duplicateUrl" :url="duplicateUrl" :disabled="form.isDirty || form.processing || deleting" />
                <Menu as="div" class="relative">
                    <MenuButton
                        :disabled="form.processing || deleting"
                        class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:min-h-10"
                        >More</MenuButton
                    >
                    <MenuItems class="absolute right-0 z-30 mt-2 w-48 rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-200 focus:outline-none">
                        <MenuItem v-slot="{ active }"
                            ><button
                                type="button"
                                class="w-full rounded-md px-3 py-2 text-left text-sm text-red-700"
                                :class="{ 'bg-red-50': active }"
                                @click="destroy"
                            >
                                Delete screen
                            </button></MenuItem
                        >
                    </MenuItems>
                </Menu>
            </div>
        </div>
        <div v-if="form.isDirty" role="status" class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Changes not applied</p>
            <p class="mt-1">Results use the saved rules. Select Save and Apply to update them, or discard your changes.</p>
            <p v-if="duplicateUrl" class="mt-1">Save changes before you duplicate this screen.</p>
        </div>
        <p v-else-if="saved" role="status" class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">Changes saved. Results updated.</p>
        <WorkspaceTabs v-model="activeTab" :dirty="form.isDirty" />
        <div id="screen-panel-results" v-show="activeTab === 'results'" role="tabpanel" aria-labelledby="screen-tab-results">
            <SavedRules
                :screen="screen"
                :apply-filters-on-options="applyFiltersOnOptions"
                :custom-filter-value-options="customFilterValueOptions"
                @show-rules="activeTab = 'rules'"
            />
            <Results
                :screen="screen"
                :results="results"
                :columns="columns"
                :available-columns="availableColumns"
                :valuation-filter-warnings="valuationFilterWarnings"
                :loading="form.processing"
                :actions-disabled="form.isDirty || form.processing || deleting"
            />
        </div>
        <div id="screen-panel-rules" v-show="activeTab === 'rules'" role="tabpanel" aria-labelledby="screen-tab-rules">
            <div
                v-if="form.hasErrors"
                ref="errorSummary"
                role="alert"
                tabindex="-1"
                class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                <p class="font-semibold">Check these fields before you save:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                </ul>
            </div>
            <WarningAlert v-for="warning in valuationFilterWarnings" :key="warning" class="mb-4"
                >{{ warning }} These warnings refer to the saved results.</WarningAlert
            >
            <form id="screen-rules-form" @submit.prevent="update">
                <fieldset :disabled="form.processing || deleting" class="min-w-0">
                    <RulesEditor
                        :key="editorRevision"
                        :form="form"
                        :indices="indices"
                        :sort-by-options="sortByOptions"
                        :apply-filters-on-options="applyFiltersOnOptions"
                        :custom-filter-value-options="customFilterValueOptions"
                        :custom-filter-comparator-options="customFilterComparatorOptions"
                        :show-errors="form.hasErrors"
                    />
                </fieldset>
            </form>
        </div>
        <div
            v-if="activeTab === 'rules' || form.isDirty"
            class="sticky bottom-0 z-20 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-lg sm:px-5"
        >
            <p class="text-sm text-gray-600" role="status">
                {{ form.processing ? 'Saving and updating results…' : form.isDirty ? 'You have unsaved changes.' : 'All changes saved.' }}
            </p>
            <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:gap-3">
                <button
                    type="button"
                    :disabled="!form.isDirty || form.processing || deleting"
                    class="min-h-11 rounded-lg border border-gray-300 px-2 py-2 text-sm font-semibold whitespace-nowrap text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-40 sm:min-h-10 sm:px-4"
                    @click="discard"
                >
                    Discard changes
                </button>
                <button
                    type="submit"
                    form="screen-rules-form"
                    :disabled="form.processing || deleting"
                    class="min-h-11 rounded-lg bg-purple-600 px-2 py-2 text-sm font-semibold whitespace-nowrap text-white hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-10 sm:px-4"
                >
                    {{ form.processing ? 'Applying…' : 'Save and Apply' }}
                </button>
            </div>
        </div>
    </div>
</template>
<script setup lang="ts">
import { Head, Link, router, useForm, useRemember } from '@inertiajs/vue3';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import Results from '@/Pages/Screens/partials/Results.vue';
import RulesEditor from '@/Pages/Screens/partials/RulesEditor.vue';
import SavedRules from '@/Pages/Screens/partials/SavedRules.vue';
import WorkspaceTabs from '@/Pages/Screens/partials/WorkspaceTabs.vue';
import DuplicateScreenButton from '@/Pages/Screens/partials/DuplicateScreenButton.vue';
import type { Screen } from '@/types/app/Models/Screen';
import type { BacktestNseInstrumentPriceResource } from '@/types/app/Resources/BacktestNseInstrumentPriceResource';
import type { SelectOption } from '@/types/SelectOption';
import type { ScreenColumnGroup } from '@/types/ScreenColumnGroup';
import type { ScreenResultColumn } from '@/types/ScreenResultColumn';
import type { ScreenSettings } from '@/types/ScreenSettings';
const props = defineProps<{
    screen: Screen;
    duplicateUrl: string | null;
    exportUrl: string | null;
    indices: SelectOption[];
    sortByOptions: SelectOption[];
    applyFiltersOnOptions: SelectOption[];
    customFilterValueOptions: SelectOption[];
    customFilterComparatorOptions: SelectOption[];
    results: (BacktestNseInstrumentPriceResource & Record<string, any>)[];
    valuationFilterWarnings: string[];
    columns: ScreenResultColumn[];
    availableColumns: ScreenColumnGroup[];
}>();

function savedSettings(): ScreenSettings {
    return {
        name: props.screen.name,
        index: props.screen.index,
        unique_etf_per_index: props.screen.unique_etf_per_index,
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
        apply_ema: props.screen.apply_ema,
        above_ema_200: props.screen.above_ema_200,
        above_ema_100: props.screen.above_ema_100,
        above_ema_50: props.screen.above_ema_50,
        above_ema_20: props.screen.above_ema_20,
        below_ema_200: props.screen.below_ema_200,
        below_ema_100: props.screen.below_ema_100,
        below_ema_50: props.screen.below_ema_50,
        below_ema_20: props.screen.below_ema_20,
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
    };
}
const form = useForm(`Screen:${props.screen.id}`, savedSettings());
const initialTab = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'rules' ? 'rules' : 'results';
const workspace = ref(useRemember({ tab: initialTab as 'results' | 'rules' }, `ScreenTab:${props.screen.id}`));
const activeTab = computed({
    get: () => workspace.value.tab,
    set: (tab: 'results' | 'rules') => {
        workspace.value.tab = tab;
    },
});
const deleting = ref(false);
const saved = ref(false);
const editorRevision = ref(0);
const errorSummary = ref<HTMLElement | null>(null);
watch(activeTab, (tab) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    router.replace({ url: `${url.pathname}${url.search}`, preserveState: true, preserveScroll: true });
});
watch(
    () => form.isDirty,
    (dirty) => {
        if (dirty) saved.value = false;
    },
);

function discard(): void {
    form.defaults(savedSettings());
    form.reset();
    form.clearErrors();
    editorRevision.value++;
}
function update(): void {
    if (form.processing || deleting.value) return;
    if (!form.apply_historical_date) form.historical_date = '2010-01-04';
    form.put(`/screens/${props.screen.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults(savedSettings());
            form.reset();
            editorRevision.value++;
            activeTab.value = 'results';
            saved.value = true;
            nextTick(() => document.getElementById('screen-tab-results')?.focus());
        },
        onError: () => {
            activeTab.value = 'rules';
            editorRevision.value++;
            nextTick(() => errorSummary.value?.focus());
        },
    });
}
function destroy(): void {
    if (form.processing || deleting.value || !confirm('Delete this screen? This cannot be undone.')) return;
    deleting.value = true;
    router.delete(`/screens/${props.screen.id}`, {
        onFinish: () => {
            deleting.value = false;
        },
    });
}
function warnBeforeUnload(event: BeforeUnloadEvent): void {
    if (!form.isDirty || deleting.value) return;
    event.preventDefault();
    event.returnValue = '';
}
window.addEventListener('beforeunload', warnBeforeUnload);
const removeNavigationGuard = router.on('before', (event) => {
    if (
        event.detail.visit.method === 'get' &&
        form.isDirty &&
        !form.processing &&
        !deleting.value &&
        !window.confirm('Leave this page without applying your changes?')
    ) {
        event.preventDefault();
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload);
    removeNavigationGuard();
});
</script>
