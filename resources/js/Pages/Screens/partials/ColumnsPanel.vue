<template>
    <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-50" @close="$emit('close')">
            <TransitionChild
                as="template"
                enter="ease-in-out duration-300"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in-out duration-300"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-gray-900/50 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-hidden">
                <div class="absolute inset-0 overflow-hidden">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <TransitionChild
                            as="template"
                            enter="transform transition ease-in-out duration-300"
                            enter-from="translate-x-full"
                            enter-to="translate-x-0"
                            leave="transform transition ease-in-out duration-300"
                            leave-from="translate-x-0"
                            leave-to="translate-x-full"
                        >
                            <DialogPanel class="pointer-events-auto w-screen max-w-md">
                                <form class="flex h-full flex-col bg-white shadow-xl" @submit.prevent="apply">
                                    <div class="border-b border-gray-200 px-4 py-4 sm:px-6">
                                        <div class="flex items-start justify-between">
                                            <DialogTitle class="text-base font-semibold text-gray-900"> Edit Columns </DialogTitle>
                                            <button
                                                type="button"
                                                class="-m-2 cursor-pointer p-2 text-gray-400 hover:text-gray-500"
                                                @click="$emit('close')"
                                            >
                                                <span class="sr-only">Close panel</span>
                                                <XMarkIcon class="h-6 w-6" />
                                            </button>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            Choose which columns appear in the results table. The sort factor columns always appear.
                                        </p>
                                    </div>

                                    <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
                                        <div class="flex flex-col gap-6">
                                            <div v-for="group in availableColumns" :key="group.name">
                                                <div class="flex items-center justify-between">
                                                    <h3 class="text-sm font-semibold text-gray-900">
                                                        {{ group.name }}
                                                    </h3>
                                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                                        <button
                                                            type="button"
                                                            class="cursor-pointer text-purple-600 hover:text-purple-500"
                                                            @click="selectGroup(group)"
                                                        >
                                                            All
                                                        </button>
                                                        <span class="text-gray-300">/</span>
                                                        <button
                                                            type="button"
                                                            class="cursor-pointer text-purple-600 hover:text-purple-500"
                                                            @click="clearGroup(group)"
                                                        >
                                                            None
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="mt-2 flex flex-col gap-0.5">
                                                    <label
                                                        v-for="column in group.columns"
                                                        :key="column.id"
                                                        class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-gray-50"
                                                    >
                                                        <input
                                                            v-model="form.columns"
                                                            type="checkbox"
                                                            :value="column.id"
                                                            :name="`column-${column.id}`"
                                                            class="h-4 w-4 rounded-xs border-gray-300 text-purple-600 focus:ring-purple-600"
                                                        />
                                                        <span class="text-sm text-gray-700">{{ column.name }}</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 px-4 py-4 sm:px-6">
                                        <p v-if="form.errors.columns" class="mb-2 text-sm text-red-600">
                                            {{ form.errors.columns }}
                                        </p>
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm text-gray-500">{{ form.columns.length }} selected</p>
                                            <div class="flex gap-3">
                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                                                    @click="$emit('close')"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="submit"
                                                    :disabled="form.processing"
                                                    class="cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
                                                >
                                                    Apply
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup lang="ts">
import { watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import type { Screen } from '@/types/app/Models/Screen';
import type { ScreenColumnGroup } from '@/types/ScreenColumnGroup';

const props = defineProps<{
    open: boolean;
    screen: Screen;
    availableColumns: ScreenColumnGroup[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    columns: [...props.screen.columns],
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.columns = [...props.screen.columns];
            form.clearErrors();
        }
    },
);

function selectGroup(group: ScreenColumnGroup): void {
    const ids = group.columns.map((column) => String(column.id));
    form.columns = [...new Set([...form.columns, ...ids])];
}

function clearGroup(group: ScreenColumnGroup): void {
    const ids = group.columns.map((column) => String(column.id));
    form.columns = form.columns.filter((id) => !ids.includes(id));
}

function apply(): void {
    form.put(`/screens/${props.screen.id}/columns`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>
