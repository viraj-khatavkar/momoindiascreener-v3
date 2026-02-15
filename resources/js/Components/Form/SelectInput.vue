<template>
    <div>
        <label :for="id" class="block text-sm/6 font-medium text-gray-900">
            {{ label }}
        </label>
        <div class="mt-2">
            <select
                :name="name"
                :id="id"
                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                v-model="model"
                :class="{
                    'text-red-900 outline-red-300 focus:outline-red-600': error,
                }"
            >
                <option v-for="option in options" :key="option.id" :value="option.id">
                    {{ option.name }}
                </option>
            </select>
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600" :id="`${name}-error`">
            {{ error }}
        </p>
    </div>
</template>

<script setup lang="ts">
interface Option {
    id: string | number;
    name: string;
}

interface PropTypes {
    label: string;
    name: string;
    id?: string;
    error?: string;
    options: Option[];
}

withDefaults(defineProps<PropTypes>(), {
    id: `select-input-${crypto.randomUUID()}`,
});

const model = defineModel();
</script>
