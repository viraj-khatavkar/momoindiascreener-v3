<template>
    <div>
        <label :for="id" class="block text-sm/6 font-medium text-gray-900">
            {{ label }}
        </label>
        <div class="mt-2">
            <input
                :type="type"
                :name="name"
                :id="id"
                :step="type === 'number' ? 'any' : undefined"
                class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6 disabled:bg-purple-100 disabled:outline-purple-200"
                :placeholder="placeholder"
                v-model="model"
                :class="{
                    'text-red-900 outline-red-300 placeholder:text-red-400 focus:outline-red-600': error,
                }"
            />
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600" :id="`${name}-error`">
            {{ error }}
        </p>
    </div>
</template>

<script setup lang="ts">
interface PropTypes {
    type?: string;
    label: string;
    name: string;
    id?: string;
    placeholder?: string;
    error?: string;
}

withDefaults(defineProps<PropTypes>(), {
    type: 'text',
    id: `text-input-${crypto.randomUUID()}`,
});

const model = defineModel();
</script>
