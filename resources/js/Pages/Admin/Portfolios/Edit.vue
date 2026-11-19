<template>
    <div class="min-w-0 space-y-5">
        <Head :title="portfolio.name + ' · Portfolio'" />
        <header>
            <Link :href="portfolioRoutes.index" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-purple-700">
                <ArrowLeftIcon class="size-4" aria-hidden="true" /> All portfolios
            </Link>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                <form v-if="renaming" class="min-w-0 flex-auto sm:max-w-lg" @submit.prevent="rename" @keydown.esc.prevent="cancelRename">
                    <label for="portfolio-name" class="sr-only">Portfolio name</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            id="portfolio-name"
                            ref="nameInput"
                            v-model="nameForm.name"
                            name="name"
                            type="text"
                            maxlength="255"
                            required
                            :aria-invalid="!!nameForm.errors.name"
                            :aria-describedby="nameForm.errors.name ? 'name-error' : undefined"
                            :disabled="nameForm.processing"
                            class="h-10 min-w-32 flex-1 rounded-md border border-slate-300 bg-white px-3 font-semibold focus:outline-purple-600"
                        />
                        <PrimaryButton type="submit" :disabled="nameForm.processing" class="mt-0! min-h-10 disabled:opacity-50"
                            >Save name</PrimaryButton
                        >
                        <button
                            type="button"
                            :disabled="nameForm.processing"
                            class="cursor-pointer px-2 py-2 text-sm text-slate-600"
                            @click="cancelRename"
                        >
                            Cancel
                        </button>
                    </div>
                    <p v-if="nameForm.errors.name" id="name-error" role="alert" class="mt-1 text-sm text-red-700">{{ nameForm.errors.name }}</p>
                </form>
                <div v-else class="flex min-w-0 items-center gap-1.5">
                    <h1 class="text-2xl font-semibold tracking-tight wrap-anywhere text-slate-950 sm:text-3xl">{{ portfolio.name }}</h1>
                    <button
                        ref="renameButton"
                        type="button"
                        title="Rename portfolio"
                        aria-label="Rename portfolio"
                        class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-md text-slate-500 hover:bg-white hover:text-purple-700 focus-visible:outline-purple-600"
                        @click="startRename"
                    >
                        <PencilSquareIcon class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                    <Popover class="relative">
                        <PopoverButton
                            :aria-label="'Change valuation date, ' + valuation.date"
                            class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:border-slate-400 focus-visible:outline-purple-600"
                            @click="resetDate"
                        >
                            <CalendarDaysIcon class="size-4 text-slate-500" aria-hidden="true" />
                            <span
                                >As of <time :datetime="valuation.date">{{ formatDate(valuation.date + 'T00:00:00') }}</time></span
                            >
                            <ChevronDownIcon class="size-3.5 text-slate-500" aria-hidden="true" />
                        </PopoverButton>
                        <PopoverPanel
                            v-slot="{ close }"
                            focus
                            class="absolute left-0 z-25 mt-2 w-68 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-4 shadow-lg sm:right-0 sm:left-auto"
                        >
                            <form @submit.prevent="changeDate(close)">
                                <label for="valuation-date" class="text-sm font-semibold text-slate-900">Valuation date</label>
                                <input
                                    id="valuation-date"
                                    v-model="dateForm.date"
                                    name="date"
                                    type="date"
                                    required
                                    :max="today"
                                    :aria-invalid="!!dateForm.errors.date"
                                    :aria-describedby="dateForm.errors.date ? 'valuation-date-error' : 'valuation-date-hint'"
                                    class="mt-2 block h-11 w-full min-w-0 rounded-md border border-slate-300 px-3 text-base focus:outline-purple-600 sm:h-10 sm:text-sm"
                                />
                                <p id="valuation-date-hint" class="mt-2 text-xs/5 text-slate-600">
                                    Use current quantities and the last raw closing price on or before this date.
                                </p>
                                <p v-if="dateForm.errors.date" id="valuation-date-error" role="alert" class="mt-2 text-sm text-red-700">
                                    {{ dateForm.errors.date }}
                                </p>
                                <div class="mt-4 flex items-center justify-between gap-3">
                                    <Link
                                        :href="portfolioRoutes.edit"
                                        preserve-state
                                        preserve-scroll
                                        class="py-2 text-sm font-medium text-purple-700 hover:underline"
                                        @success="close()"
                                        >Use latest</Link
                                    >
                                    <PrimaryButton type="submit" :disabled="dateForm.processing" class="mt-0! min-h-10 disabled:opacity-50"
                                        >Apply date</PrimaryButton
                                    >
                                </div>
                            </form>
                        </PopoverPanel>
                    </Popover>
                    <a
                        :href="portfolioRoutes.download"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-purple-600"
                    >
                        <ArrowDownTrayIcon class="size-4" aria-hidden="true" /> Export CSV
                    </a>
                </div>
            </div>
        </header>

        <section aria-label="Portfolio valuation" class="@container rounded-xl border border-slate-200 bg-white shadow-xs">
            <dl class="grid grid-cols-2 @2xl:grid-cols-[1.25fr_1fr_1fr]">
                <div
                    class="col-span-2 min-w-0 rounded-t-xl border-b border-purple-100 bg-purple-50/65 px-4 py-3 @2xl:col-span-1 @2xl:rounded-l-xl @2xl:rounded-tr-none @2xl:border-r @2xl:border-b-0 @2xl:px-6 @2xl:py-4"
                >
                    <dt class="text-sm font-medium text-purple-900">
                        {{ valuation.summary.missingPrices ? 'Known portfolio value' : 'Portfolio value' }}
                    </dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-x-2">
                        <span class="text-2xl font-semibold tracking-tight wrap-anywhere text-slate-950 tabular-nums">{{
                            formatCurrency(valuation.summary.value)
                        }}</span>
                        <button
                            type="button"
                            :aria-label="valuation.summary.missingPrices ? 'Copy known portfolio value' : 'Copy total portfolio value'"
                            title="Copy value for Google Sheets"
                            class="grid size-8 cursor-pointer place-items-center rounded-md text-purple-700 hover:bg-purple-100 focus-visible:outline-purple-600"
                            @click="copyPortfolioValue"
                        >
                            <CheckIcon v-if="copyState === 'copied'" class="size-4" aria-hidden="true" />
                            <ClipboardDocumentIcon v-else class="size-4" aria-hidden="true" />
                        </button>
                    </dd>
                    <dd role="status" class="text-xs text-purple-800">
                        <span v-if="copyState === 'copied'">Value copied.</span>
                        <span v-if="copyState === 'manual'"
                            >Select and copy <strong class="select-all">{{ valuation.summary.value.toFixed(2) }}</strong></span
                        >
                    </dd>
                </div>
                <div class="min-w-0 px-4 py-3 @2xl:px-6 @2xl:py-4">
                    <dt class="text-sm font-medium text-slate-600">Total cost</dt>
                    <dd class="mt-1 text-lg font-semibold tracking-tight wrap-anywhere text-slate-900 tabular-nums sm:text-xl">
                        {{ formatCurrency(valuation.summary.cost) }}
                    </dd>
                </div>
                <div class="min-w-0 border-l border-slate-200 px-4 py-3 @2xl:px-6 @2xl:py-4">
                    <dt class="text-sm font-medium text-slate-600">Unrealised P/L</dt>
                    <dd class="mt-1 text-lg tracking-tight sm:text-xl">
                        <ProfitLoss :value="valuation.summary.profitLoss" :percent-change="valuation.summary.percentChange" />
                    </dd>
                </div>
            </dl>
        </section>

        <WarningAlert v-if="valuation.summary.missingPrices">
            {{ valuation.summary.missingPrices }} {{ valuation.summary.missingPrices === 1 ? 'entry has' : 'entries have' }} no price by
            {{ valuation.date }}. Value is partial. Total profit and return are unavailable.
        </WarningAlert>
        <WarningAlert v-if="valuation.summary.earlierPrices">
            {{ valuation.summary.earlierPrices }} {{ valuation.summary.earlierPrices === 1 ? 'entry uses a price' : 'entries use prices' }} from
            before the latest market date, {{ valuation.marketDate }}. See the marked price dates below.
        </WarningAlert>

        <details v-if="corporateActions.length" class="group rounded-xl border border-slate-200 bg-white">
            <summary
                class="flex cursor-pointer list-none flex-wrap items-center gap-2 rounded-xl px-4 py-3 text-sm focus-visible:outline-purple-600 sm:px-5 [&::-webkit-details-marker]:hidden"
            >
                <CalendarDaysIcon class="size-4 text-slate-500" aria-hidden="true" />
                <span class="font-semibold text-slate-800">Corporate actions</span>
                <span class="rounded bg-slate-100 px-1.5 text-xs/5 font-medium text-slate-700">{{ corporateActions.length }}</span>
                <time :datetime="valuation.date" class="ml-auto text-xs text-slate-500">{{ formatDate(valuation.date + 'T00:00:00') }}</time>
                <ChevronDownIcon class="size-4 text-slate-500 group-open:rotate-180" aria-hidden="true" />
            </summary>
            <div class="border-t border-slate-100 px-4 sm:px-5">
                <p class="pt-3 text-xs text-slate-600">Review these events and update the holding quantities or buy prices yourself if needed.</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="action in corporateActions" :key="action.id" class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="font-semibold wrap-anywhere">{{ action.symbol }}</span>
                                <span class="text-slate-700 capitalize">{{ action.type ?? 'Other' }}</span>
                                <span class="font-medium tabular-nums">{{
                                    action.type === 'dividend' ? dividendAmount(action.dividend) : (action.ratio ?? '—')
                                }}</span>
                            </div>
                            <p v-if="action.description" class="mt-1 text-xs text-slate-600">{{ action.description }}</p>
                        </div>
                        <Link
                            :href="action.editUrl"
                            :aria-label="'Edit ' + action.symbol + ' corporate action'"
                            class="shrink-0 rounded px-2 py-2 text-sm font-medium text-purple-700 hover:bg-purple-50"
                            >Edit action</Link
                        >
                    </li>
                </ul>
            </div>
        </details>

        <HoldingsPanel
            :holdings="valuation.holdings"
            :stock-count="valuation.summary.stocks"
            :date="valuation.date"
            :add-url="portfolioRoutes.addHolding"
            :symbol-search-url="portfolioRoutes.symbols"
            :quote-url="portfolioRoutes.quote"
        />
    </div>
