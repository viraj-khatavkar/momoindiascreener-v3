<template>
    <div>
        <Head title="Orders" />
        <div class="flex items-start justify-between">
            <PageHeader description="Manage paid orders.">Orders</PageHeader>
            <a
                :href="`/admin/orders/download?${filterQueryString}`"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                Export CSV
            </a>
        </div>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <StatCard label="Total Orders" :value="count" />
            <StatCard
                label="Revenue"
                :value="formatCurrency(totalRevenue)"
                variant="green"
            />
            <StatCard label="Monthly" :value="planCounts.monthly" />
            <StatCard label="Yearly" :value="planCounts.yearly" />
            <StatCard label="Forever" :value="planCounts.forever" />
            <StatCard label="Newsletter" :value="planCounts.newsletter" />
        </div>

        <!-- Filters -->
        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-3">
                <TextInput
                    v-model="form.from_date"
                    type="date"
                    label="From Date"
                    name="from_date"
                />
                <TextInput
                    v-model="form.to_date"
                    type="date"
                    label="To Date"
                    name="to_date"
                />
                <TextInput
                    v-model="form.search"
                    label="Search (name or email)"
                    name="search"
                    placeholder="Search..."
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
                            Date
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            User
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Invoice #
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Plan
                        </th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="order in orders.data" :key="order.id">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                            {{ formatDate(order.created_at) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ order.user?.name }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ order.invoice_number ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                class="inline-flex rounded-full px-2 text-xs font-semibold leading-5"
                                :class="planBadgeClass(order.plan)"
                            >
                                {{ order.plan }}
                            </span>
                        </td>
                        <td
                            class="whitespace-nowrap px-3 py-4 text-right text-sm text-gray-900"
                        >
                            {{ order.amount }}
                        </td>
                    </tr>
                    <tr v-if="orders.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                            No orders found for this period.
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination :links="orders.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatCurrency, formatDate } from '@/utils';

interface Order {
    id: number;
    created_at: string;
    user: { name: string; email: string } | null;
    invoice_number: number | null;
    plan: string;
    amount: string;
}

interface PaginatedOrders {
    data: Order[];
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    orders: PaginatedOrders;
    count: number;
    totalRevenue: number;
    planCounts: {
        monthly: number;
        yearly: number;
        forever: number;
        newsletter: number;
    };
    filters: {
        from_date: string;
        to_date: string;
        search: string | null;
    };
}>();

const form = useForm({
    from_date: props.filters.from_date,
    to_date: props.filters.to_date,
    search: props.filters.search ?? '',
});

const filterQueryString = computed(() => {
    const params = new URLSearchParams();
    params.set('from_date', form.from_date);
    params.set('to_date', form.to_date);
    if (form.search) {
        params.set('search', form.search);
    }
    return params.toString();
});

function planBadgeClass(plan: string): string {
    const classes: Record<string, string> = {
        monthly: 'bg-blue-100 text-blue-700',
        yearly: 'bg-green-100 text-green-700',
        forever: 'bg-purple-100 text-purple-700',
        newsletter: 'bg-amber-100 text-amber-700',
    };
    return classes[plan] ?? 'bg-gray-100 text-gray-700';
}

function applyFilter() {
    form.get('/admin/orders', {
        preserveState: true,
    });
}
</script>
