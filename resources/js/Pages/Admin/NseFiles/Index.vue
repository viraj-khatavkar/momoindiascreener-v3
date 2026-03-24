<template>
    <div>
        <Head title="NSE Files" />
        <div class="flex items-start justify-between">
            <PageHeader description="List of all uploaded NSE files for a specified date.">
                NSE Files
            </PageHeader>
            <Link
                href="/admin/nse-files/create"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                Upload NSE File
            </Link>
        </div>

        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput
                    v-model="form.date"
                    type="date"
                    label="Date"
                    name="date"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Apply Date Filter
            </button>
        </form>

        <div class="mt-8">
            <ul v-if="files.length > 0" class="list-decimal">
                <li v-for="(file, index) in files" :key="index">{{ file }}</li>
            </ul>
            <div v-else>No files uploaded for the date.</div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { SelectOption } from '@/types/SelectOption';

defineProps<{
    files: string[];
    fileNames: SelectOption[];
}>();

const form = useForm({
    date: '',
});

function applyFilter() {
    form.get('/admin/nse-files', {
        preserveState: true,
    });
}
</script>
