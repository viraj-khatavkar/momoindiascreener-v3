<template>
    <div role="group" aria-label="Chart time period" class="flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1">
        <button
            v-for="range in chartTimeRanges"
            :key="range.key"
            type="button"
            :aria-pressed="selectedTimeRange === range.key"
            :aria-label="`Show ${range.description}`"
            class="min-h-10 cursor-pointer rounded-md px-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:px-3"
            :class="selectedTimeRange === range.key ? 'bg-white text-purple-700 shadow-xs' : 'text-gray-700 hover:bg-gray-50'"
            @click="
                selectedTimeRange = range.key;
                $emit('select');
            "
        >
            {{ range.label }}
        </button>
    </div>
</template>

<script setup lang="ts">
import { chartTimeRanges } from '@/utils/chartTimeRanges';
import type { ChartTimeRangeKey } from '@/utils/chartTimeRanges';

const selectedTimeRange = defineModel<ChartTimeRangeKey>({ required: true });
defineEmits<{ select: [] }>();
</script>