</template>

<script setup lang="ts">
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import type { PortfolioValuation } from '@/types/app/Models/Portfolio';
import { formatCurrency, formatDate } from '@/utils/format';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    CalendarDaysIcon,
    CheckIcon,
    ChevronDownIcon,
    ClipboardDocumentIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import HoldingsPanel from './partials/HoldingsPanel.vue';
import ProfitLoss from './partials/ProfitLoss.vue';

const props = defineProps<{
    portfolio: { id: number; name: string };
    valuation: PortfolioValuation;
    corporateActions: {
        id: number;
        symbol: string;
        date: string;
        type: string | null;
        description: string | null;
        ratio: string | null;
        dividend: string | null;
        editUrl: string;
    }[];
    today: string;
    portfolioRoutes: { index: string; edit: string; update: string; addHolding: string; download: string; symbols: string; quote: string };
}>();

const nameForm = useForm({ name: props.portfolio.name });
const dateForm = useForm({ date: props.valuation.date });
const renaming = ref(false);
const nameInput = ref<HTMLInputElement | null>(null);
const renameButton = ref<HTMLButtonElement | null>(null);
const copyState = ref<'idle' | 'copied' | 'manual'>('idle');

watch(
    () => props.valuation.summary.value,
    () => {
        copyState.value = 'idle';
    },
);

async function copyPortfolioValue() {
    const value = props.valuation.summary.value.toFixed(2);
    try {
        await navigator.clipboard.writeText(value);
        copyState.value = 'copied';
    } catch {
        copyState.value = 'manual';
    }
}

function dividendAmount(value: string | null) {
    return value !== null && Number.isFinite(Number(value)) ? formatCurrency(value) : '—';
}

async function startRename() {
    nameForm.name = props.portfolio.name;
    nameForm.clearErrors();
    renaming.value = true;
    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
}

async function cancelRename() {
    if (nameForm.processing) return;
    renaming.value = false;
    await nextTick();
    renameButton.value?.focus();
}

function rename() {
    nameForm.put(props.portfolioRoutes.update, { preserveScroll: true, onSuccess: cancelRename });
}

function resetDate() {
    dateForm.date = props.valuation.date;
    dateForm.clearErrors();
}

function changeDate(close: () => void) {
    dateForm.get(props.portfolioRoutes.edit, { preserveState: true, preserveScroll: true, onSuccess: () => close() });
}
</script>
