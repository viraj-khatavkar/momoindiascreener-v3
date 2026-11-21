<template>
    <div class="min-w-0 sm:p-5" :class="[headline ? 'p-3' : 'rounded-xl border border-slate-200 p-4', toneClasses[tone]]">
        <dt class="flex items-center gap-2 text-sm font-medium text-slate-600">
            {{ label }}
            <button
                v-if="help"
                type="button"
                :aria-label="`About ${label}`"
                :aria-expanded="expanded"
                :aria-controls="helpId"
                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-purple-600"
                @click="expanded = !expanded"
            >
                <InformationCircleIcon class="h-4 w-4" aria-hidden="true" />
            </button>
        </dt>
        <dd class="mt-2 text-2xl font-semibold tracking-tight wrap-anywhere tabular-nums" :class="headline && 'sm:text-3xl xl:text-4xl'">
            <slot name="value">{{ value }}</slot>
        </dd>
        <dd
            v-if="detail || $slots.default"
            class="mt-2 text-slate-600"
            :class="headline ? 'text-xs leading-5 sm:text-sm' : 'text-sm leading-relaxed'"
        >
            <slot>{{ detail }}</slot>
        </dd>
        <dd v-if="expanded && help" :id="helpId" class="mt-3 border-t border-slate-100 pt-3 text-sm leading-relaxed text-slate-600">{{ help }}</dd>
    </div>
</template>

<script setup lang="ts">
import { InformationCircleIcon } from '@heroicons/vue/20/solid';
import { ref, useId } from 'vue';

const toneClasses = {
    neutral: 'bg-white text-slate-900',
    positive: 'bg-emerald-50 text-emerald-700',
    negative: 'bg-red-50 text-red-700',
    risk: 'bg-amber-50 text-amber-800',
    ratio: 'bg-violet-50 text-violet-700',
};

withDefaults(defineProps<{ label: string; value?: string; detail?: string; help?: string; tone?: keyof typeof toneClasses; headline?: boolean }>(), {
    tone: 'neutral',
});
const expanded = ref(false);
const helpId = useId();
</script>
