<template>
    <div>
        <Head title="Create Backtest" />
        <div class="flex items-start justify-between">
            <PageHeader description="Create a new backtest">Create Backtest</PageHeader>
        </div>
        <form @submit.prevent="store">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput
                    v-model="form.name"
                    label="Name"
                    name="name"
                    :error="form.errors.name"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Create Backtest
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';

const form = useForm({
    name: '',
});

function store() {
    form.post('/backtests');
}
</script>
