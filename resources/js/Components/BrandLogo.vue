<template>
    <svg
        viewBox="0 8 468 48"
        width="468"
        height="48"
        role="img"
        aria-label="momoindiascreener"
        class="brand-logo block h-auto shrink-0"
        @pointerdown="tapLogo"
    >
        <use href="/images/momo-logo.svg?v=2#momo-lettering" />
        <g transform="translate(4 17) scale(.44)" fill="#7515d9" :class="{ tapped }">
            <use class="upper-dot" href="/images/momo-logo.svg?v=2#momo-upper-dot" />
            <use class="lower-dot" href="/images/momo-logo.svg?v=2#momo-lower-dot" @animationend="tapped = false" @animationcancel="tapped = false" />
        </g>
    </svg>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const tapped = ref(false);

function tapLogo() {
    tapped.value = window.matchMedia('(prefers-reduced-motion: no-preference)').matches;
}
</script>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .tapped .upper-dot,
    a:focus-visible .upper-dot {
        animation: tap-dot 740ms ease-in-out;
    }
    .tapped .lower-dot,
    a:focus-visible .lower-dot {
        animation: rebound-dot 740ms ease-in-out;
    }
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
    .brand-logo:hover .upper-dot {
        animation: tap-dot 740ms 180ms ease-in-out;
    }
    .brand-logo:hover .lower-dot {
        animation: rebound-dot 740ms 180ms ease-in-out;
    }
}
@keyframes tap-dot {
    0%,
    100% {
        transform: translateY(0);
    }
    22% {
        transform: translateY(-14px);
    }
    36% {
        transform: translateY(8px);
    }
    63% {
        transform: translateY(-4px);
    }
}
@keyframes rebound-dot {
    0%,
    35%,
    100% {
        transform: translateY(0);
    }
    58% {
        transform: translateY(23px);
    }
    77% {
        transform: translateY(-5px);
    }
    91% {
        transform: translateY(1.5px);
    }
}
</style>
