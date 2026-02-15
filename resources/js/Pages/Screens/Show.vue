<template>
    <div>
        <Head :title="screen.name" />
        <div class="flex items-start justify-between">
            <PageHeader description="Public screen (read-only)">
                {{ screen.name }}
            </PageHeader>
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
        </div>

        <InfoAlert class="mt-4">
            To create your own custom screens with editable filters, please create a new
            screen from the Screens page.
        </InfoAlert>

        <Results
            :screen="screen"
            :results="results"
            :columns="columns"
            :show-actions="false"
        />
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
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

const form = {
    index: props.screen.index,
    sort_by: props.screen.sort_by,
    sort_direction: props.screen.sort_direction,
};
</script>
