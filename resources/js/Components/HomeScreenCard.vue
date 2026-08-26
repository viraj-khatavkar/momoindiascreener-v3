<template>
    <Link
        :href="screenHref"
        :aria-label="`${editable ? 'Edit' : 'Open'} ${screen.name}`"
        class="group block h-full rounded-2xl focus:outline-hidden focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2"
    >
        <article
            class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition duration-200 group-hover:-translate-y-0.5 group-hover:border-purple-200 group-hover:shadow-md"
        >
            <div class="flex items-start justify-between gap-3">
                <span class="inline-flex rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700">
                    {{ screenSortByDisplayName(screen.index) }}
                </span>
                <span v-if="screen.apply_historical_date" class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                    Historical
                </span>
            </div>

            <h3 class="mt-4 text-lg font-semibold text-gray-950 group-hover:text-purple-700">
                {{ screen.name }}
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                Ranked by {{ screenSortByDisplayName(screen.sort_by) }} ·
                {{ screen.sort_direction === 'asc' ? 'low to high' : 'high to low' }}
            </p>

            <Deferred :data="previewProp">
                <template #fallback>
                    <div class="mt-6 animate-pulse" aria-label="Loading current matches">
                        <div class="h-5 w-32 rounded bg-gray-200" />
                        <div class="mt-4 space-y-2">
                            <div class="h-8 rounded-lg bg-gray-100" />
                            <div class="h-8 rounded-lg bg-gray-100" />
                            <div class="h-8 rounded-lg bg-gray-100" />
                        </div>
                    </div>
                </template>

                <div v-if="preview" class="mt-6" aria-live="polite">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="font-semibold text-gray-900">
                            {{ preview.result_count }}
                            {{ preview.result_count === 1 ? 'match' : 'matches' }}
                        </p>
                        <p v-if="preview.result_date" class="text-xs text-gray-500">
                            {{ formatDate(preview.result_date) }}
                        </p>
                    </div>

                    <ul v-if="preview.top_results.length" class="mt-3 space-y-2">
                        <li
                            v-for="result in preview.top_results"
                            :key="result.symbol"
                            class="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2"
                        >
                            <span class="w-24 shrink-0 text-sm font-semibold text-gray-900">
                                {{ result.symbol }}
                            </span>
                            <span class="truncate text-xs text-gray-500">
                                {{ result.name || 'Name not available' }}
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 rounded-lg bg-gray-50 px-3 py-4 text-sm text-gray-500">No stocks match these rules on this date.</p>
                </div>

                <p v-else class="mt-6 rounded-lg bg-gray-50 px-3 py-4 text-sm text-gray-500">Current results are not available.</p>
            </Deferred>

            <div class="mt-auto flex items-center gap-1 pt-5 text-sm font-semibold text-purple-700">
                {{ editable ? 'Edit screen' : 'Open screen' }}
                <ArrowRightIcon class="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </div>
        </article>
    </Link>
</template>

<script setup lang="ts">
import type { HomeScreen, HomeScreenPreview } from '@/types/HomePage';
import { formatDate } from '@/utils/format';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { ArrowRightIcon } from '@heroicons/vue/20/solid';
import { Deferred, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        screen: HomeScreen;
        preview?: HomeScreenPreview;
        previewProp: string;
        editable?: boolean;
    }>(),
    {
        preview: undefined,
        editable: false,
    },
);

const screenHref = computed(() => (props.editable ? `/screens/${props.screen.id}/edit` : `/screens/${props.screen.id}`));
</script>
