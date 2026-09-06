<template>
    <div class="space-y-8">
        <Head title="Admin overview" />

        <section
            class="relative overflow-hidden rounded-3xl border border-purple-200 bg-gradient-to-br from-purple-50 via-white to-blue-50 px-6 py-7 text-slate-950 shadow-sm sm:px-8 sm:py-9"
        >
            <div class="absolute -top-28 -right-20 size-72 rounded-full bg-purple-200/50 blur-3xl" aria-hidden="true" />
            <div class="absolute -bottom-32 left-1/3 size-64 rounded-full bg-blue-200/40 blur-3xl" aria-hidden="true" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-purple-700">{{ todayLabel }}</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Business at a glance</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600 sm:text-base">
                        See revenue, member growth, and items that need your attention.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/admin/users"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-purple-700"
                        prefetch
                    >
                        View users
                        <ArrowRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                    <Link
                        href="/admin/orders"
                        class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                        prefetch
                    >
                        View orders
                    </Link>
                </div>
            </div>
        </section>

        <section aria-labelledby="summary-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-purple-700">Performance</p>
                    <h2 id="summary-heading" class="mt-1 text-xl font-bold text-slate-950">This month</h2>
                </div>
                <p class="hidden text-sm text-slate-500 sm:block">Compared with last month</p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Revenue</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                                {{ formatCurrency(ordersComparison.thisMonth.revenue) }}
                            </p>
                        </div>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                            <BanknotesIcon class="size-5" aria-hidden="true" />
                        </span>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-3 text-xs">
                        <span class="hidden text-slate-500 sm:inline"> {{ ordersComparison.thisMonth.count }} paid orders </span>
                        <span
                            v-if="revenueChange !== null"
                            class="inline-flex items-center gap-1 font-semibold"
                            :class="revenueChange >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                        >
                            <component :is="revenueChange >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="size-4" aria-hidden="true" />
                            {{ formatPercentageChange(revenueChange) }}
                        </span>
                        <span v-else class="font-medium text-slate-400">No prior revenue</span>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Paid members</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                                {{ formatNumber(stats.paidUsers) }}
                            </p>
                        </div>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-purple-50 text-purple-700">
                            <UserGroupIcon class="size-5" aria-hidden="true" />
                        </span>
                    </div>
                    <div class="mt-5 flex items-center justify-between gap-3 text-xs text-slate-500">
                        <span>{{ paidUserRate }}% of all users</span>
                        <span class="hidden sm:inline">{{ formatNumber(stats.newsletterUsers) }} newsletter</span>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">New users</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                                {{ formatNumber(stats.newThisMonth) }}
                            </p>
                        </div>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                            <UsersIcon class="size-5" aria-hidden="true" />
                        </span>
                    </div>
                    <p class="mt-5 text-xs text-slate-500">{{ formatNumber(stats.totalUsers) }} total registered users</p>
                </article>

                <article
                    class="rounded-2xl border p-4 shadow-sm sm:p-5"
                    :class="stats.expiringIn7Days > 0 ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white'"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Ending soon</p>
                            <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                                {{ formatNumber(stats.expiringIn7Days) }}
                            </p>
                        </div>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                            <CalendarDaysIcon class="size-5" aria-hidden="true" />
                        </span>
                    </div>
                    <p class="mt-5 text-xs text-slate-500">Paid plans ending in 7 days</p>
                </article>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.8fr)]">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="recent-orders-heading">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="recent-orders-heading" class="font-bold text-slate-950">Recent orders</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Latest paid orders</p>
                    </div>
                    <Link
                        href="/admin/orders"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-purple-700 hover:text-purple-900"
                        prefetch
                    >
                        View all
                        <ArrowRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                </div>

                <div v-if="recentOrders.length" class="divide-y divide-slate-100">
                    <div
                        v-for="order in recentOrders"
                        :key="order.id"
                        class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:px-6"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600"
                            >
                                {{ initials(order.user?.name) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ order.user?.name ?? 'Deleted user' }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ order.user?.email ?? formatDate(order.created_at) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 sm:justify-end">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="planBadgeClass(order.plan)">
                                {{ order.plan }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3 sm:block sm:text-right">
                            <p class="text-sm font-bold text-slate-950">
                                {{ formatMoney(order.amount) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ formatDate(order.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <ShoppingBagIcon class="size-6" aria-hidden="true" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900">No recent orders</p>
                    <p class="mt-1 text-sm text-slate-500">New paid orders will appear here.</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="expiring-users-heading">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 id="expiring-users-heading" class="font-bold text-slate-950">Needs attention</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Plans ending in 14 days</p>
                    </div>
                    <span v-if="expiringUsers.length" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">
                        {{ expiringUsers.length }}
                    </span>
                </div>

                <div v-if="expiringUsers.length" class="divide-y divide-slate-100">
                    <div v-for="user in expiringUsers" :key="user.id" class="flex items-center gap-3 px-5 py-4">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-purple-50 text-xs font-bold text-purple-700">
                            {{ initials(user.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ user.name }}
                            </p>
                            <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="isExpiringSoon(user.plan_ends_at) ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700'"
                        >
                            {{ expiryLabel(user.plan_ends_at) }}
                        </span>
                    </div>
                </div>

                <div v-else class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <CheckCircleIcon class="size-6" aria-hidden="true" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900">Nothing needs review</p>
                    <p class="mt-1 text-sm text-slate-500">No paid plans end in the next 14 days.</p>
                </div>

                <div class="border-t border-slate-100 px-5 py-3">
                    <Link
                        href="/admin/users?is_paid=1"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-purple-700 hover:text-purple-900"
                        prefetch
                    >
                        Review paid users
                        <ArrowRightIcon class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    ArrowRightIcon,
    ArrowTrendingDownIcon,
    ArrowTrendingUpIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ShoppingBagIcon,
    UserGroupIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatCurrency, formatDate } from '@/utils';

const props = defineProps<{
    stats: {
        totalUsers: number;
        paidUsers: number;
        newThisMonth: number;
        newsletterUsers: number;
        expiringIn7Days: number;
    };
    ordersComparison: {
        thisMonth: { count: number; revenue: number };
        lastMonth: { count: number; revenue: number };
    };
    recentOrders: {
        id: number;
        user: { name: string; email: string } | null;
        plan: string;
        amount: string | number;
        created_at: string;
    }[];
    expiringUsers: {
        id: number;
        name: string;
        email: string;
        plan_ends_at: string;
    }[];
}>();

const todayLabel = new Intl.DateTimeFormat('en-IN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(new Date());

const revenueChange = computed(() => {
    const previousRevenue = Number(props.ordersComparison.lastMonth.revenue);

    if (previousRevenue === 0) {
        return null;
    }

    return ((Number(props.ordersComparison.thisMonth.revenue) - previousRevenue) / previousRevenue) * 100;
});

const paidUserRate = computed(() => {
    if (props.stats.totalUsers === 0) {
        return 0;
    }

    return Math.round((props.stats.paidUsers / props.stats.totalUsers) * 100);
});

function formatMoney(value: string | number): string {
    return formatCurrency(Number(value));
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

function formatPercentageChange(value: number): string {
    return `${Math.abs(value).toFixed(0)}%`;
}

function initials(name: string | undefined): string {
    if (!name) {
        return '—';
    }

    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function planBadgeClass(plan: string): string {
    const classes: Record<string, string> = {
        monthly: 'bg-blue-50 text-blue-700',
        yearly: 'bg-emerald-50 text-emerald-700',
        forever: 'bg-purple-50 text-purple-700',
        newsletter: 'bg-amber-50 text-amber-700',
    };

    return classes[plan] ?? 'bg-slate-100 text-slate-700';
}

function daysUntil(date: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const endDate = new Date(date);
    endDate.setHours(0, 0, 0, 0);

    return Math.max(0, Math.ceil((endDate.getTime() - today.getTime()) / 86_400_000));
}

function isExpiringSoon(date: string): boolean {
    return daysUntil(date) <= 3;
}

function expiryLabel(date: string): string {
    const days = daysUntil(date);

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return '1 day';
    }

    return `${days} days`;
}
</script>
