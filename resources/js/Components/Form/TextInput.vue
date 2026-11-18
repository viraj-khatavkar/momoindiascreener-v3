<template>
    <div class="min-w-0">
        <label :for="inputId" class="block text-sm/6 font-medium text-gray-900">
            {{ label }}
        </label>
        <div class="mt-2">
            <input
                :type="type"
                :name="name"
                :id="inputId"
                :disabled="disabled"
                :aria-invalid="error ? true : undefined"
                :aria-describedby="error ? `${inputId}-error` : undefined"
                :step="type === 'number' ? 'any' : undefined"
                class="block h-11 w-full min-w-0 rounded-md bg-white px-3 py-2 text-base/6 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 disabled:outline-gray-200 sm:h-10 sm:text-sm/6"
                :placeholder="placeholder"
                v-model="model"
                :class="{
                    'text-red-900 outline-red-300 placeholder:text-red-400 focus:outline-red-600': error,
                }"
            />
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600" :id="`${inputId}-error`">
            {{ error }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';

interface PropTypes {
    type?: string;
    label: string;
    name: string;
    id?: string;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<PropTypes>(), {
    type: 'text',
});
const generatedId = useId();
const inputId = computed(() => props.id ?? `input-${generatedId}`);

const model = defineModel();
</script>
