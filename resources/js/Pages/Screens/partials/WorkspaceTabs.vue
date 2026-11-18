<template>
    <div class="mb-5 flex gap-6 border-b border-gray-200" role="tablist" aria-label="Screen workspace">
        <button
            v-for="tab in tabs"
            :id="`screen-tab-${tab.key}`"
            :key="tab.key"
            type="button"
            role="tab"
            :aria-selected="model === tab.key"
            :aria-controls="`screen-panel-${tab.key}`"
            :tabindex="model === tab.key ? 0 : -1"
            class="border-b-2 px-1 py-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            :class="model === tab.key ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-900'"
            @click="model = tab.key"
            @keydown.left.prevent="selectOther"
            @keydown.right.prevent="selectOther"
            @keydown.home.prevent="selectTab('results')"
            @keydown.end.prevent="selectTab('rules')"
        >
            {{ tab.label
            }}<span v-if="tab.key === 'rules' && dirty" class="ml-2 inline-block size-2 rounded-full bg-amber-500" aria-label="Unsaved changes" />
        </button>
    </div>
</template>

<script setup lang="ts">
import { nextTick } from 'vue';
defineProps<{ dirty?: boolean }>();
const model = defineModel<'results' | 'rules'>({ required: true });
const tabs = [
    { key: 'results', label: 'Results' },
    { key: 'rules', label: 'Rules' },
] as const;
function selectTab(tab: 'results' | 'rules'): void {
    model.value = tab;
    nextTick(() => document.getElementById(`screen-tab-${tab}`)?.focus());
}
function selectOther(): void {
    selectTab(model.value === 'results' ? 'rules' : 'results');
}
</script>
