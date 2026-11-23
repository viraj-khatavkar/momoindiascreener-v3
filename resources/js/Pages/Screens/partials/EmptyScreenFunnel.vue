<template>
    <button
        type="button"
        aria-label="Shake the empty filter"
        class="relative inline-grid size-12 cursor-pointer place-items-center rounded-lg text-gray-400 hover:text-purple-600 focus-visible:outline-2 focus-visible:outline-purple-600"
        @click="shakes++"
    >
        <span :key="shakes" class="relative" :class="{ shaken: shakes > 0 }" aria-hidden="true">
            <FunnelIcon class="size-10" />
            <span v-if="shakes" class="grain absolute top-1 left-1.5 size-1.5 rounded-full bg-purple-600" />
        </span>
    </button>
</template>

<script setup lang="ts">
import { FunnelIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const shakes = ref(0);
</script>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .shaken svg {
        animation: rattle 400ms 140ms ease-out;
    }
    .grain {
        animation: bounce-off 760ms ease-in both;
    }
}
@keyframes rattle {
    20% {
        transform: rotate(-7deg);
    }
    55% {
        transform: rotate(4deg);
    }
    80% {
        transform: rotate(-2deg);
    }
}
@keyframes bounce-off {
    0% {
        opacity: 0;
        transform: translate(8px, -12px);
    }
    23% {
        opacity: 1;
        transform: translate(5px, 0);
    }
    47% {
        opacity: 1;
        transform: translate(-3px, -6px);
    }
    70% {
        opacity: 1;
        transform: translate(-7px, 0);
    }
    100% {
        opacity: 0;
        transform: translate(-15px, 8px);
    }
}
</style>
