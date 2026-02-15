<template>
    <div class="relative grid flex-1 grid-cols-1">
        <Combobox :model-value="null" nullable @update:model-value="onSelect">
            <div class="col-start-1 row-start-1 flex items-center">
                <MagnifyingGlassIcon
                    class="pointer-events-none size-5 text-gray-400"
                    aria-hidden="true"
                />
            </div>
            <ComboboxInput
                ref="inputRef"
                class="col-start-1 row-start-1 block size-full bg-white pl-8 text-base text-gray-900 outline-none placeholder:text-gray-400 sm:text-sm/6"
                placeholder="Search instruments..."
                :display-value="() => ''"
                @change="onInputChange"
            />
            <ComboboxOptions
                v-if="query.length > 0"
                class="absolute left-0 top-full z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm"
            >
                <div
                    v-if="loading"
                    class="px-4 py-2 text-sm text-gray-500"
                >
                    Searching...
                </div>
                <div
                    v-else-if="results.length === 0"
                    class="px-4 py-2 text-sm text-gray-500"
                >
                    No instruments found.
                </div>
                <ComboboxOption
                    v-for="instrument in results"
                    :key="instrument.symbol"
                    :value="instrument"
                    v-slot="{ active }"
                    class="cursor-pointer"
                >
                    <div
                        :class="[
                            active ? 'bg-purple-600 text-white' : 'text-gray-900',
                            'px-4 py-2',
                        ]"
                    >
                        <span class="font-medium">{{ instrument.symbol }}</span>
                        <span :class="active ? 'text-purple-200' : 'text-gray-500'">
                            &mdash; {{ instrument.name }}
                        </span>
                    </div>
                </ComboboxOption>
            </ComboboxOptions>
        </Combobox>
    </div>
</template>

<script setup lang="ts">
import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, onUnmounted, ref } from 'vue';

interface Instrument {
    symbol: string;
    name: string;
}

const inputRef = ref<{ el: HTMLInputElement } | null>(null);
const query = ref('');
const results = ref<Instrument[]>([]);
const loading = ref(false);

function onKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault();
        inputRef.value?.el?.focus();
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function onInputChange(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    query.value = value;

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    if (value.length === 0) {
        results.value = [];
        loading.value = false;
        return;
    }

    loading.value = true;

    debounceTimer = setTimeout(async () => {
        try {
            const response = await axios.get('/instruments/search', {
                params: { q: value },
            });
            results.value = response.data;
        } catch {
            results.value = [];
        } finally {
            loading.value = false;
        }
    }, 250);
}

function onSelect(instrument: Instrument | null) {
    if (instrument) {
        query.value = '';
        results.value = [];
        router.visit(`/instruments/${instrument.symbol}`);
    }
}
</script>
