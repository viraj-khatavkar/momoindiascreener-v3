<template>
    <div>
        <Head title="Create Corporate Action" />
        <PageHeader description="Add a corporate action for a backtest instrument.">
            Create Corporate Action
        </PageHeader>

        <form @submit.prevent="store">
            <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                <TextInput
                    v-model="form.date"
                    type="date"
                    label="Ex-Date"
                    name="date"
                    :error="form.errors.date"
                />
                <TextInput
                    v-model="form.symbol"
                    label="Symbol"
                    name="symbol"
                    placeholder="e.g. TCS"
                    :error="form.errors.symbol"
                />
                <TextInput
                    v-model="form.series"
                    label="Series"
                    name="series"
                    placeholder="e.g. EQ"
                    :error="form.errors.series"
                />
                <SelectInput
                    v-model="form.type"
                    label="Type"
                    name="type"
                    :options="typeOptions"
                    :error="form.errors.type"
                />
                <div class="sm:col-span-2">
                    <TextInput
                        v-model="form.description"
                        label="Description"
                        name="description"
                        placeholder="e.g. Corporate Action: EQ TCS BONUS 1:1"
                        :error="form.errors.description"
                    />
                </div>
                <TextInput
                    v-model="form.ratio"
                    label="Ratio"
                    name="ratio"
                    placeholder="e.g. 1:1"
                    :error="form.errors.ratio"
                />
                <TextInput
                    v-model="form.dividend"
                    label="Dividend (₹ per share)"
                    name="dividend"
                    :error="form.errors.dividend"
                />
                <TextInput
                    v-model="form.dividend_adjustment_factor"
                    label="Dividend Adjustment Factor"
                    name="dividend_adjustment_factor"
                    :error="form.errors.dividend_adjustment_factor"
                />
                <TextInput
                    v-model="form.price_adjustment_factor"
                    label="Price Adjustment Factor"
                    name="price_adjustment_factor"
                    :error="form.errors.price_adjustment_factor"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-6 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Create Corporate Action
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';

const props = defineProps<{
    types: string[];
}>();

const typeOptions = computed(() => [
    { id: '', name: 'None' },
    ...props.types.map((type) => ({
        id: type,
        name: type.charAt(0).toUpperCase() + type.slice(1),
    })),
]);

const form = useForm({
    date: '',
    symbol: '',
    series: 'EQ',
    type: '',
    description: '',
    ratio: '',
    dividend: '',
    dividend_adjustment_factor: '',
    price_adjustment_factor: '',
});

function store() {
    form.post('/admin/corporate-actions');
}
</script>
