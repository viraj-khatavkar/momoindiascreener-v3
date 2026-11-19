<template>
    <Dialog :open="true" class="relative z-50" @close="dismiss">
        <div class="fixed inset-0 bg-slate-950/30" aria-hidden="true" />
        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
                <DialogPanel class="w-full max-w-md min-w-0 rounded-xl bg-white p-5 shadow-xl sm:p-6">
                    <template v-if="confirmingRemoval && holding">
                        <DialogTitle class="text-xl font-semibold tracking-tight text-slate-950">Remove holding?</DialogTitle>
                        <DialogDescription class="mt-3 text-sm/6 text-slate-700">
                            Remove {{ holding.symbol }} ({{ holding.quantity.toLocaleString('en-IN') }}
                            {{ holding.quantity === 1 ? 'share' : 'shares' }} at {{ formatCurrency(holding.price) }} each) from this portfolio?
                        </DialogDescription>
                        <p v-if="deleteForm.errors.date" role="alert" class="mt-3 text-sm text-red-700">{{ deleteForm.errors.date }}</p>
                        <div class="mt-6 flex justify-end gap-2">
                            <button
                                ref="keepButton"
                                type="button"
                                :disabled="deleteForm.processing"
                                class="min-h-11 cursor-pointer rounded-md border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="setRemovalConfirmation(false)"
                            >
                                Keep holding
                            </button>
                            <button
                                type="button"
                                :disabled="deleteForm.processing"
                                class="min-h-11 cursor-pointer rounded-md bg-red-700 px-3 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50"
                                @click="removeHolding"
                            >
                                {{ deleteForm.processing ? 'Removing…' : 'Remove holding' }}
                            </button>
                        </div>
                    </template>
                    <form v-else @submit.prevent="submit">
                        <DialogTitle class="text-xl font-semibold tracking-tight text-slate-950">{{
                            holding ? 'Edit holding' : 'Add holding'
                        }}</DialogTitle>
                        <fieldset :disabled="form.processing" class="mt-5 grid min-w-0 grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <SymbolSearchInput
                                    v-model="form.symbol"
                                    label="NSE symbol"
                                    name="symbol"
                                    placeholder="Search by symbol or company"
                                    :search-url="symbolSearchUrl"
                                    :error="form.errors.symbol"
                                />
                                <div role="status" class="mt-2 text-xs/5 text-slate-600">
                                    <p v-if="quoteLoading">Loading price…</p>
                                    <p v-else-if="quote">Market price {{ formatCurrency(quote.close) }} · {{ quote.date }}</p>
                                    <p v-else-if="form.symbol">{{ quoteError || 'No price found on or before the selected date.' }}</p>
                                </div>
                            </div>
                            <TextInput
                                id="quantity"
                                v-model="form.quantity"
                                type="number"
                                label="Quantity"
                                name="quantity"
                                placeholder="Whole shares"
                                :error="form.errors.quantity"
                            />
                            <TextInput
                                id="price"
                                v-model="form.price"
                                type="number"
                                label="Buy price (₹)"
                                name="price"
                                placeholder="Price per share"
                                :error="form.errors.price"
                            />
                        </fieldset>
                        <p v-if="form.errors.date" role="alert" class="mt-3 text-sm text-red-700">{{ form.errors.date }}</p>
                        <div class="mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
                            <button
                                v-if="holding"
                                ref="removeButton"
                                type="button"
                                :disabled="form.processing"
                                :aria-label="'Remove ' + holding.symbol + ' holding'"
                                class="mr-auto inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-md px-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                                @click="setRemovalConfirmation(true)"
                            >
                                <TrashIcon class="size-4" aria-hidden="true" /> Remove
                            </button>
                            <button
                                type="button"
                                :disabled="form.processing"
                                class="min-h-11 cursor-pointer rounded-md border border-slate-300 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="dismiss"
                            >
                                Cancel
                            </button>
                            <PrimaryButton type="submit" :disabled="form.processing" class="mt-0! min-h-11 disabled:opacity-50">{{
                                form.processing ? 'Saving…' : holding ? 'Save holding' : 'Add to portfolio'
                            }}</PrimaryButton>
                        </div>
                    </form>
                </DialogPanel>
            </div>
        </div>
    </Dialog>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import SymbolSearchInput from '@/Components/Form/SymbolSearchInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { PortfolioHoldingValue } from '@/types/app/Models/Portfolio';
import { formatCurrency } from '@/utils/format';
import { Dialog, DialogDescription, DialogPanel, DialogTitle } from '@headlessui/vue';
import { TrashIcon } from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
    holding: PortfolioHoldingValue | null;
    date: string;
    submitUrl: string;
    symbolSearchUrl: string;
    quoteUrl: string;
}>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({
    symbol: props.holding?.symbol ?? '',
    quantity: props.holding?.quantity ?? ('' as string | number),
    price: props.holding?.price ?? ('' as string | number),
    date: props.date,
});
const deleteForm = useForm({ date: props.date });
const confirmingRemoval = ref(false);
const keepButton = ref<HTMLButtonElement | null>(null);
const removeButton = ref<HTMLButtonElement | null>(null);
const quote = ref<{ symbol: string; close: number; date: string } | null>(null);
const quoteLoading = ref(false);
const quoteError = ref('');

watch(
    () => [form.symbol, props.date],
    ([symbol, date], _, onCleanup) => {
        quote.value = null;
        quoteError.value = '';
        quoteLoading.value = false;
        if (!symbol.trim()) return;

        const controller = new AbortController();
        quoteLoading.value = true;
        const timer = setTimeout(async () => {
            try {
                const response = await axios.get(props.quoteUrl, { params: { symbol, date }, signal: controller.signal });
                quote.value = response.data.quote;
            } catch {
                if (!controller.signal.aborted) quoteError.value = 'Price lookup failed. You can still enter the purchase details.';
            } finally {
                if (!controller.signal.aborted) quoteLoading.value = false;
            }
        }, 300);
        onCleanup(() => {
            clearTimeout(timer);
            controller.abort();
        });
    },
    { immediate: true },
);

function dismiss() {
    if (!form.processing && !deleteForm.processing) emit('close');
}

async function setRemovalConfirmation(confirm: boolean) {
    confirmingRemoval.value = confirm;
    deleteForm.clearErrors();
    await nextTick();
    if (confirm) keepButton.value?.focus();
    else removeButton.value?.focus();
}

function submit() {
    form.date = props.date;
    form.submit(props.holding ? 'put' : 'post', props.submitUrl, { preserveScroll: true, onSuccess: () => emit('close') });
}

function removeHolding() {
    if (!props.holding || !confirmingRemoval.value) return;
    deleteForm.date = props.date;
    deleteForm.delete(props.holding.destroyUrl, { preserveScroll: true, onSuccess: () => emit('close') });
}
</script>
