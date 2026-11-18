<template>
    <button
        type="button"
        :disabled="disabled || form.processing"
        class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold whitespace-nowrap text-gray-700 shadow-xs ring-1 ring-gray-300 transition-colors ring-inset hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-50 sm:min-h-10"
        @click="duplicate"
    >
        <DocumentDuplicateIcon class="h-4 w-4" aria-hidden="true" />
        {{ form.processing ? 'Copying…' : 'Duplicate Screen' }}
    </button>
</template>

<script setup lang="ts">
import { DocumentDuplicateIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    url: string;
    disabled?: boolean;
}>();

const form = useForm({});

function duplicate(): void {
    if (props.disabled || form.processing) {
        return;
    }

    form.post(props.url, {
        preserveState: false,
    });
}
</script>
