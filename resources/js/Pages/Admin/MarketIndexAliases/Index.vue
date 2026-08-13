<template>
    <div>
        <Head title="ETF Index Mappings" />
        <PageHeader description="Review index labels found in NSE ETF files."> ETF Index Mappings </PageHeader>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-amber-50 p-4 ring-1 ring-amber-200">
                <p class="text-sm font-medium text-amber-700">Pending</p>
                <p class="mt-1 text-2xl font-semibold text-amber-900">
                    {{ statusCount('pending') }}
                </p>
            </div>
            <div class="rounded-lg bg-green-50 p-4 ring-1 ring-green-200">
                <p class="text-sm font-medium text-green-700">Approved</p>
                <p class="mt-1 text-2xl font-semibold text-green-900">
                    {{ statusCount('approved') }}
                </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200">
                <p class="text-sm font-medium text-gray-600">Ignored</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">
                    {{ statusCount('ignored') }}
                </p>
            </div>
        </div>

        <form class="mt-8" @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput v-model="filterForm.search" label="Search" name="search" placeholder="Label, symbol, or slug" />
                <SelectInput v-model="filterForm.status" label="Status" name="status" :options="statusOptions" />
            </div>
            <button
                type="submit"
                :disabled="filterForm.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Filter
            </button>
        </form>

        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900">Source label</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Sample ETF</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Seen</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Index</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">ETFs</th>
                        <th class="relative py-3.5 pr-4 pl-3">
                            <span class="sr-only">Review</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template v-for="alias in marketIndexAliases.data" :key="alias.id">
                        <tr>
                            <td class="max-w-sm py-4 pr-3 pl-4 text-sm">
                                <p class="font-medium text-gray-900">
                                    {{ alias.source_label }}
                                </p>
                                <p class="mt-1 truncate text-xs text-gray-500">
                                    {{ alias.normalized_label }}
                                </p>
                            </td>
                            <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-600">
                                {{ alias.sample_symbol ?? '-' }}
                            </td>
                            <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-600">
                                <p>{{ displayDate(alias.first_seen_on) }}</p>
                                <p v-if="alias.last_seen_on !== alias.first_seen_on" class="text-xs text-gray-500">
                                    to {{ displayDate(alias.last_seen_on) }}
                                </p>
                            </td>
                            <td class="max-w-xs px-3 py-4 text-sm">
                                <div v-if="alias.market_index">
                                    <p class="font-medium text-gray-900">
                                        {{ alias.market_index.name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ alias.market_index.slug }}
                                    </p>
                                </div>
                                <div v-else-if="alias.suggested_market_index">
                                    <p class="text-amber-700">Suggested: {{ alias.suggested_market_index.name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ alias.suggested_market_index.slug }}
                                    </p>
                                </div>
                                <p v-else class="text-gray-500">Suggested slug: {{ alias.suggested_slug ?? '-' }}</p>
                            </td>
                            <td class="px-3 py-4 text-sm whitespace-nowrap">
                                <span :class="[statusClasses(alias.status), 'inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize']">
                                    {{ alias.status }}
                                </span>
                                <p v-if="alias.reviewer" class="mt-1 text-xs text-gray-500">
                                    {{ alias.reviewer.name }}
                                </p>
                            </td>
                            <td class="px-3 py-4 text-right text-sm whitespace-nowrap text-gray-600">
                                {{ alias.instruments_count }}
                            </td>
                            <td class="py-4 pr-4 pl-3 text-right text-sm font-medium whitespace-nowrap">
                                <button type="button" class="cursor-pointer text-purple-600 hover:text-purple-900" @click="startReview(alias)">
                                    {{ alias.status === 'pending' ? 'Review' : 'Edit' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="reviewingAliasId === alias.id" class="bg-purple-50/50">
                            <td colspan="7" class="px-4 py-6">
                                <form class="grid gap-6" @submit.prevent="saveReview(alias)">
                                    <fieldset>
                                        <legend class="text-sm font-semibold text-gray-900">Select an action</legend>
                                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:gap-6">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                                <input
                                                    v-model="reviewForm.action"
                                                    type="radio"
                                                    value="link"
                                                    class="size-4 border-gray-300 text-purple-600 focus:ring-purple-600"
                                                />
                                                Link to an existing index
                                            </label>
                                            <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                                                <input
                                                    v-model="reviewForm.action"
                                                    type="radio"
                                                    value="create"
                                                    class="size-4 border-gray-300 text-purple-600 focus:ring-purple-600"
                                                    @change="prepareNewIndex(alias)"
                                                />
                                                Create a new index
                                            </label>
                                        </div>
                                    </fieldset>

                                    <div v-if="reviewForm.action === 'link'" class="max-w-2xl">
                                        <label :for="`market-index-${alias.id}`" class="block text-sm/6 font-medium text-gray-900"> Index </label>
                                        <select
                                            :id="`market-index-${alias.id}`"
                                            v-model="reviewForm.market_index_id"
                                            class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-purple-600 sm:text-sm/6"
                                        >
                                            <option :value="null" disabled>Select an index</option>
                                            <option v-for="marketIndex in marketIndices" :key="marketIndex.id" :value="marketIndex.id">
                                                {{ marketIndex.name }} — {{ marketIndex.slug }}
                                            </option>
                                        </select>
                                        <p v-if="reviewForm.errors.market_index_id" class="mt-2 text-sm text-red-600">
                                            {{ reviewForm.errors.market_index_id }}
                                        </p>
                                    </div>

                                    <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                                        <TextInput v-model="reviewForm.name" label="Index name" name="name" :error="reviewForm.errors.name" />
                                        <TextInput v-model="reviewForm.slug" label="Index slug" name="slug" :error="reviewForm.errors.slug" />
                                        <TextInput
                                            v-model="reviewForm.provider"
                                            label="Provider"
                                            name="provider"
                                            placeholder="NSE, BSE, S&P, or other"
                                            :error="reviewForm.errors.provider"
                                        />
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <button
                                            type="submit"
                                            :disabled="reviewForm.processing"
                                            class="cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 disabled:cursor-not-allowed disabled:opacity-75"
                                        >
                                            {{ reviewForm.processing ? 'Saving...' : 'Save mapping' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="cursor-pointer rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-gray-300 hover:bg-gray-50"
                                            @click="cancelReview"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="reviewForm.processing"
                                            class="cursor-pointer rounded-md px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-75"
                                            @click="ignoreAlias(alias)"
                                        >
                                            Ignore label
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="marketIndexAliases.data.length === 0">
                        <td colspan="7" class="py-8 text-center text-sm text-gray-500">No ETF index mappings match these filters.</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="marketIndexAliases.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { MarketIndex } from '@/types/app/Models/MarketIndex';
import type { MarketIndexAlias } from '@/types/app/Models/MarketIndexAlias';
import { formatDate } from '@/utils';

const props = defineProps<{
    marketIndexAliases: {
        data: MarketIndexAlias[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    marketIndices: MarketIndex[];
    filters: {
        search: string;
        status: string;
    };
    statusCounts: Record<string, number>;
}>();

const statusOptions = [
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'ignored', name: 'Ignored' },
    { id: 'all', name: 'All' },
];

const filterForm = useForm({
    search: props.filters.search,
    status: props.filters.status,
});

const reviewForm = useForm<{
    action: 'link' | 'create';
    market_index_id: number | null;
    name: string;
    slug: string;
    provider: string;
}>({
    action: 'link',
    market_index_id: null,
    name: '',
    slug: '',
    provider: '',
});

const reviewingAliasId = ref<number | null>(null);

function applyFilter(): void {
    filterForm.get('/admin/market-index-aliases', { preserveState: true });
}

function startReview(alias: MarketIndexAlias): void {
    reviewingAliasId.value = alias.id;
    reviewForm.clearErrors();

    const suggestedMarketIndex = alias.market_index ?? alias.suggested_market_index;

    if (suggestedMarketIndex) {
        reviewForm.action = 'link';
        reviewForm.market_index_id = suggestedMarketIndex.id;
        reviewForm.name = '';
        reviewForm.slug = '';
        reviewForm.provider = '';

        return;
    }

    reviewForm.action = 'create';
    reviewForm.market_index_id = null;
    prepareNewIndex(alias);
}

function prepareNewIndex(alias: MarketIndexAlias): void {
    const suggestedSlug = alias.suggested_slug ?? '';

    reviewForm.market_index_id = null;
    reviewForm.slug = suggestedSlug;
    reviewForm.name = suggestedSlug
        .split('-')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    reviewForm.provider = '';
    reviewForm.clearErrors();
}

function saveReview(alias: MarketIndexAlias): void {
    reviewForm.put(`/admin/market-index-aliases/${alias.id}`, {
        preserveScroll: true,
        onSuccess: cancelReview,
    });
}

function ignoreAlias(alias: MarketIndexAlias): void {
    if (!confirm(`Ignore the index label "${alias.source_label}"?`)) {
        return;
    }

    router.put(
        `/admin/market-index-aliases/${alias.id}`,
        { action: 'ignore' },
        {
            preserveScroll: true,
            onSuccess: cancelReview,
        },
    );
}

function cancelReview(): void {
    reviewingAliasId.value = null;
    reviewForm.reset();
    reviewForm.clearErrors();
}

function displayDate(date: string | null): string {
    return date ? formatDate(date) : '-';
}

function statusCount(status: string): number {
    return Number(props.statusCounts[status] ?? 0);
}

function statusClasses(status: MarketIndexAlias['status']): string {
    return {
        pending: 'bg-amber-100 text-amber-700',
        approved: 'bg-green-100 text-green-700',
        ignored: 'bg-gray-100 text-gray-700',
    }[status];
}
</script>
