<template>
    <div class="wrap-anywhere tabular-nums" :class="tone">
        <span class="font-semibold">{{ value === null ? '—' : formatCurrency(value) }}</span>
        <span v-if="percentChange !== null" class="block text-xs/5 font-medium tracking-normal">{{
            formatPercent(percentChange / 100, percentChange > 0)
        }}</span>
    </div>
</template>

<script setup lang="ts">
import { formatCurrency, formatPercent } from '@/utils/format';
import { computed } from 'vue';

const props = defineProps<{ value: number | null; percentChange: number | null }>();
const tone = computed(() => {
    if (props.value === null || props.value === 0) return 'text-slate-700';
    return props.value > 0 ? 'text-emerald-700' : 'text-red-700';
});
</script>
