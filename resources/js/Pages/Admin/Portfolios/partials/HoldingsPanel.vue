<template>
    <section aria-labelledby="holdings-title" class="@container min-w-0 rounded-xl border border-slate-200 bg-white shadow-xs">
        <div
            class="grid grid-cols-[1fr_auto] items-center gap-3 rounded-t-xl bg-white px-4 py-4 @xl:flex @xl:px-5 @2xl:sticky @2xl:top-16 @2xl:z-20 @2xl:h-20"
        >
            <div class="min-w-0 @xl:mr-auto">
                <h2 id="holdings-title" class="text-lg font-semibold tracking-tight text-slate-950">Holdings</h2>
                <p v-if="holdings.length" role="status" class="mt-0.5 text-xs text-slate-600">
                    <template v-if="search.trim()"
                        >{{ visibleHoldings.length }} of {{ holdings.length }} {{ holdings.length === 1 ? 'entry' : 'entries' }}</template
                    >
                    <template v-else>
                        {{ stockCount }} {{ stockCount === 1 ? 'stock' : 'stocks'
                        }}<span v-if="holdings.length !== stockCount"> · {{ holdings.length }} purchase entries</span>
                    </template>
                </p>
            </div>
            <div v-if="holdings.length" class="relative col-span-2 row-start-2 min-w-0 @xl:w-64">
                <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" aria-hidden="true" />
                <input
                    ref="searchInput"
                    v-model="search"
                    type="search"
                    name="holding-search"
                    aria-label="Search holdings by symbol or company"
                    placeholder="Search symbol or company"
                    class="h-11 w-full rounded-lg border border-slate-300 bg-white pr-10 pl-9 text-base text-slate-900 placeholder:text-slate-500 focus:outline-purple-600 @xl:h-10 @xl:text-sm [&::-webkit-search-cancel-button]:appearance-none"
                />
                <button
                    v-if="search"
                    type="button"
                    aria-label="Clear search"
                    title="Clear search"
                    class="absolute inset-y-0 right-0 grid w-10 cursor-pointer place-items-center rounded-r-lg text-slate-500 hover:text-slate-900"
                    @click="clearSearch"
                >
                    <XMarkIcon class="size-4" aria-hidden="true" />
                </button>
            </div>
            <button
                ref="addButton"
                type="button"
                class="col-start-2 row-start-1 inline-flex min-h-11 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-purple-700 px-3.5 text-sm font-semibold text-white hover:bg-purple-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-700 @xl:min-h-10"
                @click="openEditor(null, $event)"
            >
                <PlusIcon class="size-4" aria-hidden="true" /> Add holding
            </button>
        </div>

        <table v-if="visibleHoldings.length" aria-labelledby="holdings-title" class="block w-full text-left text-sm @2xl:table @2xl:table-fixed">
            <thead
                class="hidden bg-slate-50 text-xs font-medium text-slate-600 @2xl:sticky @2xl:top-36 @2xl:z-10 @2xl:table-header-group @2xl:shadow-[0_-1px_var(--color-slate-200),0_1px_var(--color-slate-200)]"
            >
                <tr class="border-y border-slate-200">
                    <th scope="col" class="w-[25%] py-2.5 pr-2 pl-5 font-medium">Instrument</th>
                    <th scope="col" class="px-2 py-2.5 text-right font-medium">Quantity</th>
                    <th scope="col" class="hidden px-2 py-2.5 text-right font-medium @4xl:table-cell">Buy price</th>
                    <th scope="col" class="px-2 py-2.5 text-right font-medium">Market price</th>
                    <th scope="col" class="px-2 py-2.5 text-right font-medium">Market value</th>
                    <th scope="col" class="px-2 py-2.5 text-right font-medium">Profit / loss</th>
                    <th scope="col" class="w-14"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="block divide-y divide-slate-200 border-t border-slate-200 @2xl:table-row-group @2xl:border-0">
                <tr
                    v-for="holding in visibleHoldings"
                    :key="holding.id"
                    :data-test="'holding-' + holding.id"
                    class="grid grid-cols-2 items-start gap-x-4 gap-y-3 px-4 py-4 @2xl:table-row @2xl:hover:bg-slate-50/70"
                >
                    <th
                        scope="row"
                        class="col-span-2 col-start-1 row-start-1 min-w-0 pr-12 text-left align-middle font-normal @2xl:py-3 @2xl:pr-2 @2xl:pl-5"
                    >
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <Link
                                :href="'/instruments/' + encodeURIComponent(holding.symbol)"
                                class="rounded-sm font-semibold wrap-anywhere text-slate-950 hover:text-purple-700 hover:underline focus-visible:outline-purple-600"
                                >{{ holding.symbol }}</Link
                            >
                            <span
                                v-if="holding.series"
                                class="rounded px-1.5 text-[0.625rem]/4 font-medium"
                                :class="holding.series === 'BE' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'"
                                >{{ holding.series }}</span
                            >
                        </div>
                        <p v-if="holding.name" class="mt-0.5 text-xs/5 wrap-anywhere text-slate-500">{{ holding.name }}</p>
                    </th>
                    <td class="col-start-1 row-start-3 min-w-0 align-middle tabular-nums @2xl:px-2 @2xl:py-3 @2xl:text-right">
                        <span class="block text-xs text-slate-500 @2xl:hidden">Quantity</span>
                        <span class="text-slate-800">{{ holding.quantity.toLocaleString('en-IN') }}</span>
                        <span class="block text-xs/5 wrap-anywhere text-slate-600 @4xl:hidden">Buy {{ formatCurrency(holding.price) }}</span>
                    </td>
                    <td class="hidden px-2 py-3 text-right align-middle wrap-anywhere text-slate-800 tabular-nums @4xl:table-cell">
                        {{ formatCurrency(holding.price) }}
                    </td>
                    <td class="col-start-2 row-start-3 min-w-0 align-middle tabular-nums @2xl:px-2 @2xl:py-3 @2xl:text-right">
                        <span class="block text-xs text-slate-500 @2xl:hidden">Market price</span>
                        <span :title="holding.priceDate ?? undefined" class="wrap-anywhere text-slate-800">{{
                            holding.close === null ? '—' : formatCurrency(holding.close)
                        }}</span>
                        <span
                            v-if="holding.priceDate !== date"
                            class="block text-xs/5"
                            :class="holding.earlierPrice || !holding.priceDate ? 'font-medium text-amber-800' : 'text-slate-500'"
                        >
                            <span v-if="holding.earlierPrice">Earlier price · </span>{{ holding.priceDate ?? 'No price' }}
                        </span>
                    </td>
                    <td class="col-start-1 row-start-2 min-w-0 align-middle @2xl:px-2 @2xl:py-3 @2xl:text-right">
                        <span class="block text-xs text-slate-500 @2xl:hidden">Market value</span>
                        <span class="font-semibold wrap-anywhere text-slate-900 tabular-nums">{{
                            holding.value === null ? '—' : formatCurrency(holding.value)
                        }}</span>
                    </td>
                    <td class="col-start-2 row-start-2 min-w-0 align-middle @2xl:px-2 @2xl:py-3 @2xl:text-right">
                        <span class="block text-xs text-slate-500 @2xl:hidden">Profit / loss</span>
                        <ProfitLoss :value="holding.profitLoss" :percent-change="holding.percentChange" />
                    </td>
                    <td class="col-start-2 row-start-1 justify-self-end align-middle @2xl:py-2 @2xl:pr-2 @2xl:text-right">
                        <button
                            type="button"
                            :aria-label="'Edit ' + holding.symbol + ' holding'"
                            :title="'Edit ' + holding.symbol + ' holding'"
                            class="inline-grid size-11 cursor-pointer place-items-center rounded-lg text-slate-500 hover:bg-purple-50 hover:text-purple-700 focus-visible:outline-purple-600 @2xl:size-9"
                            @click="openEditor(holding, $event)"
                        >
                            <PencilSquareIcon class="size-[1.125rem]" aria-hidden="true" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-else-if="holdings.length" class="border-t border-slate-200 px-5 py-10 text-center">
            <p class="text-sm wrap-anywhere text-slate-700">No holdings match “{{ search }}”.</p>
        </div>
        <div v-else class="border-t border-slate-200 px-5 py-10 text-center">
            <BriefcaseIcon class="mx-auto size-6 text-slate-400" aria-hidden="true" />
            <h3 class="mt-2 text-sm font-semibold text-slate-900">No holdings yet</h3>
            <p class="mt-1 text-sm text-slate-600">Add a holding to record your first purchase.</p>
        </div>

        <HoldingForm
            v-if="editorOpen"
            :holding="selectedHolding"
            :date="date"
            :submit-url="selectedHolding?.updateUrl ?? addUrl"
            :symbol-search-url="symbolSearchUrl"
            :quote-url="quoteUrl"
            @close="closeEditor"
        />
    </section>
