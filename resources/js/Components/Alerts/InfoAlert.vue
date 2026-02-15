<template>
    <div class="rounded-md bg-blue-50 p-4">
        <div class="flex">
            <div class="shrink-0">
                <ExclamationCircleIcon class="size-5 text-blue-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-blue-800">
                    <slot />
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid';
</script>
