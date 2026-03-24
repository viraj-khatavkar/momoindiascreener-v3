<template>
    <div>
        <Head title="Users" />
        <div class="flex items-start justify-between">
            <PageHeader description="Manage registered users.">Users</PageHeader>
            <a
                :href="`/admin/users/download?${filterQueryString}`"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                Export CSV
            </a>
        </div>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard label="Total Users" :value="stats.total" />
            <StatCard label="Paid" :value="stats.paid" variant="green" />
            <StatCard label="New This Month" :value="stats.newThisMonth" variant="blue" />
            <StatCard label="Newsletter" :value="stats.newsletter" />
            <StatCard label="Expiring (7 days)" :value="stats.expiringIn7Days" variant="amber" />
        </div>

        <!-- Filters -->
        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput
                    v-model="form.search"
                    label="Search (name or email)"
                    name="search"
                    placeholder="Search..."
                />
                <SelectInput
                    v-model="form.is_paid"
                    label="Paid Status"
                    name="is_paid"
                    :options="paidStatusOptions"
                />
                <TextInput
                    v-model="form.from_date"
                    type="date"
                    label="Registered From"
                    name="from_date"
                />
                <TextInput
                    v-model="form.to_date"
                    type="date"
                    label="Registered To"
                    name="to_date"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Filter
            </button>
        </form>

        <!-- Table -->
        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">
                            Name
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Email
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Status
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Plan Ends At
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Registered
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        :class="{ 'bg-amber-50': isExpiringSoon(user) }"
                    >
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                            {{ user.name }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ user.email }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                v-if="user.is_paid"
                                class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-700"
                            >
                                Paid
                            </span>
                            <span
                                v-else-if="user.is_newsletter_paid"
                                class="inline-flex rounded-full bg-blue-100 px-2 text-xs font-semibold leading-5 text-blue-700"
                            >
                                Newsletter
                            </span>
                            <span
                                v-else
                                class="inline-flex rounded-full bg-gray-100 px-2 text-xs font-semibold leading-5 text-gray-500"
                            >
                                Free
                            </span>
                        </td>
                        <td
                            class="whitespace-nowrap px-3 py-4 text-sm"
                            :class="
                                isExpiringSoon(user)
                                    ? 'font-medium text-red-600'
                                    : 'text-gray-500'
                            "
                        >
                            {{ user.plan_ends_at ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ formatDate(user.created_at) }}
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                            No users found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination :links="users.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatCard from '@/Components/StatCard.vue';
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
    { id: '', name: 'All' },
    { id: '1', name: 'Paid' },
    { id: '0', name: 'Unpaid' },
];

const form = useForm({
    search: props.filters.search ?? '',
    is_paid: props.filters.is_paid ?? '',
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
});

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

function isExpiringSoon(user: User): boolean {
    if (!user.is_paid || !user.plan_ends_at) {
        return false;
    }
    const diff = new Date(user.plan_ends_at).getTime() - Date.now();
    return diff >= 0 && diff < 7 * 24 * 60 * 60 * 1000;
}

function applyFilter() {
    form.get('/admin/users', {
        preserveState: true,
    });
}
</script>
