<template>
    <div class="min-h-screen bg-linear-to-b from-white to-gray-50">
        <Head title="Screens" />

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-12 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Screens</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Create and manage your stock screens
                    </p>
                </div>
                <Link
                    href="/screens/create"
                    class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
                >
                    Create New Screen
                </Link>
            </div>

            <div v-if="publicScreens.length > 0" class="mb-16">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Example Screens</h2>
                    <span
                        class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700"
                    >
                        {{ publicScreens.length }} screens
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="screen in publicScreens"
                        :key="screen.id"
                        :href="`/screens/${screen.id}`"
                    >
                        <div
                            class="group relative overflow-hidden rounded-xl bg-linear-to-br from-purple-50 to-white p-6 shadow-xs ring-1 ring-purple-100 transition-all duration-200 hover:shadow-md hover:ring-purple-200"
                        >
                            <div class="mb-4">
                                <h3
                                    class="text-lg font-semibold text-purple-900 group-hover:text-purple-600"
                                >
                                    {{ screen.name }}
                                </h3>
                            </div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Index</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ screenSortByDisplayName(screen.index) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Ranking Factor</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ screenSortByDisplayName(screen.sort_by) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Order</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{
                                            screen.sort_direction === 'asc'
                                                ? 'Lowest to Highest'
                                                : 'Highest to Lowest'
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <div
                                class="absolute bottom-0 left-0 right-0 h-1 bg-linear-to-r from-purple-500 to-purple-600 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            />
                        </div>
                    </Link>
                </div>
            </div>

            <div v-if="screens.length > 0">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Your Screens</h2>
                    <span
                        class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700"
                    >
                        {{ screens.length }} screens
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="screen in screens"
                        :key="screen.id"
                        :href="`/screens/${screen.id}/edit`"
                    >
                        <div
                            class="group relative overflow-hidden rounded-xl bg-linear-to-br from-purple-50 to-white p-6 shadow-xs ring-1 ring-purple-100 transition-all duration-200 hover:shadow-md hover:ring-purple-200"
                        >
                            <div class="mb-4">
                                <h3
                                    class="text-lg font-semibold text-purple-900 group-hover:text-purple-600"
                                >
                                    {{ screen.name }}
                                </h3>
                            </div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Index</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ screenSortByDisplayName(screen.index) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Ranking Factor</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ screenSortByDisplayName(screen.sort_by) }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Order</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{
                                            screen.sort_direction === 'asc'
                                                ? 'Lowest to Highest'
                                                : 'Highest to Lowest'
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <div
                                class="absolute bottom-0 left-0 right-0 h-1 bg-linear-to-r from-purple-500 to-purple-600 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            />
                        </div>
                    </Link>
                </div>
            </div>

            <div
                v-if="screens.length === 0 && publicScreens.length === 0"
                class="rounded-xl bg-linear-to-br from-purple-50 to-white p-12 text-center shadow-xs ring-1 ring-purple-100"
            >
                <h3 class="text-lg font-semibold text-purple-900">No screens found</h3>
                <p class="mt-2 text-sm text-gray-500">
                    Create your first screen to get started
                </p>
                <div class="mt-6">
                    <Link
                        href="/screens/create"
                        class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
                    >
                        Create New Screen
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import type { Screen } from '@/types/app/Models/Screen';

defineProps<{
    screens: Screen[];
    publicScreens: Screen[];
}>();
</script>
