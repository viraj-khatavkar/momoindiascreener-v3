<template>
    <div class="space-y-8">
        <Head title="Orders" />

        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-purple-700">Revenue</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Orders</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Review paid orders and revenue for the selected period.</p>
            </div>
            <a
                :href="downloadUrl"
                class="inline-flex min-h-11 w-fit items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:border-slate-400 hover:bg-slate-50"
            >
                <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                Export current view
            </a>
        </header>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4" aria-label="Order summary">
            <article
                class="relative col-span-2 overflow-hidden rounded-2xl border border-purple-200 bg-purple-50 p-5 text-slate-950 shadow-sm sm:col-span-1"
            >
                <div class="absolute -top-12 -right-8 size-32 rounded-full bg-purple-200/50" aria-hidden="true" />
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-purple-700">Paid revenue</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight">
                            {{ formatCurrency(totalRevenue) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white text-purple-700 ring-1 ring-purple-100">
                        <BanknotesIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="relative mt-5 truncate text-xs text-slate-600">{{ periodLabel }}</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Paid orders</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {{ formatNumber(count) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                        <ShoppingBagIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-5 hidden text-xs text-slate-500 sm:block">Completed payments in this view</p>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Average order</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                            {{ formatCurrency(averageOrderValue) }}
                        </p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                        <ChartBarIcon class="size-5" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-5 hidden text-xs text-slate-500 sm:block">Revenue divided by paid orders</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="order-filters-heading">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="order-filters-heading" class="text-sm font-bold text-slate-950">Date range</h2>
                    <p class="text-xs text-slate-500">Change the period or find a customer.</p>
                </div>
                <Link href="/admin/orders" class="mt-2 w-fit text-xs font-semibold text-purple-700 hover:text-purple-900 sm:mt-0" preserve-state>
                    Reset to this month
                </Link>
            </div>

            <form
                class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_minmax(16rem,1.4fr)_auto] xl:items-end"
                @submit.prevent="applyFilter"
            >
                <TextInput v-model="form.from_date" type="date" label="From" name="from_date" />
                <TextInput v-model="form.to_date" type="date" label="To" name="to_date" />
                <div class="relative sm:col-span-2 xl:col-span-1">
                    <label for="order-search" class="block text-sm/6 font-medium text-slate-900"> Customer </label>
                    <div class="relative mt-2">
                        <MagnifyingGlassIcon
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            aria-hidden="true"
                        />
                        <input
                            id="order-search"
                            v-model="form.search"
                            name="search"
                            type="search"
                            autocomplete="off"
                            placeholder="Name or email"
                            class="block min-h-9 w-full rounded-md bg-white py-1.5 pr-3 pl-9 text-base text-slate-900 outline-1 -outline-offset-1 outline-slate-300 placeholder:text-slate-400 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                        />
                    </div>
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-xl bg-purple-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60 sm:col-span-2 xl:col-span-1"
                >
                    {{ form.processing ? 'Updating…' : 'Apply' }}
                </button>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="order-results-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 id="order-results-heading" class="font-bold text-slate-950">Order list</h2>
                        <span v-if="form.processing" class="inline-flex items-center gap-2 text-xs font-semibold text-purple-700">
                            <span class="size-2 animate-pulse rounded-full bg-purple-600" />
                            Updating
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-slate-500">{{ resultSummary }}</p>
                </div>

                <div class="flex flex-wrap gap-2" aria-label="Orders by plan">
                    <span
                        v-for="plan in planMix"
                        :key="plan.name"
                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600"
                    >
                        <span class="size-1.5 rounded-full" :class="plan.dotClass" />
                        <span class="font-medium">{{ plan.label }}</span>
                        <span class="font-bold text-slate-900">{{ formatNumber(plan.count) }}</span>
                    </span>
                </div>
            </div>

            <div v-if="orders.data.length">
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full">
                        <thead class="bg-slate-50/80">
                            <tr class="border-b border-slate-200">
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Order</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Plan</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="order in orders.data" :key="order.id" class="transition-colors hover:bg-slate-50/70">
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ formatDate(order.created_at) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ invoiceLabel(order.invoice_number) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span
                                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-purple-50 text-xs font-bold text-purple-700"
                                        >
                                            {{ initials(order.user?.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900">
                                                {{ order.user?.name ?? 'Deleted user' }}
                                            </p>
                                            <p class="truncate text-sm text-slate-500">
                                                {{ order.user?.email ?? 'No email available' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="planBadgeClass(order.plan)"
                                    >
                                        {{ order.plan }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-bold text-slate-950">
                                    {{ formatMoney(order.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 md:hidden">
                    <article v-for="order in orders.data" :key="order.id" class="p-5">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-purple-50 text-xs font-bold text-purple-700"
                            >
                                {{ initials(order.user?.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ order.user?.name ?? 'Deleted user' }}
                                </p>
                                <p class="truncate text-sm text-slate-500">
                                    {{ order.user?.email ?? 'No email available' }}
                                </p>
                            </div>
                            <p class="shrink-0 text-sm font-bold text-slate-950">
                                {{ formatMoney(order.amount) }}
                            </p>
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                            <div>
                                <p class="text-xs font-medium text-slate-500">
                                    {{ invoiceLabel(order.invoice_number) }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ formatDate(order.created_at) }}
                                </p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="planBadgeClass(order.plan)">
                                {{ order.plan }}
                            </span>
                        </div>
                    </article>
                </div>
            </div>

            <div v-else class="px-6 py-16 text-center">
                <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <ShoppingBagIcon class="size-6" aria-hidden="true" />
                </span>
                <h3 class="mt-3 text-sm font-semibold text-slate-900">No orders found</h3>
                <p class="mt-1 text-sm text-slate-500">Try a different customer or date range.</p>
                <Link href="/admin/orders" class="mt-4 inline-block text-sm font-semibold text-purple-700 hover:text-purple-900" preserve-state>
                    Reset to this month
                </Link>
            </div>

            <div v-if="orders.data.length" class="border-t border-slate-200 px-5 pb-5 sm:px-6">
                <Pagination :links="orders.links" />
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { ArrowDownTrayIcon, BanknotesIcon, ChartBarIcon, MagnifyingGlassIcon, ShoppingBagIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatCurrency, formatDate } from '@/utils';

interface Order {
    id: number;
    created_at: string;
    user: { name: string; email: string } | null;
    invoice_number: number | string | null;
    plan: string;
    amount: string | number;
}

interface PaginatedOrders {
    data: Order[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
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

const downloadUrl = computed(() => `/admin/orders/download?${filterQueryString.value}`);

const averageOrderValue = computed(() => (props.count > 0 ? Number(props.totalRevenue) / props.count : 0));

const periodLabel = computed(() => `${formatDate(props.filters.from_date)} to ${formatDate(props.filters.to_date)}`);

const resultSummary = computed(() => {
    if (!props.orders.total || props.orders.from === null || props.orders.to === null) {
        return 'No matching orders';
    }

    return `${formatNumber(props.orders.from)}–${formatNumber(props.orders.to)} of ${formatNumber(props.orders.total)} orders`;
});

const planMix = computed(() => [
    {
        name: 'monthly',
        label: 'Monthly',
        count: props.planCounts.monthly,
        dotClass: 'bg-blue-500',
    },
    {
        name: 'yearly',
        label: 'Yearly',
        count: props.planCounts.yearly,
        dotClass: 'bg-emerald-500',
    },
    {
        name: 'forever',
        label: 'Forever',
        count: props.planCounts.forever,
        dotClass: 'bg-purple-500',
    },
    {
        name: 'newsletter',
        label: 'Newsletter',
        count: props.planCounts.newsletter,
        dotClass: 'bg-amber-500',
    },
]);

function formatMoney(value: string | number): string {
    return formatCurrency(Number(value));
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
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

function invoiceLabel(invoiceNumber: number | string | null): string {
    return invoiceNumber ? `Invoice #${invoiceNumber}` : 'No invoice number';
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

function applyFilter(): void {
    form.get('/admin/orders', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>
