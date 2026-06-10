<template>
    <div>
        <Head title="Corporate Actions" />
        <div class="flex items-start justify-between">
            <PageHeader description="Manage backtest corporate actions.">
                Corporate Actions
            </PageHeader>
            <Link
                href="/admin/corporate-actions/create"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                New Corporate Action
            </Link>
        </div>

        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput
                    v-model="form.search"
                    label="Search (symbol)"
                    name="search"
                    placeholder="Search by symbol..."
                />
                <SelectInput
                    v-model="form.type"
                    label="Type"
                    name="type"
                    :options="typeOptions"
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

        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">
                            Date
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Symbol
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Type
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Description
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Ratio
                        </th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                            Dividend
                        </th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                            Div Factor
                        </th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                            Price Factor
                        </th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Applied
                        </th>
                        <th class="relative py-3.5 pl-3 pr-4">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="action in corporateActions.data" :key="action.id">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                            {{ formatDate(action.date) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                            {{ action.symbol }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                v-if="action.type"
                                class="inline-flex rounded-full bg-purple-100 px-2 text-xs font-semibold capitalize leading-5 text-purple-700"
                            >
                                {{ action.type }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="max-w-md truncate px-3 py-4 text-sm text-gray-500" :title="action.description ?? undefined">
                            {{ action.description ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ action.ratio ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-right text-sm text-gray-500">
                            {{ action.dividend ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-right text-sm text-gray-500">
                            {{ action.dividend_adjustment_factor ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-right text-sm text-gray-500">
                            {{ action.price_adjustment_factor ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                v-if="action.dividend_adjustment_applied_at"
                                class="mr-1 inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-700"
                            >
                                Div
                            </span>
                            <span
                                v-if="action.price_adjustment_applied_at"
                                class="inline-flex rounded-full bg-green-100 px-2 text-xs font-semibold leading-5 text-green-700"
                            >
                                Price
                            </span>
                            <span
                                v-if="!action.dividend_adjustment_applied_at && !action.price_adjustment_applied_at"
                                class="text-gray-400"
                            >
                                -
                            </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium">
                            <Link
                                :href="`/admin/corporate-actions/${action.id}/edit`"
                                class="text-purple-600 hover:text-purple-900"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                class="ml-4 cursor-pointer text-red-600 hover:text-red-900"
                                @click="destroy(action)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="corporateActions.data.length === 0">
                        <td colspan="10" class="py-4 text-center text-sm text-gray-500">
                            No corporate actions found.
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="corporateActions.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import type { BacktestNseCorporateAction } from '@/types/app/Models/BacktestNseCorporateAction';

const props = defineProps<{
    corporateActions: {
        data: BacktestNseCorporateAction[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
        type: string | null;
    };
    types: string[];
}>();

const typeOptions = computed(() => [
    { id: '', name: 'All' },
    ...props.types.map((type) => ({
        id: type,
        name: type.charAt(0).toUpperCase() + type.slice(1),
    })),
]);

const form = useForm({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
});

function applyFilter() {
    form.get('/admin/corporate-actions', { preserveState: true });
}

function destroy(action: BacktestNseCorporateAction) {
    if (confirm(`Delete the ${action.type ?? 'corporate'} action for ${action.symbol} on ${formatDate(action.date)}?`)) {
        router.delete(`/admin/corporate-actions/${action.id}`, { preserveScroll: true });
    }
}
</script>
