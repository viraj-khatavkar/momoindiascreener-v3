<template>
    <button
        type="button"
        class="group/th flex w-full cursor-pointer items-center gap-1"
        :class="align === 'right' ? 'justify-end' : 'justify-start'"
        @click="$emit('toggle')"
    >
        <span><slot /></span>
        <ChevronDownIcon v-if="direction === 'desc'" class="h-4 w-4 shrink-0 text-purple-600" />
        <ChevronUpIcon v-else-if="direction === 'asc'" class="h-4 w-4 shrink-0 text-purple-600" />
        <ChevronUpDownIcon v-else class="h-4 w-4 shrink-0 text-gray-400 opacity-0 group-hover/th:opacity-100" />
    </button>
</template>

<script setup lang="ts">
import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon } from '@heroicons/vue/20/solid';

withDefaults(
    defineProps<{
        direction: 'asc' | 'desc' | null;
        align?: 'left' | 'right';
    }>(),
    {
        align: 'left',
    },
);

defineEmits<{
    toggle: [];
}>();
</script>
