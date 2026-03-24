<template>
    <div>
        <Head title="Upload NSE File" />
        <PageHeader description="Upload a NSE file for a specific date.">
            Upload NSE File
        </PageHeader>

        <form @submit.prevent="store">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2">
                <SelectInput
                    v-model="form.filename"
                    label="Filename"
                    name="filename"
                    :options="fileNames"
                    :error="form.errors.filename"
                />
                <TextInput
                    v-model="form.date"
                    type="date"
                    label="Date"
                    name="date"
                    :error="form.errors.date"
                />
                <div class="mt-6">
                    <label for="file" class="block text-sm/6 font-medium text-gray-900">
                        File
                    </label>
                    <div class="mt-2">
                        <input
                            type="file"
                            id="file"
                            name="file"
                            class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                            @change="onFileChange"
                        />
                    </div>
                    <p
                        v-if="form.errors.file"
                        class="mt-2 text-sm text-red-600"
                    >
                        {{ form.errors.file }}
                    </p>
                    <progress
                        v-if="form.progress"
                        :value="form.progress.percentage"
                        max="100"
                        class="mt-2 w-full"
                    >
                        {{ form.progress.percentage }}%
                    </progress>
                </div>
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Upload File
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { SelectOption } from '@/types/SelectOption';

defineProps<{
    fileNames: SelectOption[];
}>();

const today = new Date().toISOString().split('T')[0];

const form = useForm({
    file: null as File | null,
    date: today,
    filename: 'bhavcopy',
});

function onFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function store() {
    form.post('/admin/nse-files');
}
</script>
