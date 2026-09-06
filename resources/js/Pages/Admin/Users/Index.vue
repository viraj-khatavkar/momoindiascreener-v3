<template>
    <div class="space-y-8">
        <Head title="Users" />

        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-purple-700">Customers</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Users</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Find members, check plan access, and review plans that end soon.</p>
            </div>
            <a
                :href="downloadUrl"
                class="inline-flex min-h-11 w-fit items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:border-slate-400 hover:bg-slate-50"
            >
                <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                Export current view
            </a>
        </header>

        <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="User summary">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">All users</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {{ formatNumber(stats.total) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <UsersIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-4 hidden text-xs text-slate-500 sm:block">Registered accounts</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Paid members</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {{ formatNumber(stats.paid) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <CheckBadgeIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-4 hidden text-xs text-slate-500 sm:block">{{ formatNumber(stats.newsletter) }} newsletter members</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">New this month</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {{ formatNumber(stats.newThisMonth) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                        <UserPlusIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-4 hidden text-xs text-slate-500 sm:block">New registered accounts</p>
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
                <p class="mt-4 hidden text-xs text-slate-500 sm:block">Paid plans ending in 7 days</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="user-filters-heading">
            <h2 id="user-filters-heading" class="sr-only">Filter users</h2>
            <form class="space-y-4" @submit.prevent="applyFilter">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                    <div class="relative min-w-0 flex-1">
                        <label for="user-search" class="sr-only">Search users</label>
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400"
                            aria-hidden="true"
                        />
                        <input
                            id="user-search"
                            v-model="form.search"
                            name="search"
                            type="search"
                            autocomplete="off"
                            placeholder="Search by name or email"
                            class="block min-h-11 w-full rounded-xl border-0 bg-slate-50 py-2.5 pr-10 pl-11 text-sm text-slate-950 outline-1 -outline-offset-1 outline-slate-200 placeholder:text-slate-400 focus:bg-white focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600"
                        />
                    </div>

                    <div class="grid grid-cols-3 rounded-xl bg-slate-100 p-1 sm:flex" aria-label="Paid status">
                        <button
                            v-for="status in paidStatusOptions"
                            :key="status.value"
                            type="button"
                            class="min-h-9 rounded-lg px-3 py-1.5 text-sm font-semibold transition-colors"
                            :class="form.is_paid === status.value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900'"
                            :aria-pressed="form.is_paid === status.value"
                            :disabled="form.processing"
                            @click="selectPaidStatus(status.value)"
                        >
                            {{ status.label }}
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 xl:flex-none"
                            :aria-expanded="filtersOpen"
                            @click="filtersOpen = !filtersOpen"
                        >
                            <FunnelIcon class="size-4" aria-hidden="true" />
                            Dates
                            <span
                                v-if="dateFilterCount"
                                class="flex size-5 items-center justify-center rounded-full bg-purple-100 text-[0.6875rem] font-bold text-purple-700"
                            >
                                {{ dateFilterCount }}
                            </span>
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-xl bg-purple-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60 xl:flex-none"
                        >
                            {{ form.processing ? 'Updating…' : 'Search' }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="filtersOpen"
                    class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end"
                >
                    <TextInput v-model="form.from_date" type="date" label="Registered from" name="from_date" />
                    <TextInput v-model="form.to_date" type="date" label="Registered to" name="to_date" />
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="min-h-10 w-fit text-sm font-semibold text-slate-500 hover:text-slate-950"
                        @click="clearFilters"
                    >
                        Clear all filters
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="user-results-heading">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="user-results-heading" class="font-bold text-slate-950">User list</h2>
                    <p class="mt-0.5 text-sm text-slate-500">{{ resultSummary }}</p>
                </div>
                <span v-if="form.processing" class="inline-flex items-center gap-2 text-xs font-semibold text-purple-700">
                    <span class="size-2 animate-pulse rounded-full bg-purple-600" />
                    Updating
                </span>
            </div>

            <div v-if="users.data.length">
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full">
                        <thead class="bg-slate-50/80">
                            <tr class="border-b border-slate-200">
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">User</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Access</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Plan end</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-slate-50/70">
                                <td class="px-6 py-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span
                                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-purple-50 text-xs font-bold text-purple-700"
                                        >
                                            {{ initials(user.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ user.name }}
                                            </p>
                                            <p class="truncate text-sm text-slate-500">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="user.is_paid ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                        >
                                            {{ user.is_paid ? 'Paid' : 'Free' }}
                                        </span>
                                        <span
                                            v-if="user.is_newsletter_paid"
                                            class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700"
                                        >
                                            Newsletter
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <template v-if="user.plan_ends_at">
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ formatDate(user.plan_ends_at) }}
                                        </p>
                                        <p v-if="isExpiringSoon(user)" class="mt-0.5 text-xs font-semibold text-amber-700">
                                            {{ expiryLabel(user.plan_ends_at) }}
                                        </p>
                                    </template>
                                    <span v-else class="text-sm text-slate-400">No end date</span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-slate-500">
                                    {{ formatDate(user.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    <article v-for="user in users.data" :key="user.id" class="p-5">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-purple-50 text-xs font-bold text-purple-700"
                            >
                                {{ initials(user.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ user.name }}
                                </p>
                                <p class="truncate text-sm text-slate-500">{{ user.email }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="user.is_paid ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ user.is_paid ? 'Paid' : 'Free' }}
                            </span>
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <div>
                                <dt class="text-xs font-medium text-slate-500">Plan end</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ user.plan_ends_at ? formatDate(user.plan_ends_at) : 'No end date' }}
                                </dd>
                                <p v-if="user.plan_ends_at && isExpiringSoon(user)" class="mt-0.5 text-xs font-semibold text-amber-700">
                                    {{ expiryLabel(user.plan_ends_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <dt class="text-xs font-medium text-slate-500">Joined</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ formatDate(user.created_at) }}
                                </dd>
                            </div>
                        </dl>
                        <span
                            v-if="user.is_newsletter_paid"
                            class="mt-3 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700"
                        >
                            Newsletter member
                        </span>
                    </article>
                </div>
            </div>

            <div v-else class="px-6 py-16 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <UsersIcon class="size-6" aria-hidden="true" />
                </span>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">No users found</h3>
                <p class="mt-1 text-sm text-slate-500">Try a different search or date range.</p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="mt-4 text-sm font-semibold text-purple-700 hover:text-purple-900"
                    @click="clearFilters"
                >
                    Clear all filters
                </button>
            </div>

            <div v-if="users.data.length" class="border-t border-slate-200 px-5 pb-5 sm:px-6">
                <Pagination :links="users.links" />
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    CheckBadgeIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';

interface User {
    id: number;
    name: string;
    email: string;
    is_paid: boolean;
    is_newsletter_paid: boolean;
    plan_ends_at: string | null;
    created_at: string;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    users: PaginatedUsers;
    filters: {
        search: string | null;
        is_paid: string | null;
        from_date: string | null;
        to_date: string | null;
    };
    stats: {
        total: number;
        paid: number;
        newThisMonth: number;
        newsletter: number;
        expiringIn7Days: number;
    };
}>();

const paidStatusOptions = [
    { value: '', label: 'All' },
    { value: '1', label: 'Paid' },
    { value: '0', label: 'Free' },
];

const form = useForm({
    search: props.filters.search ?? '',
    is_paid: props.filters.is_paid ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
});

const filtersOpen = ref(Boolean(form.from_date || form.to_date));

const filterQueryString = computed(() => {
    const params = new URLSearchParams();

    if (form.search) {
        params.set('search', form.search);
    }

    if (form.is_paid !== '') {
        params.set('is_paid', form.is_paid);
    }

    if (form.from_date) {
        params.set('from_date', form.from_date);
    }

    if (form.to_date) {
        params.set('to_date', form.to_date);
    }

    return params.toString();
});

const downloadUrl = computed(() => {
    const query = filterQueryString.value;

    return query ? `/admin/users/download?${query}` : '/admin/users/download';
});

const dateFilterCount = computed(() => Number(Boolean(form.from_date)) + Number(Boolean(form.to_date)));

const hasFilters = computed(() => Boolean(form.search || form.is_paid !== '' || form.from_date || form.to_date));

const resultSummary = computed(() => {
    if (!props.users.total || props.users.from === null || props.users.to === null) {
        return 'No matching users';
    }

    return `${formatNumber(props.users.from)}–${formatNumber(props.users.to)} of ${formatNumber(props.users.total)} users`;
});

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

function initials(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function daysUntil(date: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const endDate = new Date(date);
    endDate.setHours(0, 0, 0, 0);

    return Math.ceil((endDate.getTime() - today.getTime()) / 86_400_000);
}

function isExpiringSoon(user: User): boolean {
    if (!user.is_paid || !user.plan_ends_at) {
        return false;
    }

    const days = daysUntil(user.plan_ends_at);

    return days >= 0 && days <= 7;
}

function expiryLabel(date: string): string {
    const days = daysUntil(date);

    if (days === 0) {
        return 'Ends today';
    }

    if (days === 1) {
        return 'Ends tomorrow';
    }

    return `Ends in ${days} days`;
}

function applyFilter(): void {
    form.get('/admin/users', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function selectPaidStatus(value: string): void {
    if (form.is_paid === value) {
        return;
    }

    form.is_paid = value;
    applyFilter();
}

function clearFilters(): void {
    form.search = '';
    form.is_paid = '';
    form.from_date = '';
    form.to_date = '';
    filtersOpen.value = false;
    applyFilter();
}
</script>
