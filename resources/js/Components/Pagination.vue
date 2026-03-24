<template>
    <nav v-if="links.length > 3" class="mt-6 flex items-center justify-between">
        <Link
            v-if="prevUrl"
            :href="prevUrl"
            class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50"
            preserve-state
        >
            &larr; Previous
        </Link>
        <span v-else />

        <Link
            v-if="nextUrl"
            :href="nextUrl"
            class="inline-flex items-center gap-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50"
            preserve-state
        >
            Next &rarr;
        </Link>
        <span v-else />
    </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    links: { url: string | null; label: string; active: boolean }[];
}>();

const prevUrl = computed(() => props.links[0]?.url);
const nextUrl = computed(() => props.links[props.links.length - 1]?.url);
</script>
