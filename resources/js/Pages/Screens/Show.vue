<template>
    <div>
        <Head :title="screen.name" />
        <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
            <PageHeader description="Example screen (read-only)">
                {{ screen.name }}
            </PageHeader>
            <div class="flex flex-wrap gap-3">
                <Link
                    v-if="exportUrl"
                    :href="exportUrl"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Historical ZIP
                </Link>
                <DuplicateScreenButton v-if="duplicateUrl" :url="duplicateUrl" />
            </div>
        </div>

        <!-- Core Settings (read-only) -->
        <div class="bg-slate-100 p-8">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                <SelectInput
                    v-model="form.index"
                    label="Index Universe"
                    name="index"
                    :options="indices"
                    disabled
                />
                <SelectInput
                    v-model="form.sort_by"
                    label="Sort By (Factor)"
                    name="sort_by"
                    :options="sortByOptions"
                    disabled
                />
                <SelectInput
                    v-model="form.sort_direction"
                    label="Sort Direction"
                    name="sort_direction"
                    :options="sortDirectionOptions"
                    disabled
                />
            </div>
            <p v-if="screen.index === 'etf' && screen.unique_etf_per_index" class="mt-4 text-sm text-gray-600">
                One ETF per underlying index. Each ETF has the highest median daily volume over one year among the matching results.
            </p>
        </div>

        <InfoAlert class="mt-4">
            <template v-if="duplicateUrl">Use Duplicate Screen to create a private copy with these settings. You can then edit the filters.</template>
            <template v-else>Paid members can duplicate this example screen and edit their private copy.</template>
        </InfoAlert>

        <Results
            :screen="screen"
            :results="results"
            :columns="columns"
            :valuation-filter-warnings="valuationFilterWarnings"
            :show-actions="false"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import Results from '@/Pages/Screens/partials/Results.vue';
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

const sortDirectionOptions = [
    { id: 'desc', name: 'Highest to Lowest' },
    { id: 'asc', name: 'Lowest to Highest' },
];

const form = {
    index: props.screen.index,
    sort_by: props.screen.sort_by,
    sort_direction: props.screen.sort_direction,
};
</script>
