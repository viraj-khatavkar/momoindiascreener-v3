<template>
    <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
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
        <dd
            class="mt-2 text-2xl font-semibold tracking-tight tabular-nums"
            :class="tone === 'positive' ? 'text-emerald-700' : tone === 'negative' ? 'text-red-700' : 'text-slate-900'"
        >
            {{ value }}
        </dd>
        <dd v-if="detail" class="mt-2 text-sm leading-relaxed text-slate-500">{{ detail }}</dd>
        <dd v-if="expanded && help" :id="helpId" class="mt-3 border-t border-slate-100 pt-3 text-sm leading-relaxed text-slate-600">{{ help }}</dd>
    </div>
</template>

<script setup lang="ts">
import { InformationCircleIcon } from '@heroicons/vue/20/solid';
import { ref, useId } from 'vue';

withDefaults(defineProps<{ label: string; value: string; detail?: string; help?: string; tone?: 'positive' | 'negative' | 'neutral' }>(), {
    tone: 'neutral',
});
const expanded = ref(false);
const helpId = useId();
</script>
