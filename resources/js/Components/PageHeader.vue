<template>
    <div class="mb-8">
        <h1 class="text-xl font-bold">
            <slot />
        </h1>
        <p v-if="description" class="mt-4 text-sm text-gray-600">{{ description }}</p>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    description?: string;
}>();
</script>
