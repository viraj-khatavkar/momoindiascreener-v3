<template>
    <div class="rounded-md bg-yellow-50 p-4">
        <div class="flex">
            <div class="shrink-0">
                <ExclamationTriangleIcon class="size-5 text-yellow-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-yellow-800">
                    <slot />
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid';
</script>
