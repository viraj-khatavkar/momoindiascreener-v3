<template>
    <div class="rounded-md bg-green-50 p-4">
        <div class="flex">
            <div class="shrink-0">
                <CheckCircleIcon class="size-5 text-green-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800">
                    <slot />
                </p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { CheckCircleIcon } from '@heroicons/vue/20/solid';
</script>
