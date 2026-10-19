<template>
    <div class="mx-auto max-w-7xl">
        <Head title="Home" />

        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 pb-8">
            <div>
                <p class="text-xs font-semibold tracking-widest text-purple-700 uppercase">Home</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-gray-900">
                    {{ user ? 'Your workspace' : 'Explore screens and backtests' }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-600">
                    {{
                        user
                            ? 'Continue with your saved screens and recent backtests.'
                            : 'Explore stock screens and review the results of example strategies.'
                    }}
                </p>
            </div>
            <Link v-if="!user" href="/login" :class="primaryActionClass">Sign in</Link>
        </header>

        <section class="mt-8" aria-labelledby="home-screens-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 id="home-screens-heading" class="text-xl font-semibold text-gray-900">{{ user ? 'Your screens' : 'Example screens' }}</h2>
                        <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">{{ screenCount }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ user ? 'Your saved filters and ranking rules.' : 'Start with a ready-made set of filters.' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <Link href="/screens" :class="secondaryActionClass">View all screens <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                    <Link v-if="user" href="/screens/create" :class="primaryActionClass">
                        <PlusIcon class="size-4" aria-hidden="true" /> Create screen
                    </Link>
                </div>
            </div>

            <div v-if="screens.length" class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <ScreenCard v-for="screen in screens" :key="screen.id" :screen="screen" :editable="!!user" />
            </div>
            <div v-else class="mt-6 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8">
                <h3 class="font-semibold text-gray-900">{{ user ? 'No screens yet' : 'No example screens yet' }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ user ? 'Create a screen to save the filters you use most.' : 'Example screens will appear here when they are available.' }}
                </p>
            </div>
            <p v-if="screenCount > screens.length" class="mt-3 text-xs text-gray-500">
                Showing {{ screens.length }} of {{ screenCount }} screens, most recently updated first.
            </p>
        </section>

        <section class="mt-12 border-t border-gray-200 pt-8" aria-labelledby="home-backtests-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 id="home-backtests-heading" class="text-xl font-semibold text-gray-900">
                            {{ user ? 'Your backtests' : 'Example backtests' }}
                        </h2>
                        <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">{{ backtestCount }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ user ? 'Recent strategies, run status and saved results.' : 'Review completed strategies and their historical results.' }}
                    </p>
                </div>
                <div v-if="user" class="flex flex-wrap items-center gap-4">
                    <template v-if="isPaid">
                        <Link href="/backtests" :class="secondaryActionClass"
                            >View all backtests <ArrowRightIcon class="size-4" aria-hidden="true"
                        /></Link>
                        <Link href="/backtests/create" :class="primaryActionClass"
                            ><PlusIcon class="size-4" aria-hidden="true" /> Create backtest</Link
                        >
                    </template>
                    <Link v-else href="/pricing" :class="secondaryActionClass">View plans <ArrowRightIcon class="size-4" aria-hidden="true" /></Link>
                </div>
            </div>
            <p v-if="user && !isPaid" class="mt-4 text-sm text-gray-600">Paid access is needed to open or run your backtests.</p>

            <ul v-if="backtests.length" class="mt-6 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-xs">
                <li v-for="backtest in backtests" :key="backtest.id">
                    <Link
                        :href="user && !isPaid ? '/pricing' : backtest.url"
                        :aria-label="`${user && !isPaid ? 'View plans to open' : 'Open'} ${backtest.name}`"
                        class="group grid gap-4 rounded-xl p-5 transition-colors hover:bg-purple-50/50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:grid-cols-[minmax(0,1fr)_minmax(14rem,auto)] sm:items-center sm:gap-8"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="min-w-0 font-semibold break-words text-gray-900 group-hover:text-purple-700">{{ backtest.name }}</h3>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClasses[backtest.status]">{{
                                    statusLabels[backtest.status]
                                }}</span>
                            </div>
                            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs leading-5 text-gray-500">
                                <span>{{ screenSortByDisplayName(backtest.index) }}</span>
                                <span v-if="backtest.updated_at">Updated {{ formatDate(backtest.updated_at) }}</span>
                            </p>
                        </div>
                        <dl v-if="backtest.summary_metrics" class="grid grid-cols-2 gap-6 sm:text-right">
                            <div>
                                <dt class="text-xs text-gray-500">CAGR</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums" :class="returnColor(backtest.summary_metrics.cagr)">
                                    {{ formatPercent(backtest.summary_metrics.cagr) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Max drawdown</dt>
                                <dd class="mt-1 text-lg font-semibold tabular-nums" :class="returnColor(backtest.summary_metrics.max_drawdown)">
                                    {{ formatPercent(backtest.summary_metrics.max_drawdown) }}
                                </dd>
                            </div>
                        </dl>
                        <div v-else class="text-sm text-gray-600 sm:text-right">
                            <template v-if="backtest.status === 'running'">
                                <p>{{ backtest.progress }}% complete</p>
                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100"
                                    role="progressbar"
                                    :aria-label="`${backtest.name} progress`"
                                    :aria-valuenow="backtest.progress"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div class="h-full rounded-full bg-blue-500" :style="{ width: `${backtest.progress}%` }" />
                                </div>
                            </template>
                            <p v-else-if="backtest.status === 'failed'">Open to review the failed run</p>
                            <p v-else-if="backtest.status === 'pending'">Ready to configure and run</p>
                            <p v-else>Result summary is not available</p>
                        </div>
                    </Link>
                </li>
            </ul>
            <div v-else class="mt-6 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-6 py-8">
                <h3 class="font-semibold text-gray-900">{{ user ? 'No backtests yet' : 'No example backtests yet' }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{
                        user
                            ? 'Test how your strategy would have performed with historical data.'
                            : 'Completed example backtests will appear here when they are available.'
                    }}
                </p>
            </div>
            <p v-if="backtestCount > backtests.length" class="mt-3 text-xs text-gray-500">
                Showing {{ backtests.length }} of {{ backtestCount }} backtests, most recently updated first.
            </p>
            <p v-if="backtests.some((backtest) => backtest.summary_metrics)" class="mt-4 text-xs leading-5 text-gray-500">
                CAGR is the annualised return. Max drawdown is the largest fall from a previous peak.
            </p>
        </section>
    </div>
</template>

<script setup lang="ts">
import ScreenCard from '@/Components/ScreenCard.vue';
import type { HomeBacktest, HomeScreen } from '@/types/HomePage';
import { formatDate, formatPercent } from '@/utils/format';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import { ArrowRightIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    screens: HomeScreen[];
    screenCount: number;
    backtests: HomeBacktest[];
    backtestCount: number;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isPaid = computed(() => user.value?.is_paid === true);
const primaryActionClass =
    'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600';
const secondaryActionClass =
    'inline-flex min-h-11 items-center gap-2 rounded text-sm font-semibold text-purple-700 hover:text-purple-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600';
const statusLabels = { pending: 'Pending', running: 'Running', completed: 'Completed', failed: 'Failed' };
const statusClasses = {
    pending: 'bg-gray-100 text-gray-700',
    running: 'bg-blue-50 text-blue-700',
    completed: 'bg-emerald-50 text-emerald-700',
    failed: 'bg-rose-50 text-rose-700',
};

function returnColor(value: number): string {
    return value > 0 ? 'text-emerald-700' : value < 0 ? 'text-rose-700' : 'text-gray-700';
}
</script>
