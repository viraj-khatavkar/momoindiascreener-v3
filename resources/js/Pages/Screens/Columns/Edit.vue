<template>
    <div>
        <Head :title="screen.name" />
        <div class="flex items-start justify-between">
            <PageHeader description="Edit your screen's displayable columns">
                {{ screen.name }}
            </PageHeader>
        </div>
        <form @submit.prevent="update">
            <div
                class="mt-4 grid grid-cols-1 gap-x-8 border border-gray-200 px-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="column in columns"
                    :key="column.id"
                    class="relative flex items-start gap-6 py-4"
                >
                    <div class="ml-3 flex h-6 items-center">
                        <input
                            :id="`column-${column.id}`"
                            :name="`column-${column.id}`"
                            type="checkbox"
                            class="h-4 w-4 rounded-xs border-gray-300 text-purple-600 focus:ring-purple-600"
                            :checked="form.columns.includes(column.id)"
                            @change="toggleColumn(column.id, ($event.target as HTMLInputElement).checked)"
                        />
                    </div>
                    <div class="min-w-0 flex-1 text-sm/6">
                        <label
                            :for="`column-${column.id}`"
                            class="cursor-pointer select-none font-medium text-gray-900"
                        >
                            {{ column.name }}
                        </label>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Save
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import type { Screen } from '@/types/app/Models/Screen';
import type { SelectOption } from '@/types/SelectOption';

const props = defineProps<{
    screen: Screen;
    columns: SelectOption[];
}>();

const form = useForm({
    columns: [...props.screen.columns],
});

function toggleColumn(columnId: string, checked: boolean) {
    if (checked) {
        form.columns.push(columnId);
    } else {
        form.columns = form.columns.filter((c: string) => c !== columnId);
    }
}

function update() {
    form.put(`/screens/${props.screen.id}/columns`);
}
</script>
