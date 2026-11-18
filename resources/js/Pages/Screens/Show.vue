<template>
    <div>
        <Head :title="screen.name" />
        <Link
            href="/screens"
            class="mb-4 inline-flex min-h-9 items-center rounded text-sm font-medium text-gray-500 hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >← All screens</Link
        >
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <PageHeader class="mb-0! max-w-full min-w-0 [&_h1]:break-words" description="Example screen · Read-only">{{ screen.name }}</PageHeader>
            <div class="flex flex-wrap gap-3">
                <Link
                    v-if="exportUrl"
                    :href="exportUrl"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:min-h-10"
                    >Historical ZIP</Link
                >
                <DuplicateScreenButton v-if="duplicateUrl" :url="duplicateUrl" />
            </div>
        </div>
        <p class="mb-5 text-sm text-gray-600">
            {{
                duplicateUrl
                    ? 'Use Duplicate Screen to create a private copy. You can then edit its rules.'
                    : 'Paid members can duplicate this example screen and edit their private copy.'
            }}
        </p>
        <WorkspaceTabs v-model="activeTab" />
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
                :valuation-filter-warnings="valuationFilterWarnings"
                :show-actions="false"
            />
        </div>
        <div id="screen-panel-rules" v-show="activeTab === 'rules'" role="tabpanel" aria-labelledby="screen-tab-rules">
            <WarningAlert v-for="warning in valuationFilterWarnings" :key="warning" class="mb-4">{{ warning }}</WarningAlert>
            <SavedRules
                :screen="screen"
                :apply-filters-on-options="applyFiltersOnOptions"
                :custom-filter-value-options="customFilterValueOptions"
                expanded
            />
            <p v-if="screen.apply_factor_two || screen.apply_factor_three" class="mt-4 text-sm text-gray-600">
                Combined ranking adds the enabled factor ranks. The lowest total comes first. Equal values share the average rank. Equal totals use
                stock symbols from A to Z.
            </p>
        </div>
    </div>
</template>
<script setup lang="ts">
import { Head, Link, router, useRemember } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import Results from '@/Pages/Screens/partials/Results.vue';
import SavedRules from '@/Pages/Screens/partials/SavedRules.vue';
import WorkspaceTabs from '@/Pages/Screens/partials/WorkspaceTabs.vue';
import DuplicateScreenButton from '@/Pages/Screens/partials/DuplicateScreenButton.vue';
import type { Screen } from '@/types/app/Models/Screen';
import type { BacktestNseInstrumentPriceResource } from '@/types/app/Resources/BacktestNseInstrumentPriceResource';
import type { SelectOption } from '@/types/SelectOption';
import type { ScreenResultColumn } from '@/types/ScreenResultColumn';
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
}>();

const initialTab = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'rules' ? 'rules' : 'results';
const workspace = ref(useRemember({ tab: initialTab as 'results' | 'rules' }, `ExampleScreenTab:${props.screen.id}`));
const activeTab = computed({
    get: () => workspace.value.tab,
    set: (tab: 'results' | 'rules') => {
        workspace.value.tab = tab;
    },
});
watch(activeTab, (tab) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    router.replace({ url: `${url.pathname}${url.search}`, preserveState: true, preserveScroll: true });
});
</script>
