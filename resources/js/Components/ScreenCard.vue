<template>
    <article
        class="group flex h-full min-w-0 flex-col overflow-hidden rounded-xl bg-linear-to-br from-purple-50 to-white shadow-xs ring-1 ring-purple-100 transition hover:shadow-md hover:ring-purple-200"
    >
        <Link
            :href="editable ? `/screens/${screen.id}/edit` : `/screens/${screen.id}`"
            :aria-label="`${editable && !showActions ? 'Edit' : 'Open'} ${screen.name}`"
            class="block flex-1 p-5 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-purple-600"
        >
            <h3 class="mb-4 text-lg font-semibold break-words text-purple-900 group-hover:text-purple-600">
                {{ screen.name }}
            </h3>
            <dl class="space-y-3">
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-sm text-gray-500">Index</dt>
                    <dd class="min-w-0 text-right text-sm font-medium break-words text-gray-900">{{ screenSortByDisplayName(screen.index) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-sm text-gray-500">Ranking Factor</dt>
                    <dd class="min-w-0 text-right text-sm font-medium break-words text-gray-900">{{ screenSortByDisplayName(screen.sort_by) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="shrink-0 text-sm text-gray-500">Order</dt>
                    <dd class="text-right text-sm font-medium text-gray-900">
                        {{ screen.sort_direction === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest' }}
                    </dd>
                </div>
            </dl>
        </Link>
        <div v-if="showActions" class="flex flex-wrap items-center justify-between gap-3 border-t border-purple-100 px-5 py-3 text-sm font-semibold">
            <Link
                :href="editable ? `/screens/${screen.id}/edit?tab=results` : `/screens/${screen.id}?tab=results`"
                class="inline-flex min-h-10 items-center rounded text-purple-700 hover:text-purple-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                >View Results →</Link
            >
            <Link
                v-if="editable"
                :href="`/screens/${screen.id}/edit?tab=rules`"
                :aria-label="`Edit ${screen.name}`"
                class="inline-flex min-h-10 items-center rounded text-gray-600 hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                >Edit Rules</Link
            >
            <Link
                v-else
                :href="`/screens/${screen.id}?tab=rules`"
                class="inline-flex min-h-10 items-center rounded text-gray-600 hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                >View Rules</Link
            >
        </div>
    </article>
</template>

<script setup lang="ts">
import type { Screen } from '@/types/app/Models/Screen';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        screen: Pick<Screen, 'id' | 'name' | 'index' | 'sort_by' | 'sort_direction'>;
        editable?: boolean;
        showActions?: boolean;
    }>(),
    { editable: false },
);
</script>
