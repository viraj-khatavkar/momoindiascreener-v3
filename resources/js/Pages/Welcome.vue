<template>
    <div class="mx-auto max-w-7xl">
        <Head title="Home" />

        <section>
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-bold tracking-tight text-gray-950">Screens</h1>
                <Link v-if="user" href="/screens/create" :class="primaryActionClass">
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    Create screen
                </Link>
            </div>

            <div v-if="user" class="mt-8">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900">Your screens</h2>
                    <Link href="/screens" class="text-sm font-semibold text-purple-700 hover:text-purple-600">
                        View all<span v-if="personalScreenCount > personalScreens.length"> {{ personalScreenCount }}</span>
                    </Link>
                </div>

                <div v-if="personalScreens.length" class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <HomeScreenCard
                        v-for="screen in personalScreens"
                        :key="screen.id"
                        :screen="screen"
                        :preview="findScreenPreview(personalScreenPreviews, screen.id)"
                        preview-prop="personalScreenPreviews"
                        editable
                    />
                </div>
                <div
                    v-else
                    class="mt-4 flex flex-col items-start justify-between gap-4 rounded-xl border border-dashed border-gray-300 px-5 py-4 sm:flex-row sm:items-center"
                >
                    <p class="text-sm text-gray-500">You do not have a personal screen.</p>
                    <Link href="/screens/create" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> Create screen </Link>
                </div>
            </div>

            <div :class="user ? 'mt-12' : 'mt-8'">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-gray-900">Public screens</h2>
                    <Link href="/screens" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> View all </Link>
                </div>

                <div v-if="publicScreens.length" class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <HomeScreenCard
                        v-for="screen in publicScreens"
                        :key="screen.id"
                        :screen="screen"
                        :preview="findScreenPreview(publicScreenPreviews, screen.id)"
                        preview-prop="publicScreenPreviews"
                    />
                </div>
                <div v-else class="mt-4 rounded-xl border border-gray-200 px-5 py-6 text-center">
                    <p class="text-sm text-gray-500">Public screens are not available.</p>
                </div>
            </div>
        </section>

        <section class="mt-16 border-t border-gray-200 pt-12">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950">Backtests</h2>
                <Link v-if="isPaid" href="/backtests/create" :class="primaryActionClass">
                    <BeakerIcon class="h-4 w-4" aria-hidden="true" />
                    Create backtest
                </Link>
            </div>

            <template v-if="isPaid">
                <div v-if="backtestActivity.length" class="mt-8">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900">Activity</h3>
                        <Link href="/backtests" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> View all </Link>
                    </div>

                    <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <Link
                            v-for="backtest in backtestActivity"
                            :key="backtest.id"
                            :href="`/backtests/${backtest.id}`"
                            class="group flex flex-col gap-4 px-5 py-4 transition hover:bg-purple-50/50 sm:flex-row sm:items-center"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                                :class="activityStatusClass(backtest.status)"
                            >
                                <ExclamationTriangleIcon v-if="backtest.status === 'failed'" class="h-5 w-5" aria-hidden="true" />
                                <ArrowPathIcon v-else-if="backtest.status === 'running'" class="h-5 w-5" aria-hidden="true" />
                                <ClockIcon v-else class="h-5 w-5" aria-hidden="true" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <h4 class="truncate font-semibold text-gray-900 group-hover:text-purple-700">
                                        {{ backtest.name }}
                                    </h4>
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="activityStatusClass(backtest.status)">
                                        {{ activityLabel(backtest.status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ activityDescription(backtest) }}
                                </p>
                                <div
                                    v-if="backtest.status === 'running'"
                                    class="mt-2 h-1.5 max-w-sm overflow-hidden rounded-full bg-gray-200"
                                    role="progressbar"
                                    :aria-valuenow="backtest.progress"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="h-full rounded-full bg-blue-500 transition-[width] duration-300"
                                        :style="{ width: `${backtest.progress}%` }"
                                    />
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3 text-xs text-gray-500 sm:ml-auto">
                                <span v-if="backtest.updated_at">
                                    {{ formatDate(backtest.updated_at) }}
                                </span>
                                <ArrowRightIcon
                                    class="h-4 w-4 text-gray-400 transition-transform group-hover:translate-x-0.5 group-hover:text-purple-600"
                                    aria-hidden="true"
                                />
                            </div>
                        </Link>
                    </div>
                </div>

                <div :class="backtestActivity.length ? 'mt-10' : 'mt-8'">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent backtests</h3>
                        <Link href="/backtests" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> View all </Link>
                    </div>

                    <div v-if="recentBacktests.length" class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <Link
                            v-for="backtest in recentBacktests"
                            :key="backtest.id"
                            :href="`/backtests/${backtest.id}`"
                            class="group rounded-xl focus:outline-hidden focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2"
                        >
                            <article
                                class="h-full rounded-xl border border-gray-200 bg-white p-5 transition duration-200 group-hover:border-purple-200 group-hover:shadow-sm"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <h4 class="font-semibold text-gray-950 group-hover:text-purple-700">
                                        {{ backtest.name }}
                                    </h4>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"> Completed </span>
                                </div>

                                <dl v-if="backtest.summary_metrics" class="mt-5 grid grid-cols-2 divide-x divide-gray-200 rounded-lg bg-gray-50 py-4">
                                    <div class="px-4">
                                        <dt class="text-xs text-gray-500">CAGR</dt>
                                        <dd class="mt-1 text-lg font-semibold" :class="metricClass(backtest.summary_metrics.cagr)">
                                            {{ formatPercent(backtest.summary_metrics.cagr) }}
                                        </dd>
                                    </div>
                                    <div class="px-4">
                                        <dt class="text-xs text-gray-500">Max drawdown</dt>
                                        <dd class="mt-1 text-lg font-semibold text-red-700">
                                            {{ formatPercent(backtest.summary_metrics.max_drawdown) }}
                                        </dd>
                                    </div>
                                </dl>
                                <p v-else class="mt-5 rounded-lg bg-gray-50 px-4 py-5 text-sm text-gray-500">A result summary is not available.</p>

                                <div class="mt-5 flex items-center justify-between text-xs text-gray-500">
                                    <span v-if="backtest.completed_at">
                                        {{ formatDate(backtest.completed_at) }}
                                    </span>
                                    <span class="ml-auto flex items-center gap-1 font-semibold text-purple-700">
                                        Open
                                        <ArrowRightIcon class="h-4 w-4" aria-hidden="true" />
                                    </span>
                                </div>
                            </article>
                        </Link>
                    </div>
                    <div
                        v-else
                        class="mt-4 flex flex-col items-start justify-between gap-4 rounded-xl border border-dashed border-gray-300 px-5 py-4 sm:flex-row sm:items-center"
                    >
                        <p class="text-sm text-gray-500">You do not have a completed backtest.</p>
                        <Link href="/backtests/create" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> Create backtest </Link>
                    </div>
                </div>
            </template>

            <div
                v-else
                class="mt-8 flex flex-col items-start justify-between gap-4 rounded-xl border border-gray-200 px-5 py-5 sm:flex-row sm:items-center"
            >
                <div>
                    <h3 class="font-semibold text-gray-900">Backtests need paid access</h3>
                    <p class="mt-1 text-sm text-gray-500">Run screen rules on historical market data.</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link v-if="!user" href="/login" class="text-sm font-semibold text-gray-700 hover:text-gray-900"> Sign in </Link>
                    <Link href="/pricing" class="text-sm font-semibold text-purple-700 hover:text-purple-600"> View access </Link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import HomeScreenCard from '@/Components/HomeScreenCard.vue';
import type { HomeBacktestActivity, HomeRecentBacktest, HomeScreen, HomeScreenPreview } from '@/types/HomePage';
import { formatDate, formatPercent } from '@/utils/format';
import { ArrowPathIcon, BeakerIcon, ClockIcon, ExclamationTriangleIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { ArrowRightIcon } from '@heroicons/vue/20/solid';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    publicScreens: HomeScreen[];
    publicScreenPreviews?: HomeScreenPreview[];
    personalScreens: HomeScreen[];
    personalScreenCount: number;
    personalScreenPreviews?: HomeScreenPreview[];
    backtestActivity: HomeBacktestActivity[];
    recentBacktests: HomeRecentBacktest[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isPaid = computed(() => user.value?.is_paid === true);

const primaryActionClass =
    'inline-flex items-center gap-2 rounded-lg bg-purple-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-purple-600 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2';

function findScreenPreview(previews: HomeScreenPreview[] | undefined, screenId: number): HomeScreenPreview | undefined {
    return previews?.find((preview) => preview.screen_id === screenId);
}

function activityLabel(status: HomeBacktestActivity['status']): string {
    if (status === 'failed') {
        return 'Failed';
    }

    if (status === 'running') {
        return 'Running';
    }

    return 'Queued';
}

function activityDescription(backtest: HomeBacktestActivity): string {
    if (backtest.status === 'failed') {
        return 'Open this backtest to review the failed run.';
    }

    if (backtest.status === 'running') {
        return `${backtest.progress}% complete`;
    }

    return 'Waiting for a worker to start the run.';
}

function activityStatusClass(status: HomeBacktestActivity['status']): string {
    if (status === 'failed') {
        return 'bg-red-50 text-red-700';
    }

    if (status === 'running') {
        return 'bg-blue-50 text-blue-700';
    }

    return 'bg-amber-50 text-amber-700';
}

function metricClass(value: number): string {
    return value >= 0 ? 'text-emerald-700' : 'text-red-700';
}
</script>
