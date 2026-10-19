<template>
    <Link
        :href="editable ? `/screens/${screen.id}/edit` : `/screens/${screen.id}`"
        :aria-label="`${editable ? 'Edit' : 'Open'} ${screen.name}`"
        class="group relative block h-full min-w-0 overflow-hidden rounded-xl bg-linear-to-br from-purple-50 to-white p-6 shadow-xs ring-1 ring-purple-100 transition duration-200 hover:shadow-md hover:ring-purple-200 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-600"
    >
        <h3 class="mb-4 text-lg font-semibold break-words text-purple-900 group-hover:text-purple-600">
            {{ screen.name }}
        </h3>
        <dl class="space-y-3">
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-sm text-gray-500">Index</dt>
                <dd class="text-right text-sm font-medium break-words text-gray-900">{{ screenSortByDisplayName(screen.index) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-sm text-gray-500">Ranking Factor</dt>
                <dd class="text-right text-sm font-medium break-words text-gray-900">{{ screenSortByDisplayName(screen.sort_by) }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="shrink-0 text-sm text-gray-500">Order</dt>
                <dd class="text-right text-sm font-medium text-gray-900">
                    {{ screen.sort_direction === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest' }}
                </dd>
            </div>
        </dl>
        <div
            class="absolute inset-x-0 bottom-0 h-1 bg-linear-to-r from-purple-500 to-purple-600 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
            aria-hidden="true"
        />
    </Link>
</template>

<script setup lang="ts">
import type { Screen } from '@/types/app/Models/Screen';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        screen: Pick<Screen, 'id' | 'name' | 'index' | 'sort_by' | 'sort_direction'>;
        editable?: boolean;
    }>(),
    { editable: false },
);
</script>
