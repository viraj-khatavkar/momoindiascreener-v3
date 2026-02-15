<template>
    <Head title="Inspirational Quote" />
    <div class="bg-white py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl lg:mx-0">
                <h2 class="text-5xl leading-16 font-semibold text-gray-900 sm:text-7xl lg:leading-20">
                    {{ message }}
                </h2>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    message: string;
}>();
</script>
