<template>
    <div>
        <Head title="Admin Dashboard" />
        <PageHeader description="Overview of key metrics and recent activity.">
            Admin Dashboard
        </PageHeader>

        <!-- User Stats -->
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Users</h2>
        <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard label="Total Users" :value="stats.totalUsers" />
            <StatCard label="Paid Users" :value="stats.paidUsers" variant="green" />
            <StatCard label="New This Month" :value="stats.newThisMonth" variant="blue" />
            <StatCard label="Newsletter" :value="stats.newsletterUsers" />
            <StatCard label="Expiring (7 days)" :value="stats.expiringIn7Days" variant="amber" />
        </div>

        <!-- Revenue Stats -->
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">
            Revenue
        </h2>
        <div class="mt-2 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard
                label="This Month Orders"
                :value="ordersComparison.thisMonth.count"
            />
            <StatCard
                label="This Month Revenue"
                :value="formatCurrency(ordersComparison.thisMonth.revenue)"
                variant="green"
            />
            <StatCard
                label="Last Month Orders"
                :value="ordersComparison.lastMonth.count"
            />
            <StatCard
                label="Last Month Revenue"
                :value="formatCurrency(ordersComparison.lastMonth.revenue)"
            />
        </div>

        <!-- Recent Orders + Expiring Users -->
        <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
            <!-- Recent Orders -->
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Recent Orders
                    </h2>
                    <Link
                        href="/admin/orders"
                        class="text-sm font-medium text-purple-600 hover:text-purple-500"
                    >
                        View all
                    </Link>
                </div>
                <div class="mt-2 overflow-hidden rounded-lg shadow ring-1 ring-gray-200">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 pl-4 pr-3 text-left text-xs font-medium text-gray-500">
                                    Date
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                    User
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                    Plan
                                </th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">
                                    Amount
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="order in recentOrders" :key="order.id">
                                <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500">
                                    {{ formatDate(order.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                    {{ order.user?.name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                    {{ order.plan }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm text-gray-500">
                                    {{ order.amount }}
                                </td>
                            </tr>
                            <tr v-if="recentOrders.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-4 text-center text-sm text-gray-500"
                                >
                                    No recent orders.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Expiring Users -->
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Expiring Soon
                    </h2>
                    <Link
                        href="/admin/users?is_paid=1"
                        class="text-sm font-medium text-purple-600 hover:text-purple-500"
                    >
                        View all paid
                    </Link>
                </div>
                <div class="mt-2 overflow-hidden rounded-lg shadow ring-1 ring-gray-200">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 pl-4 pr-3 text-left text-xs font-medium text-gray-500">
                                    User
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">
                                    Email
                                </th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">
                                    Expires
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="user in expiringUsers" :key="user.id">
                                <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-900">
                                    {{ user.name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                    {{ user.email }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right text-sm">
                                    <span
                                        :class="
                                            isExpiringSoon(user.plan_ends_at)
                                                ? 'font-medium text-red-600'
                                                : 'text-amber-600'
                                        "
                                    >
                                        {{ user.plan_ends_at }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="expiringUsers.length === 0">
                                <td
                                    colspan="3"
                                    class="px-4 py-4 text-center text-sm text-gray-500"
                                >
                                    No users expiring soon.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { formatCurrency, formatDate } from '@/utils';

defineProps<{
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
        user: { name: string } | null;
        plan: string;
        amount: string;
        created_at: string;
    }[];
    expiringUsers: {
        id: number;
        name: string;
        email: string;
        plan_ends_at: string;
    }[];
}>();

function isExpiringSoon(date: string): boolean {
    const diff = new Date(date).getTime() - Date.now();
    return diff < 3 * 24 * 60 * 60 * 1000;
}
</script>
