<template>
    <div class="min-h-screen bg-linear-to-b from-white to-gray-50">
        <Head title="Backtests" />

        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-12 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Backtests</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Create and manage your portfolio backtests
                    </p>
                </div>
                <Link
                    href="/backtests/create"
                    class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
                >
                    Create New Backtest
                </Link>
            </div>

            <div v-if="backtests.length > 0">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Your Backtests</h2>
                    <span
                        class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700"
                    >
                        {{ backtests.length }} backtests
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="backtest in backtests"
                        :key="backtest.id"
                        :href="backtest.status === 'completed' ? `/backtests/${backtest.id}` : `/backtests/${backtest.id}/edit`"
                    >
                        <div
                            class="group relative overflow-hidden rounded-xl bg-linear-to-br from-purple-50 to-white p-6 shadow-xs ring-1 ring-purple-100 transition-all duration-200 hover:shadow-md hover:ring-purple-200"
                        >
                            <div class="mb-4 flex items-center justify-between">
                                <h3
                                    class="text-lg font-semibold text-purple-900 group-hover:text-purple-600"
                                >
                                    {{ backtest.name }}
                                </h3>
                                <span
                                    :class="statusBadgeClass(backtest.status)"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ backtest.status }}
                                </span>
                            </div>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Rebalance</dt>
                                    <dd class="text-sm font-medium text-gray-900 capitalize">
                                        {{ backtest.rebalance_frequency }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Max Stocks</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        {{ backtest.max_stocks_to_hold }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500">Weightage</dt>
                                    <dd class="text-sm font-medium text-gray-900 capitalize">
                                        {{ backtest.weightage.replace(/_/g, ' ') }}
                                    </dd>
                                </div>
                            </dl>
                            <div v-if="backtest.status === 'running'" class="mt-4">
                                <div class="h-1.5 w-full rounded-full bg-gray-200">
                                    <div
                                        class="h-1.5 rounded-full bg-purple-600 transition-all duration-300"
                                        :style="`width: ${backtest.progress}%`"
                                    />
                                </div>
                            </div>
                            <div
                                class="absolute bottom-0 left-0 right-0 h-1 bg-linear-to-r from-purple-500 to-purple-600 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                            />
                        </div>
                    </Link>
                </div>
            </div>

            <div
                v-if="backtests.length === 0"
                class="rounded-xl bg-linear-to-br from-purple-50 to-white p-12 text-center shadow-xs ring-1 ring-purple-100"
            >
                <h3 class="text-lg font-semibold text-purple-900">No backtests found</h3>
                <p class="mt-2 text-sm text-gray-500">
                    Create your first backtest to get started
                </p>
                <div class="mt-6">
                    <Link
                        href="/backtests/create"
                        class="inline-flex items-center rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-all duration-200 hover:bg-purple-500 hover:shadow-md"
                    >
                        Create New Backtest
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { Backtest } from '@/types/app/Models/Backtest';

defineProps<{
    backtests: Backtest[];
}>();

function statusBadgeClass(status: string) {
    return {
        'bg-gray-100 text-gray-700': status === 'pending',
        'bg-blue-100 text-blue-700': status === 'running',
        'bg-green-100 text-green-700': status === 'completed',
        'bg-red-100 text-red-700': status === 'failed',
    };
}
</script>
