<template>
    <div class="receipt w-full max-w-sm rounded-t-md bg-purple-50 px-4 pt-3 pb-4 text-left font-mono text-xs text-gray-800 tabular-nums">
        <div class="flex items-baseline justify-between gap-3 border-b border-dashed border-purple-200 pb-2">
            <strong>{{ item.symbol }}</strong
            ><span>Rank receipt</span>
        </div>
        <dl class="my-2 space-y-1">
            <div v-for="factor in factors" :key="factor.rankKey" class="flex items-baseline justify-between gap-3">
                <dt>{{ screenFactorLabel(factor.key) }}</dt>
                <dd class="shrink-0">{{ multiple ? formatRank(item[factor.rankKey]) : formatScreenCellValue(factor.key, item[factor.key]) }}</dd>
            </div>
            <div v-if="multiple" class="flex justify-between gap-3 border-t border-dashed border-purple-200 pt-1">
                <dt>Rank total</dt>
                <dd>{{ total ?? '—' }}</dd>
            </div>
        </dl>
        <div class="flex items-center justify-between gap-3">
            <strong>Screen rank #{{ rank }} of {{ count }}</strong>
            <span
                aria-hidden="true"
                class="stamp -rotate-6 rounded-xs border-2 border-current px-1 font-sans text-xl/none font-extrabold tracking-tighter text-purple-700"
                >mo:</span
            >
        </div>
        <p class="mt-1 text-gray-600">
            {{
                multiple
                    ? 'Lowest total first. Ties use symbol order.'
                    : factors[0].direction === 'asc'
                      ? 'Lowest value first.'
                      : 'Highest value first.'
            }}
        </p>
    </div>
</template>

<script lang="ts">
export interface RankFactor {
    key: string;
    direction: string;
    rankKey: string;
}
</script>

<script setup lang="ts">
import { computed } from 'vue';
import { screenFactorLabel } from '@/utils/screenSortByDisplayName';
import { formatScreenCellValue } from '@/utils/screenColumnFormat';

const props = defineProps<{ item: Record<string, unknown>; factors: RankFactor[]; rank: number; count: number }>();
const multiple = computed(() => props.factors.length > 1);
const total = computed(() => {
    let sum = 0;
    for (const factor of props.factors) {
        const value = props.item[factor.rankKey];
        if (value == null || !Number.isFinite(Number(value))) return null;
        sum += Number(value);
    }
    return sum;
});

function formatRank(value: unknown): string {
    return value == null ? '—' : `#${value}`;
}
</script>

<style scoped>
.receipt {
    position: relative;
}
.receipt::after {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 3px;
    background: linear-gradient(135deg, transparent 50%, white 50%) left / 8px 6px repeat-x;
}
@media (prefers-reduced-motion: no-preference) {
    .receipt {
        animation: unfold 180ms ease-out;
    }
    .stamp {
        animation: stamp 180ms 100ms ease-out backwards;
    }
}
@keyframes unfold {
    from {
        opacity: 0.4;
        transform: translateY(-5px);
    }
}
@keyframes stamp {
    from {
        opacity: 0;
        scale: 1.25;
    }
}
</style>
