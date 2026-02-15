<template>
    <Head title="Invoices" />

    <template v-if="orders.length > 0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-0"
                        >
                            Invoice Number
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Description
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Plan Starts At
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Plan Ends At
                        </th>
                        <th
                            scope="col"
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Amount
                        </th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
                            <span class="sr-only">Download</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="order in orders" :key="order.id">
                        <td
                            class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-0"
                        >
                            {{ order.invoice_number }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ order.description }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ order.plan_starts_at }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ order.plan_ends_at }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            ₹{{ order.amount }}
                        </td>
                        <td
                            class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0"
                        >
                            <a
                                :href="`/invoices/${order.id}/download`"
                                target="_blank"
                                class="text-purple-600 underline hover:text-purple-900"
                            >
                                Download Invoice
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </template>
    <template v-else>
        <p>No Invoices found.</p>
    </template>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface Order {
    id: number;
    invoice_number: number;
    description: string;
    plan_starts_at: string;
    plan_ends_at: string;
    amount: number;
}

defineProps<{
    orders: Order[];
}>();
</script>
