<template>
    <div
        class="rounded-lg border-l-4 p-4 shadow-sm"
        :class="cardClasses"
    >
        <p class="text-xs font-medium uppercase tracking-wide" :class="labelClasses">
            {{ label }}
        </p>
        <p class="mt-2 text-2xl font-bold" :class="valueClasses">
            {{ value }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: string | number;
    variant?: 'green' | 'red' | 'blue' | 'amber';
}>();

const cardClasses = computed(() => {
    const styles: Record<string, string> = {
        green: 'border-green-500 bg-green-50',
        red: 'border-red-500 bg-red-50',
        blue: 'border-blue-500 bg-blue-50',
        amber: 'border-amber-500 bg-amber-50',
    };
    return props.variant
        ? styles[props.variant]
        : 'border-purple-500 bg-gray-50';
});

const labelClasses = computed(() => {
    const styles: Record<string, string> = {
        green: 'text-green-700',
        red: 'text-red-700',
        blue: 'text-blue-700',
        amber: 'text-amber-700',
    };
    return props.variant
        ? styles[props.variant]
        : 'text-gray-500';
});

const valueClasses = computed(() => {
    const styles: Record<string, string> = {
        green: 'text-green-900',
        red: 'text-red-900',
        blue: 'text-blue-900',
        amber: 'text-amber-900',
    };
    return props.variant
        ? styles[props.variant]
        : 'text-gray-900';
});
</script>
