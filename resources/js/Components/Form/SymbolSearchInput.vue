<template>
    <div>
        <Combobox :model-value="model" nullable @update:model-value="onSelect">
            <ComboboxLabel class="block text-sm/6 font-medium text-gray-900">
                {{ label }}
            </ComboboxLabel>
            <div class="relative mt-2">
                <ComboboxInput
                    :name="name"
                    :aria-invalid="error ? true : undefined"
                    :aria-describedby="error ? errorId : undefined"
                    class="block h-11 w-full rounded-md bg-white px-3 py-2 text-base/6 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:h-10 sm:text-sm/6"
                    :placeholder="placeholder"
                    :display-value="() => model ?? ''"
                    autocomplete="off"
                    @change="onInputChange"
                />
                <ComboboxOptions
                    v-if="query.length > 0"
                    class="absolute top-full left-0 z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm"
                >
                    <div v-if="loading" class="px-4 py-2 text-sm text-gray-500">Searching...</div>
                    <div v-else-if="results.length === 0" class="px-4 py-2 text-sm text-gray-500">No symbols found.</div>
                    <ComboboxOption v-for="symbol in results" :key="symbol" v-slot="{ active }" :value="symbol" class="cursor-pointer">
                        <div :class="[active ? 'bg-purple-600 text-white' : 'text-gray-900', 'px-4 py-2 font-medium']">
                            {{ symbol }}
                        </div>
                    </ComboboxOption>
                </ComboboxOptions>
            </div>
        </Combobox>
        <p v-if="error" :id="errorId" role="alert" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>

<script setup lang="ts">
import { Combobox, ComboboxInput, ComboboxLabel, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import axios from 'axios';
import { ref, useId, watch } from 'vue';

const props = defineProps<{
    label: string;
    name: string;
    placeholder?: string;
    searchUrl: string;
    error?: string;
}>();

const model = defineModel<string>({ default: '' });
const errorId = useId();

const query = ref('');
const results = ref<string[]>([]);
const loading = ref(false);

function onInputChange(event: Event) {
    const value = (event.target as HTMLInputElement).value;

    query.value = value;
    model.value = value;
}

watch(query, (value, _, onCleanup) => {
    results.value = [];
    loading.value = value.length > 0;
    if (!value) return;

    const controller = new AbortController();
    const timer = setTimeout(async () => {
        try {
            const response = await axios.get<string[]>(props.searchUrl, { params: { q: value }, signal: controller.signal });
            results.value = response.data;
        } catch {
            if (!controller.signal.aborted) results.value = [];
        } finally {
            if (!controller.signal.aborted) loading.value = false;
        }
    }, 250);
    onCleanup(() => {
        clearTimeout(timer);
        controller.abort();
    });
});

function onSelect(symbol: string | null) {
    if (symbol) {
        model.value = symbol;
        query.value = '';
    }
}
</script>
