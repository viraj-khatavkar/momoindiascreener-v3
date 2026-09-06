<template>
    <div>
        <Head title="Corporate Actions" />
        <div class="flex items-start justify-between">
            <PageHeader description="Manage backtest corporate actions."> Corporate Actions </PageHeader>
            <Link
                href="/admin/corporate-actions/create"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                New Corporate Action
            </Link>
        </div>

        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput v-model="form.search" label="Search (symbol)" name="search" placeholder="Search by symbol..." />
                <SelectInput v-model="form.type" label="Type" name="type" :options="typeOptions" />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Filter
            </button>
        </form>

        <div class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-testid="corporate-actions-list">
            <template v-if="corporateActions.data.length">
                <div class="hidden lg:block">
                    <table class="w-full table-fixed" data-testid="corporate-actions-table">
                        <caption class="sr-only">
                            Corporate actions in verification order
                        </caption>
                        <thead class="bg-slate-50">
                            <tr class="border-b border-slate-200">
                                <th scope="col" class="w-36 px-4 py-3 text-left text-xs font-semibold text-slate-600 xl:w-44">Corporate action</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-semibold text-slate-600">Statement</th>
                                <th
                                    scope="col"
                                    class="w-22 border-l border-slate-200 px-3 py-3 text-right text-xs font-semibold text-slate-600 xl:w-28"
                                >
                                    Dividend amount
                                </th>
                                <th
                                    scope="col"
                                    class="w-28 border-l border-slate-200 px-3 py-3 text-right text-xs font-semibold text-slate-600 xl:w-40"
                                >
                                    Dividend factor
                                </th>
                                <th
                                    scope="col"
                                    class="w-28 border-l border-slate-200 px-3 py-3 text-right text-xs font-semibold text-slate-600 xl:w-40"
                                >
                                    Price factor
                                </th>
                                <th
                                    scope="col"
                                    class="w-24 border-l border-slate-200 px-3 py-3 text-left text-xs font-semibold text-slate-600 xl:w-32"
                                >
                                    Applied
                                </th>
                                <th scope="col" class="w-20 px-3 py-3 xl:w-24">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="action in corporateActions.data" :key="action.id" class="transition-colors hover:bg-slate-50/70">
                                <td class="px-4 py-3 align-middle">
                                    <span data-testid="corporate-action-symbol" class="block text-sm font-semibold break-words text-slate-950">
                                        {{ action.symbol }}
                                    </span>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                                        <span v-if="action.type" class="font-medium text-purple-700 capitalize">{{ action.type }}</span>
                                        <span class="text-slate-500">{{ formatDate(action.date) }}</span>
                                        <span v-if="action.series" class="hidden text-slate-500 xl:inline">· {{ action.series }}</span>
                                    </div>
                                </td>
                                <td class="min-w-0 px-3 py-3 align-middle">
                                    <p class="truncate text-sm text-slate-700" :title="action.description ?? undefined">
                                        {{ action.description ?? '—' }}
                                    </p>
                                    <p v-if="action.ratio" class="mt-1 truncate text-xs text-slate-500">
                                        Ratio <span class="font-medium text-slate-700">{{ action.ratio }}</span>
                                    </p>
                                </td>
                                <td
                                    data-testid="corporate-action-dividend"
                                    class="border-l border-slate-100 px-3 py-3 text-right align-middle font-mono text-sm font-semibold tabular-nums"
                                    :class="action.dividend ? 'text-slate-950' : 'text-slate-300'"
                                >
                                    {{ action.dividend ?? '—' }}
                                </td>
                                <td
                                    data-testid="corporate-action-dividend-factor"
                                    class="overflow-hidden border-l border-slate-100 px-3 py-3 text-right align-middle font-mono text-[0.8125rem] font-semibold tabular-nums"
                                    :class="action.dividend_adjustment_factor ? 'text-slate-950' : 'text-slate-300'"
                                >
                                    <span
                                        data-testid="corporate-action-dividend-factor-value"
                                        class="block max-w-full truncate"
                                        :title="action.dividend_adjustment_factor ?? undefined"
                                    >
                                        {{ action.dividend_adjustment_factor ?? '—' }}
                                    </span>
                                </td>
                                <td
                                    data-testid="corporate-action-price-factor"
                                    class="overflow-hidden border-l border-slate-100 px-3 py-3 text-right align-middle font-mono text-[0.8125rem] font-semibold tabular-nums"
                                    :class="action.price_adjustment_factor ? 'text-slate-950' : 'text-slate-300'"
                                >
                                    <span
                                        data-testid="corporate-action-price-factor-value"
                                        class="block max-w-full truncate"
                                        :title="action.price_adjustment_factor ?? undefined"
                                    >
                                        {{ action.price_adjustment_factor ?? '—' }}
                                    </span>
                                </td>
                                <td data-testid="corporate-action-applied" class="border-l border-slate-100 px-3 py-3 align-middle">
                                    <div class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1.5 text-xs font-medium"
                                            :class="action.dividend_adjustment_applied_at ? 'text-emerald-700' : 'text-slate-400'"
                                        >
                                            <span
                                                class="size-1.5 shrink-0 rounded-full"
                                                :class="action.dividend_adjustment_applied_at ? 'bg-emerald-500' : 'bg-slate-300'"
                                                aria-hidden="true"
                                            />
                                            Dividend
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5 text-xs font-medium"
                                            :class="action.price_adjustment_applied_at ? 'text-emerald-700' : 'text-slate-400'"
                                        >
                                            <span
                                                class="size-1.5 shrink-0 rounded-full"
                                                :class="action.price_adjustment_applied_at ? 'bg-emerald-500' : 'bg-slate-300'"
                                                aria-hidden="true"
                                            />
                                            Price
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link
                                            :href="`/admin/corporate-actions/${action.id}/edit`"
                                            data-testid="corporate-action-edit"
                                            class="inline-flex min-h-8 items-center rounded-md px-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50 hover:text-purple-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-rose-600 hover:bg-rose-50 hover:text-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600"
                                            :aria-label="`Delete corporate action for ${action.symbol}`"
                                            @click="destroy(action)"
                                        >
                                            <TrashIcon class="size-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="divide-y divide-slate-100 lg:hidden">
                    <article v-for="action in corporateActions.data" :key="action.id" class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 data-testid="corporate-action-symbol" class="text-sm font-semibold break-words text-slate-950">
                                    {{ action.symbol }}
                                </h2>
                                <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                                    <span v-if="action.type" class="font-medium text-purple-700 capitalize">{{ action.type }}</span>
                                    <span class="text-slate-500">{{ formatDate(action.date) }}</span>
                                    <span v-if="action.series" class="text-slate-500">· {{ action.series }}</span>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <Link
                                    :href="`/admin/corporate-actions/${action.id}/edit`"
                                    data-testid="corporate-action-edit"
                                    class="inline-flex min-h-9 items-center rounded-md px-2 text-sm font-semibold text-purple-700 hover:bg-purple-50 hover:text-purple-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-md text-rose-600 hover:bg-rose-50 hover:text-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600"
                                    :aria-label="`Delete corporate action for ${action.symbol}`"
                                    @click="destroy(action)"
                                >
                                    <TrashIcon class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <p class="text-[0.6875rem] font-semibold tracking-wide text-slate-500 uppercase">Statement</p>
                            <p class="mt-1 text-sm leading-5 text-slate-700">{{ action.description ?? '—' }}</p>
                            <p v-if="action.ratio" class="mt-1 text-xs text-slate-500">
                                Ratio <span class="font-medium text-slate-700">{{ action.ratio }}</span>
                            </p>
                        </div>

                        <dl class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                            <div data-testid="corporate-action-dividend" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-3 py-2.5">
                                <dt class="text-xs font-medium text-slate-500">Dividend amount</dt>
                                <dd
                                    class="font-mono text-sm font-semibold tabular-nums"
                                    :class="action.dividend ? 'text-slate-950' : 'text-slate-300'"
                                >
                                    {{ action.dividend ?? '—' }}
                                </dd>
                            </div>
                            <div
                                data-testid="corporate-action-dividend-factor"
                                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-3 py-2.5"
                            >
                                <dt class="text-xs font-medium text-slate-500">Dividend factor</dt>
                                <dd
                                    data-testid="corporate-action-dividend-factor-value"
                                    class="max-w-44 truncate font-mono text-sm font-semibold tabular-nums"
                                    :class="action.dividend_adjustment_factor ? 'text-slate-950' : 'text-slate-300'"
                                    :title="action.dividend_adjustment_factor ?? undefined"
                                >
                                    {{ action.dividend_adjustment_factor ?? '—' }}
                                </dd>
                            </div>
                            <div
                                data-testid="corporate-action-price-factor"
                                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-3 py-2.5"
                            >
                                <dt class="text-xs font-medium text-slate-500">Price factor</dt>
                                <dd
                                    data-testid="corporate-action-price-factor-value"
                                    class="max-w-44 truncate font-mono text-sm font-semibold tabular-nums"
                                    :class="action.price_adjustment_factor ? 'text-slate-950' : 'text-slate-300'"
                                    :title="action.price_adjustment_factor ?? undefined"
                                >
                                    {{ action.price_adjustment_factor ?? '—' }}
                                </dd>
                            </div>
                            <div data-testid="corporate-action-applied" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-3 py-2.5">
                                <dt class="text-xs font-medium text-slate-500">Applied</dt>
                                <dd class="flex items-center gap-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium"
                                        :class="action.dividend_adjustment_applied_at ? 'text-emerald-700' : 'text-slate-400'"
                                    >
                                        <span
                                            class="size-1.5 shrink-0 rounded-full"
                                            :class="action.dividend_adjustment_applied_at ? 'bg-emerald-500' : 'bg-slate-300'"
                                            aria-hidden="true"
                                        />
                                        Dividend
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-medium"
                                        :class="action.price_adjustment_applied_at ? 'text-emerald-700' : 'text-slate-400'"
                                    >
                                        <span
                                            class="size-1.5 shrink-0 rounded-full"
                                            :class="action.price_adjustment_applied_at ? 'bg-emerald-500' : 'bg-slate-300'"
                                            aria-hidden="true"
                                        />
                                        Price
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </article>
                </div>
            </template>

            <div v-else class="px-6 py-14 text-center">
                <p class="text-sm font-semibold text-slate-900">No corporate actions found</p>
                <p class="mt-1 text-sm text-slate-500">Try a different symbol or type.</p>
            </div>

            <div v-if="corporateActions.data.length" class="border-t border-slate-200 px-5 pb-5 sm:px-6">
                <Pagination :links="corporateActions.links" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { TrashIcon } from '@heroicons/vue/24/outline';
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
