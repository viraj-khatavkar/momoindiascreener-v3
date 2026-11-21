<template>
    <Popover v-slot="{ close }" as="div" class="min-w-0">
        <SelectInput v-model="model" :name="name" :label="label" :options="options" :error="error" show-selected-label>
            <template #label-action>
                <PopoverButton
                    type="button"
                    :aria-label="`Search ${label.toLowerCase()}`"
                    class="inline-flex min-h-6 items-center gap-1 rounded px-1 text-sm font-medium text-purple-700 hover:underline focus-visible:outline-purple-600"
                    @click="query = ''"
                    ><MagnifyingGlassIcon aria-hidden="true" class="size-4" />Search</PopoverButton
                >
            </template>
        </SelectInput>
        <PopoverPanel focus class="mt-2 rounded-lg border border-purple-200 bg-white p-2">
            <label :for="searchId" class="sr-only">Search {{ label.toLowerCase() }}</label>
            <input
                :id="searchId"
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Search by name or period"
                class="block h-11 w-full rounded-md border border-gray-300 px-3 text-base focus:outline-purple-600 sm:h-10 sm:text-sm"
                @keydown.enter.prevent="query.trim() && matches.length && choose(matches[0], close)"
                @keydown.down.prevent="list?.querySelector('button')?.focus()"
            />
            <ul v-if="matches.length" ref="list" class="mt-1 max-h-48 overflow-y-auto overscroll-contain">
                <li v-for="option in matches" :key="option.id">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center justify-between gap-2 rounded px-2 py-2 text-left text-sm hover:bg-purple-50 focus-visible:bg-purple-50 focus-visible:outline-purple-600"
                        @click="choose(option, close)"
                    >
                        {{ option.name }}
                        <CheckIcon v-if="model === option.id" aria-label="Selected" class="size-4 shrink-0 text-purple-700" />
                    </button>
                </li>
            </ul>
            <p v-else role="status" class="px-2 py-3 text-sm text-gray-600">No matching factors.</p>
        </PopoverPanel>
    </Popover>
</template>
<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import { CheckIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { SelectOption } from '@/types/SelectOption';

const props = defineProps<{ name: string; label: string; options: SelectOption[]; error?: string }>();
const model = defineModel<string>({ required: true });
const searchId = useId();
const list = ref<HTMLUListElement>();
const query = ref('');
const matches = computed(() => {
    const terms = query.value.toLowerCase().match(/[a-z]+|\d+/g) ?? [];
    if (!terms.length) return props.options;
    const matchingOptions = props.options.filter(({ name }) => {
        const words = name.toLowerCase().match(/[a-z]+|\d+/g) ?? [];
        return terms.every((term) => words.some((word) => (/^\d/.test(term) ? word === term : word.includes(term))));
    });
    return matchingOptions.sort((left, right) => left.name.length - right.name.length);
});

function choose(option: SelectOption, close: () => void): void {
    model.value = String(option.id);
    close();
}
</script>
