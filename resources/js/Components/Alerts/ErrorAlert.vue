<template>
    <div class="rounded-md bg-red-50 p-4">
        <div class="flex">
            <div class="shrink-0">
                <XCircleIcon class="size-5 text-red-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800">
                    <slot />
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { XCircleIcon } from '@heroicons/vue/20/solid';
</script>
