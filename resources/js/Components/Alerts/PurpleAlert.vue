<template>
    <div class="rounded-md bg-purple-50 p-4">
        <div class="flex">
            <div class="shrink-0">
                <InformationCircleIcon class="size-5 text-purple-400" aria-hidden="true" />
            </div>
            <div class="ml-3">
                <div class="text-sm font-medium text-purple-800">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { InformationCircleIcon } from '@heroicons/vue/20/solid';
</script>
