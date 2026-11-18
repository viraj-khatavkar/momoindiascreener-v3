<template>
    <section aria-label="Saved screen rules" class="rounded-xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
            <h2 class="text-sm font-semibold text-gray-900">Saved rules</h2>
            <button
                v-if="!expanded"
                type="button"
                class="min-h-9 shrink-0 rounded text-sm font-semibold text-purple-700 hover:text-purple-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                @click="$emit('showRules')"
            >
                View all rules
            </button>
            <span v-else class="text-xs text-gray-500">Read-only</span>
        </div>
        <div v-if="!expanded" class="space-y-4 p-4 sm:p-5">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium text-gray-500">Universe</dt>
                    <dd class="mt-1 text-sm/6 font-semibold break-words text-gray-900">{{ screenSortByDisplayName(screen.index) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">Ranking</dt>
                    <dd class="mt-1 text-sm/6 font-semibold break-words text-gray-900">{{ ranking }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500">Requested date</dt>
                    <dd class="mt-1 text-sm/6 font-semibold break-words text-gray-900">
                        {{ screen.apply_historical_date ? screen.historical_date : 'Latest available date' }}
                    </dd>
                </div>
            </dl>
            <p class="border-t border-gray-100 pt-3 text-sm text-gray-600">{{ highlights.join(' · ') }}. View all rules for the complete settings.</p>
        </div>
        <div v-else class="grid gap-6 p-4 sm:p-5 lg:grid-cols-2">
            <section v-for="group in groups" :key="group.title" class="min-w-0">
                <h3 class="mb-2 text-sm font-semibold text-purple-900">{{ group.title }}</h3>
                <dl class="divide-y divide-gray-100">
                    <div v-for="rule in group.rules" :key="rule.label" class="grid gap-1 py-2 text-sm sm:grid-cols-2 sm:gap-4">
                        <dt class="text-gray-500">{{ rule.label }}</dt>
                        <dd class="min-w-0 break-words text-gray-900">{{ rule.value }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Screen } from '@/types/app/Models/Screen';
import type { SelectOption } from '@/types/SelectOption';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { screenRuleGroups } from '@/utils/screenRules';

const props = defineProps<{ screen: Screen; applyFiltersOnOptions: SelectOption[]; customFilterValueOptions: SelectOption[]; expanded?: boolean }>();
defineEmits<{ showRules: [] }>();
const groups = computed(() => screenRuleGroups(props.screen, props.applyFiltersOnOptions, props.customFilterValueOptions));
const ranking = computed(() => {
    const screen = props.screen;
    const factors = [screenSortByDisplayName(screen.sort_by)];
    if (screen.apply_factor_two) factors.push(screenSortByDisplayName(screen.factor_two_sort_by));
    if (screen.apply_factor_three) factors.push(screenSortByDisplayName(screen.factor_three_sort_by));
    return factors.length > 1
        ? `${factors.join(' + ')} · Combined rank`
        : `${factors[0]} · ${screen.sort_direction === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest'}`;
});
const highlights = computed(() => {
    const screen = props.screen;
    const labels = [`Median volume ≥ ₹${Number(screen.median_volume_one_year).toLocaleString('en-IN')}`];
    if (Number(screen.minimum_return_one_year) > -100) labels.unshift(`One-year return > ${Number(screen.minimum_return_one_year)}%`);
    if (screen.apply_ma && ([200, 100, 50, 20] as const).some((period) => screen[`above_ma_${period}`] || screen[`below_ma_${period}`])) {
        labels.push('Moving average filters on');
    }
    if (screen.apply_ema && ([200, 100, 50, 20] as const).some((period) => screen[`above_ema_${period}`] || screen[`below_ema_${period}`])) {
        labels.push('EMA filters on');
    }
    if (screen.apply_pe) labels.push('P/E filter on');
    return labels;
});
</script>
