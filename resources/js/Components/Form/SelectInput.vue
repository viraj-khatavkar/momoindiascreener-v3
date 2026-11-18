<template>
    <div class="min-w-0">
        <label :for="inputId" class="block text-sm/6 font-medium text-gray-900">
            {{ label }}
        </label>
        <div class="relative mt-2">
            <select
                ref="selectElement"
                :name="name"
                :id="inputId"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="
                    [error ? `${inputId}-error` : '', showLabelHint ? `${inputId}-selection` : ''].filter(Boolean).join(' ') || undefined
                "
                :title="selectedLabel"
                class="block h-11 w-full min-w-0 appearance-none rounded-md bg-white py-2 pr-10 pl-3 text-base/6 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 disabled:outline-gray-200 sm:h-10 sm:text-sm/6"
                v-model="model"
                :class="{
                    'text-red-900 outline-red-300 focus:outline-red-600': error,
                }"
            >
                <option v-for="option in options" :key="option.id" :value="option.id">
                    {{ option.name }}
                </option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute top-3.5 right-3 size-4 text-gray-500 sm:top-3" aria-hidden="true" />
            <span
                v-if="showSelectedLabel"
                ref="labelMeasure"
                class="pointer-events-none invisible absolute max-w-full overflow-hidden text-base/6 whitespace-nowrap sm:text-sm/6"
                aria-hidden="true"
                >{{ selectedLabel }}</span
            >
            <p v-if="showLabelHint" :id="`${inputId}-selection`" class="mt-1.5 text-xs/5 break-words text-gray-500">{{ selectedLabel }}</p>
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600" :id="`${inputId}-error`">
            {{ error }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

interface Option {
    id: string | number;
    name: string;
}

interface PropTypes {
    label: string;
    name: string;
    id?: string;
    error?: string;
    disabled?: boolean;
    showSelectedLabel?: boolean;
    options: Option[];
}

const props = defineProps<PropTypes>();
const generatedId = useId();
const inputId = computed(() => props.id ?? `select-${generatedId}`);

const model = defineModel();
const selectedLabel = computed(() => props.options.find((option) => option.id === model.value)?.name ?? '');
const selectElement = ref<HTMLSelectElement | null>(null);
const labelMeasure = ref<HTMLElement | null>(null);
const showLabelHint = ref(false);
let resizeObserver: ResizeObserver | undefined;

function updateLabelHint(): void {
    const width = selectElement.value?.clientWidth ?? 0;
    showLabelHint.value = Boolean(props.showSelectedLabel && width > 0 && (labelMeasure.value?.scrollWidth ?? 0) > width - 52);
}

onMounted(() => {
    if (!props.showSelectedLabel) return;
    resizeObserver = new ResizeObserver(updateLabelHint);
    if (selectElement.value) resizeObserver.observe(selectElement.value);
    updateLabelHint();
});
watch(selectedLabel, () => nextTick(updateLabelHint));
onBeforeUnmount(() => resizeObserver?.disconnect());
</script>