</template>

<script setup lang="ts">
import type { PortfolioHoldingValue } from '@/types/app/Models/Portfolio';
import { formatCurrency } from '@/utils/format';
import { BriefcaseIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import HoldingForm from './HoldingForm.vue';
import ProfitLoss from './ProfitLoss.vue';

const props = defineProps<{
    holdings: PortfolioHoldingValue[];
    stockCount: number;
    date: string;
    addUrl: string;
    symbolSearchUrl: string;
    quoteUrl: string;
}>();

const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const addButton = ref<HTMLButtonElement | null>(null);
const selectedHolding = ref<PortfolioHoldingValue | null>(null);
const editorOpen = ref(false);
let editorTrigger: HTMLButtonElement | null = null;
const visibleHoldings = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.holdings;
    return props.holdings.filter((holding) => holding.symbol.toLowerCase().includes(term) || holding.name?.toLowerCase().includes(term));
});

function clearSearch() {
    search.value = '';
    searchInput.value?.focus();
}

function openEditor(holding: PortfolioHoldingValue | null, event: MouseEvent) {
    editorTrigger = event.currentTarget as HTMLButtonElement;
    selectedHolding.value = holding;
    editorOpen.value = true;
}

async function closeEditor() {
    editorOpen.value = false;
    if (!props.holdings.length) search.value = '';
    await nextTick();
    const target = editorTrigger?.isConnected ? editorTrigger : (searchInput.value ?? addButton.value);
    target?.focus({ preventScroll: true });
    editorTrigger = null;
}
</script>
