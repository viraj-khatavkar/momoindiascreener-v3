<template>
    <p class="text-xs/5 text-gray-500">
        <span class="hidden [@media(pointer:fine)]:inline">Hold Ctrl or ⌘ and scroll to zoom · drag to pan</span>
        <span class="[@media(pointer:fine)]:hidden">Swipe up or down to scroll the page · pinch to zoom</span>
        <span v-if="reset"> · double-click to reset</span>
    </p>
</template>

<script setup lang="ts">
defineProps<{ reset?: boolean }>();
</script>
