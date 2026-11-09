<template>
    <form @submit.prevent="submit">
        <fieldset :disabled="form.processing" class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <SymbolSearchInput
                    v-model="form.symbol"
                    label="NSE Symbol"
                    name="symbol"
                    placeholder="Search by symbol or company"
                    :search-url="symbolSearchUrl"
                />
                <p v-if="form.errors.symbol" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.symbol }}</p>
                <p v-if="quoteLoading" class="mt-2 text-xs text-slate-500">Loading price…</p>
                <p v-else-if="quote" class="mt-2 text-xs text-slate-600">
                    CMP: ₹{{ quote.close.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} · {{ quote.date }}
                </p>
                <p v-else-if="form.symbol" class="mt-2 text-xs text-slate-500">
                    {{ quoteError || 'No price found on or before the selected date.' }}
                </p>
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
                label="Buy Price (₹)"
                name="price"
                placeholder="Price per share"
                :error="form.errors.price"
            />
        </fieldset>
        <p v-if="form.errors.date" role="alert" class="mt-3 text-sm text-red-600">{{ form.errors.date }}</p>
        <div class="mt-5 flex items-center gap-4">
            <PrimaryButton type="submit" :disabled="form.processing" class="mt-0 disabled:opacity-50">
                {{ form.processing ? 'Saving…' : holding ? 'Save Holding' : 'Add to Portfolio' }}
            </PrimaryButton>
            <button
                v-if="holding"
                type="button"
                :disabled="form.processing"
                class="cursor-pointer text-sm font-medium text-slate-600"
                @click="$emit('cancel')"
            >
                Cancel Edit
            </button>
        </div>
    </form>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import SymbolSearchInput from '@/Components/Form/SymbolSearchInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { PortfolioHoldingValue } from '@/types/app/Models/Portfolio';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps<{
    holding: PortfolioHoldingValue | null;
    date: string;
    submitUrl: string;
    symbolSearchUrl: string;
    quoteUrl: string;
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
const form = useForm({
    symbol: props.holding?.symbol ?? '',
    quantity: props.holding?.quantity ?? ('' as string | number),
    price: props.holding?.price ?? ('' as string | number),
    date: props.date,
});
const quote = ref<{ symbol: string; close: number; date: string } | null>(null);
const quoteLoading = ref(false);
const quoteError = ref('');

watch(
    () => [form.symbol, props.date],
    ([symbol, date], _, onCleanup) => {
        quote.value = null;
        quoteError.value = '';
        quoteLoading.value = false;
        if (!symbol.trim()) {
            return;
        }

        const controller = new AbortController();
        quoteLoading.value = true;
        const timer = setTimeout(async () => {
            try {
                const response = await axios.get(props.quoteUrl, { params: { symbol, date }, signal: controller.signal });
                quote.value = response.data.quote;
            } catch {
                if (!controller.signal.aborted) {
                    quoteError.value = 'Price lookup failed. You can still enter the purchase details.';
                }
            } finally {
                if (!controller.signal.aborted) {
                    quoteLoading.value = false;
                }
            }
        }, 300);
        onCleanup(() => {
            clearTimeout(timer);
            controller.abort();
        });
    },
    { immediate: true },
);

function submit() {
    form.date = props.date;
    form.submit(props.holding ? 'put' : 'post', props.submitUrl, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    });
}
</script>
