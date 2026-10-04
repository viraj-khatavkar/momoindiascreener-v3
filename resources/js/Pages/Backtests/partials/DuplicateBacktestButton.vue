<template>
    <button
        type="button"
        :disabled="disabled || form.processing"
        class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-xs ring-1 ring-inset ring-gray-300 transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
        @click.stop="duplicate"
    >
        <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
        {{ form.processing ? 'Copying…' : 'Duplicate' }}
    </button>
</template>

<script setup lang="ts">
import { DocumentDuplicateIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    backtestId: number;
    disabled?: boolean;
}>();

const form = useForm({});

function duplicate(): void {
    if (props.disabled || form.processing) {
        return;
    }

    form.post(`/backtests/${props.backtestId}/duplicate`, {
        preserveState: false,
    });
}
</script>
