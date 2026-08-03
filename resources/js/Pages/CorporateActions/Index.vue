<template>
    <div>
        <Head title="Corporate Actions" />
        <PageHeader description="Dividends, bonuses, splits and other corporate actions announced by NSE-listed companies.">
            Corporate Actions
        </PageHeader>

        <div class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
            <SymbolSearchInput v-model="search" label="Symbol" name="search" placeholder="e.g. ITC" search-url="/corporate-actions/search" />
            <SelectInput v-model="type" label="Type" name="type" :options="typeOptions" />
        </div>

        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900">Date</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Symbol</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Type</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Description</th>
                        <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Ratio</th>
                        <th class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Dividend (&#8377;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="action in corporateActions.data" :key="action.id">
                        <td class="py-4 pr-3 pl-4 text-sm whitespace-nowrap text-gray-900">
                            {{ formatDate(action.date) }}
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                            <Link :href="`/instruments/${action.symbol}`" class="font-medium text-purple-600 hover:text-purple-900">
                                {{ action.symbol }}
                            </Link>
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap">
                            <span
                                v-if="action.type"
                                :class="[
                                    typePillClasses[action.type] ?? 'bg-gray-100 text-gray-700',
                                    'inline-flex rounded-full px-2 text-xs leading-5 font-semibold capitalize',
                                ]"
                            >
                                {{ action.type }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </td>
                        <td class="max-w-md truncate px-3 py-4 text-sm text-gray-500" :title="action.description ?? undefined">
                            {{ action.description ?? '-' }}
                        </td>
                        <td class="px-3 py-4 text-sm whitespace-nowrap text-gray-500">
                            {{ action.ratio ?? '-' }}
                        </td>
                        <td class="px-3 py-4 text-right text-sm whitespace-nowrap text-gray-500">
                            {{ action.dividend ?? '-' }}
                        </td>
                    </tr>
                    <tr v-if="corporateActions.data.length === 0">
                        <td colspan="6" class="py-4 text-center text-sm text-gray-500">No corporate actions found.</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="corporateActions.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/Components/PageHeader.vue';
import SymbolSearchInput from '@/Components/Form/SymbolSearchInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import type { BacktestNseCorporateAction } from '@/types/app/Models/BacktestNseCorporateAction';
import type { SelectOption } from '@/types/SelectOption';

type PublicCorporateAction = Pick<BacktestNseCorporateAction, 'id' | 'date' | 'symbol' | 'type' | 'description' | 'ratio' | 'dividend'>;

const props = defineProps<{
    corporateActions: {
        data: PublicCorporateAction[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search?: string | null;
        type?: string | null;
    };
    types: SelectOption[];
}>();

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? '');

const typeOptions = computed<SelectOption[]>(() => [{ id: '', name: 'All Types' }, ...props.types]);

const typePillClasses: Record<string, string> = {
    dividend: 'bg-green-100 text-green-700',
    bonus: 'bg-purple-100 text-purple-700',
    split: 'bg-blue-100 text-blue-700',
    rights: 'bg-amber-100 text-amber-700',
    demerger: 'bg-rose-100 text-rose-700',
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function reload() {
    const params: Record<string, string> = {};

    if (search.value) {
        params.search = search.value;
    }

    if (type.value) {
        params.type = type.value;
    }

    router.get('/corporate-actions', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['corporateActions', 'filters'],
    });
}

watch(search, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(reload, 300);
});

watch(type, () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    reload();
});
</script>
