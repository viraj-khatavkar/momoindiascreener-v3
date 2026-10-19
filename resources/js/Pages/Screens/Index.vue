<template>
    <div class="min-h-screen bg-linear-to-b from-white to-gray-50">
        <Head title="Screens" />

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-12 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Screens</h1>
                    <p class="mt-2 text-sm text-gray-600">Create and manage your stock screens</p>
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
                    <span class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700"> {{ publicScreens.length }} screens </span>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <ScreenCard v-for="screen in publicScreens" :key="screen.id" :screen="screen" />
                </div>
            </div>

            <div v-if="screens.length > 0">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Your Screens</h2>
                    <span class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700"> {{ screens.length }} screens </span>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <ScreenCard v-for="screen in screens" :key="screen.id" :screen="screen" editable />
                </div>
            </div>

            <div
                v-if="screens.length === 0 && publicScreens.length === 0"
                class="rounded-xl bg-linear-to-br from-purple-50 to-white p-12 text-center shadow-xs ring-1 ring-purple-100"
            >
                <h3 class="text-lg font-semibold text-purple-900">No screens found</h3>
                <p class="mt-2 text-sm text-gray-500">Create your first screen to get started</p>
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
import ScreenCard from '@/Components/ScreenCard.vue';
import type { Screen } from '@/types/app/Models/Screen';

defineProps<{
    screens: Screen[];
    publicScreens: Screen[];
}>();
</script>
