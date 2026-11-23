<template>
    <div
        class="meter"
        :class="{ compact }"
        role="progressbar"
        :aria-label="label"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="progress > 0 ? progress : undefined"
        :aria-valuetext="progress === 0 ? 'Waiting to start' : undefined"
    >
        <div class="rail" aria-hidden="true">
            <span class="fill" :class="{ parked: progress === 0 }" :style="{ width: progress + '%' }" />
        </div>
    </div>
</template>

<script setup lang="ts">
defineProps<{ progress: number; label: string; compact?: boolean }>();
</script>

<style scoped>
.meter {
    position: relative;
    height: 16px;
}
.meter::before {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 4px;
    background: repeating-linear-gradient(90deg, #746d84 0 1px, transparent 1px 8px);
}
.rail {
    height: 7px;
    border-radius: 2px;
    background: #494356;
}
.fill {
    display: block;
    position: relative;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #9269e6, #d6beff);
}
.fill::after {
    content: '';
    position: absolute;
    inset: -3px -2px -3px auto;
    width: 4px;
    border-radius: 1px;
    background: #f3eaff;
    box-shadow: 0 0 0 1px #282233;
}
.compact {
    height: 4px;
}
.compact::before {
    content: none;
}
.compact .rail {
    height: 100%;
    border-radius: 9px;
    background: #e9e5f0;
}
.compact .fill {
    background: #8855c9;
}
.compact .fill::after {
    inset: -1px -1px -1px auto;
    width: 2px;
    background: #6538a0;
    box-shadow: none;
}
@media (prefers-reduced-motion: no-preference) {
    .fill {
        transition: width 560ms cubic-bezier(0.2, 0.75, 0.25, 1);
    }
    .parked::after {
        animation: standby 2400ms ease-in-out 2;
    }
}
@keyframes standby {
    50% {
        opacity: 0.4;
    }
}
</style>
