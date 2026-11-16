<template>
    <div class="min-w-0">
        <Head :title="portfolio.name + ' · Portfolio'" />
        <Link :href="portfolioRoutes.index" class="mb-4 inline-flex text-sm font-medium text-purple-700 hover:text-purple-500">← All Portfolios</Link>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 break-words">
                <PageHeader description="Review your holdings, values, and corporate actions.">{{ portfolio.name }}</PageHeader>
            </div>
            <a
                :href="portfolioRoutes.download"
                class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                Export Portfolio
            </a>
        </div>

        <div class="flex flex-col gap-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="rename">
                        <TextInput
                            id="portfolio-name"
                            v-model="nameForm.name"
                            label="Portfolio Name"
                            name="name"
                            :error="nameForm.errors.name"
                            class="min-w-0 flex-1"
                        />
                        <PrimaryButton type="submit" :disabled="nameForm.processing" class="mt-0 disabled:opacity-50">Save Name</PrimaryButton>
                    </form>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="changeDate">
                        <div class="min-w-0 flex-1">
                            <label for="valuation-date" class="block text-sm/6 font-medium text-gray-900">Valuation Date</label>
                            <input
                                id="valuation-date"
                                v-model="dateForm.date"
                                name="date"
                                type="date"
                                :max="today"
                                required
                                class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-sm/6 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:outline-purple-600"
                            />
                            <p v-if="dateForm.errors.date" role="alert" class="mt-2 text-sm text-red-600">{{ dateForm.errors.date }}</p>
                        </div>
                        <PrimaryButton type="submit" :disabled="dateForm.processing" class="mt-0 disabled:opacity-50">Apply Date</PrimaryButton>
                        <Link :href="portfolioRoutes.edit" class="py-2 text-sm font-medium text-purple-700">Latest</Link>
                    </form>
                </div>
                <p class="mt-5 text-xs text-slate-500">
                    Values use your current quantities and raw market prices on or before {{ valuation.date }}. The same entered holdings are used for
                    each selected date.
                </p>
            </section>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Total Cost" :value="amount(valuation.summary.cost)" />
                <StatCard
                    :label="valuation.summary.missingPrices ? 'Known Portfolio Value' : 'Total Portfolio Value'"
                    :value="amount(valuation.summary.value)"
                    variant="blue"
                >
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            :aria-label="valuation.summary.missingPrices ? 'Copy known portfolio value' : 'Copy total portfolio value'"
                            title="Copy number for Google Sheets"
                            class="inline-flex cursor-pointer items-center gap-1 rounded-md bg-white/70 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700"
                            @click="copyPortfolioValue"
                        >
                            <ClipboardDocumentIcon class="size-4" aria-hidden="true" />
                            Copy value
                        </button>
                        <p role="status" aria-live="polite" class="text-xs text-blue-900">{{ copyMessage }}</p>
                    </div>
                </StatCard>
                <StatCard
                    label="Unrealised Profit / Loss"
                    :value="amount(valuation.summary.profitLoss)"
                    :variant="valuation.summary.profitLoss === null ? 'amber' : valuation.summary.profitLoss < 0 ? 'red' : 'green'"
                />
                <StatCard label="Total Stocks" :value="valuation.summary.stocks" />
            </div>
            <p v-if="valuation.summary.percentChange !== null" class="text-sm text-slate-600">
                Portfolio change:
                <span class="font-semibold" :class="changeColor(valuation.summary.percentChange)">{{
                    percent(valuation.summary.percentChange)
                }}</span>
                · {{ valuation.summary.lots }} holding entries
            </p>
            <WarningAlert v-if="valuation.summary.missingPrices">
                {{ valuation.summary.missingPrices }} holding entries have no price on or before {{ valuation.date }}. The known value excludes them.
                Total profit and percentage change are unavailable.
            </WarningAlert>
            <WarningAlert v-if="valuation.summary.earlierPrices">
                {{ valuation.summary.earlierPrices }} holding entries use prices earlier than the latest market date, {{ valuation.marketDate }}.
                Check the price date in each row.
            </WarningAlert>

            <section id="holding-form" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 class="mb-4 text-base font-semibold text-slate-900">{{ editingHolding ? 'Edit Holding' : 'Add Holding' }}</h2>
                <HoldingForm
                    :key="holdingFormKey"
                    :holding="editingHolding"
                    :date="valuation.date"
                    :submit-url="editingHolding?.updateUrl ?? portfolioRoutes.addHolding"
                    :symbol-search-url="portfolioRoutes.symbols"
                    :quote-url="portfolioRoutes.quote"
                    @saved="clearEditor"
                    @cancel="clearEditor"
                />
            </section>

            <section v-if="corporateActions.length" class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 class="text-base font-semibold text-slate-900">Corporate Actions · {{ valuation.date }}</h2>
                <p class="mt-1 text-xs text-slate-500">These events do not change the quantities or purchase prices entered here.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full divide-y divide-slate-200 text-left text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3">Symbol</th>
                                <th scope="col" class="px-3 py-3">Type</th>
                                <th scope="col" class="px-3 py-3">Value</th>
                                <th scope="col" class="px-3 py-3">Ex-Date</th>
                                <th scope="col" class="relative px-3 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="action in corporateActions" :key="action.id">
                                <td class="px-3 py-3 font-medium">{{ action.symbol }}</td>
                                <td class="px-3 py-3">
                                    <span class="capitalize">{{ action.type ?? 'Other' }}</span>
                                    <p v-if="action.description" class="mt-1 max-w-md text-xs text-slate-500">{{ action.description }}</p>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    {{ action.type === 'dividend' ? dividendAmount(action.dividend) : (action.ratio ?? '—') }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">{{ action.date }}</td>
                                <td class="px-3 py-3"><Link :href="action.editUrl" class="font-semibold text-purple-700">Edit Action</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 class="text-base font-semibold text-slate-900">Holdings</h2>
                <p class="mt-1 text-xs text-slate-500">Each row is a separate purchase entry. You can keep multiple entries for the same symbol.</p>
                <div
                    v-if="holdingToRemove"
                    role="alert"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 p-4"
                >
                    <p class="text-sm text-red-800">
                        Remove {{ holdingToRemove.symbol }} ({{ holdingToRemove.quantity }} shares) from this portfolio?
                    </p>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            :disabled="deleteForm.processing"
                            class="cursor-pointer rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            @click="removeHolding"
                        >
                            Remove Holding
                        </button>
                        <button
                            type="button"
                            :disabled="deleteForm.processing"
                            class="cursor-pointer text-sm font-medium text-slate-700"
                            @click="holdingToRemove = null"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
                <p v-if="deleteForm.errors.date" role="alert" class="mt-3 text-sm text-red-700">{{ deleteForm.errors.date }}</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="text-slate-600">
                            <tr>
                                <th scope="col" class="px-3 py-3">Name</th>
                                <th scope="col" class="px-3 py-3">Series</th>
                                <th scope="col" class="px-3 py-3 text-right">Quantity</th>
                                <th scope="col" class="px-3 py-3 text-right whitespace-nowrap">Buy Price</th>
                                <th scope="col" class="px-3 py-3 text-right">CMP</th>
                                <th scope="col" class="px-3 py-3 text-right">Current Value</th>
                                <th scope="col" class="px-3 py-3 text-right whitespace-nowrap">Profit / Loss</th>
                                <th scope="col" class="px-3 py-3 text-right">Change</th>
                                <th scope="col" class="relative px-3 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="holding in valuation.holdings"
                                :key="holding.id"
                                :data-test="'holding-' + holding.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="min-w-40 px-3 py-4">
                                    <Link :href="'/instruments/' + encodeURIComponent(holding.symbol)" class="font-semibold text-purple-700">{{
                                        holding.symbol
                                    }}</Link>
                                    <p class="mt-1 text-xs text-slate-500">{{ holding.name }}</p>
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="holding.series === 'BE' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'"
                                        >{{ holding.series ?? '—' }}</span
                                    >
                                </td>
                                <td class="px-3 py-4 text-right tabular-nums">{{ holding.quantity.toLocaleString('en-IN') }}</td>
                                <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ amount(holding.price) }}</td>
                                <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">
                                    {{ amount(holding.close) }}
                                    <p class="mt-1 text-xs" :class="holding.earlierPrice || !holding.priceDate ? 'text-amber-700' : 'text-slate-500'">
                                        {{ holding.priceDate ?? 'No price' }}
                                    </p>
                                    <span v-if="holding.earlierPrice" class="text-xs text-amber-700">Earlier price</span>
                                </td>
                                <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ amount(holding.value) }}</td>
                                <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums" :class="changeColor(holding.profitLoss)">
                                    {{ amount(holding.profitLoss) }}
                                </td>
                                <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums" :class="changeColor(holding.percentChange)">
                                    {{ percent(holding.percentChange) }}
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-3">
                                        <button
                                            type="button"
                                            class="cursor-pointer font-semibold text-purple-700"
                                            :aria-label="'Edit ' + holding.symbol + ' holding'"
                                            @click="editHolding(holding)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="cursor-pointer font-semibold text-red-600"
                                            :aria-label="'Remove ' + holding.symbol + ' holding'"
                                            @click="holdingToRemove = holding"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="valuation.holdings.length === 0">
                                <td colspan="9" class="px-3 py-12 text-center text-slate-500">
                                    No holdings yet. Add a symbol, quantity, and buy price above.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import type { PortfolioHoldingValue, PortfolioValuation } from '@/types/app/Models/Portfolio';
import { ArrowDownTrayIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import HoldingForm from './partials/HoldingForm.vue';

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
const deleteForm = useForm({ date: props.valuation.date });
const editingHolding = ref<PortfolioHoldingValue | null>(null);
const holdingToRemove = ref<PortfolioHoldingValue | null>(null);
const holdingFormKey = ref(0);
const copyMessage = ref('');
const currency = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2, maximumFractionDigits: 2 });

watch(
    () => props.valuation.summary.value,
    () => {
        copyMessage.value = '';
    },
);

async function copyPortfolioValue(): Promise<void> {
    const value = props.valuation.summary.value.toFixed(2);
    copyMessage.value = '';

    try {
        await navigator.clipboard.writeText(value);
        copyMessage.value = 'Value copied.';
    } catch {
        copyMessage.value = 'Copy was not available. Select and copy this number: ' + value;
    }
}

function amount(value: number | null): string {
    return value === null ? '—' : currency.format(value);
}

function percent(value: number | null): string {
    return value === null ? '—' : (value > 0 ? '+' : '') + value.toFixed(2) + '%';
}

function changeColor(value: number | null): string {
    return value === null || value === 0 ? 'text-slate-600' : value > 0 ? 'text-green-700' : 'text-red-700';
}

function dividendAmount(value: string | null): string {
    return value !== null && Number.isFinite(Number(value)) ? amount(Number(value)) : '—';
}

function rename() {
    nameForm.put(props.portfolioRoutes.update, { preserveScroll: true });
}

function changeDate() {
    dateForm.get(props.portfolioRoutes.edit, { preserveState: false });
}

function clearEditor() {
    editingHolding.value = null;
    holdingFormKey.value++;
}

async function editHolding(holding: PortfolioHoldingValue) {
    editingHolding.value = holding;
    holdingFormKey.value++;
    await nextTick();
    document.getElementById('holding-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function removeHolding() {
    if (!holdingToRemove.value) {
        return;
    }
    deleteForm.date = props.valuation.date;
    deleteForm.delete(holdingToRemove.value.destroyUrl, {
        preserveScroll: true,
        onSuccess: () => {
            holdingToRemove.value = null;
            clearEditor();
        },
    });
}
</script>
