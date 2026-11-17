<template>
    <div>
        <div class="hidden lg:block">
            <table class="w-full border-collapse text-sm">
                <caption class="sr-only">
                    {{
                        title
                    }}
                    stocks and market data
                </caption>
                <thead class="border-y border-gray-200 bg-gray-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-3 py-3 font-semibold"
                            :class="column.text ? 'text-left' : 'text-right'"
                            :aria-sort="sort === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'"
                        >
                            <button
                                type="button"
                                :disabled="loading"
                                class="inline-flex min-h-9 cursor-pointer items-center gap-1 rounded hover:text-purple-700 focus-visible:outline-2 focus-visible:outline-purple-600"
                                @click="emit('sort', column.key)"
                            >
                                {{ column.label }}
                                <ChevronUpIcon v-if="sort === column.key && direction === 'asc'" class="size-3.5 shrink-0" aria-hidden="true" />
                                <ChevronDownIcon v-else-if="sort === column.key" class="size-3.5 shrink-0" aria-hidden="true" />
                                <ChevronUpDownIcon v-else class="size-3.5 shrink-0 text-gray-400" aria-hidden="true" />
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="stock in stocks" :key="stock.symbol" class="hover:bg-purple-50/30">
                        <td class="px-3 py-4">
                            <Link
                                v-if="stock.url"
                                :href="stock.url"
                                class="inline-flex min-h-9 items-center rounded font-semibold text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                                >{{ stock.symbol }}</Link
                            >
                            <span v-else class="font-semibold">{{ stock.symbol }}</span>
                        </td>
                        <td class="max-w-64 px-3 py-4 break-words text-gray-600">{{ stock.name || '—' }}</td>
                        <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.close_adjusted) }}</td>
                        <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.marketcap, 0) }}</td>
                        <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.price_to_earnings) }}</td>
                        <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums" :class="returnClass(stock.absolute_return_one_year)">
                            {{ formatReturn(stock.absolute_return_one_year) }}
                        </td>
                        <td class="px-3 py-4 text-right whitespace-nowrap tabular-nums">{{ formatNumber(stock.beta) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul class="divide-y divide-gray-200 border-y border-gray-200 lg:hidden" aria-label="Stocks">
            <li v-for="stock in stocks" :key="stock.symbol" class="py-5">
                <Link
                    v-if="stock.url"
                    :href="stock.url"
                    class="inline-flex min-h-9 items-center rounded text-base font-semibold text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                    >{{ stock.symbol }}</Link
                >
                <span v-else class="font-semibold">{{ stock.symbol }}</span>
                <p class="mt-1 text-sm break-words text-gray-600">{{ stock.name || '—' }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Adjusted close (₹)</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.close_adjusted) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Market cap (₹ crore)</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.marketcap, 0) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">P/E</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.price_to_earnings) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">1-year return</dt>
                        <dd class="mt-1 font-medium tabular-nums" :class="returnClass(stock.absolute_return_one_year)">
                            {{ formatReturn(stock.absolute_return_one_year) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Beta</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ formatNumber(stock.beta) }}</dd>
                    </div>
                </dl>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { ChevronDownIcon, ChevronUpDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';

type NumericValue = number | string | null;
type SortKey = 'symbol' | 'name' | 'close_adjusted' | 'marketcap' | 'price_to_earnings' | 'absolute_return_one_year' | 'beta';
interface Stock {
    symbol: string;
    name: string | null;
    url: string | null;
    close_adjusted: NumericValue;
    marketcap: NumericValue;
    price_to_earnings: NumericValue;
    absolute_return_one_year: NumericValue;
    beta: NumericValue;
}
defineProps<{
    stocks: Stock[];
    title: string;
    sort: SortKey;
    direction: 'asc' | 'desc';
    loading: boolean;
}>();
const emit = defineEmits<{ sort: [key: SortKey] }>();

const columns: { key: SortKey; label: string; text?: boolean }[] = [
    { key: 'symbol', label: 'Symbol', text: true },
    { key: 'name', label: 'Company', text: true },
    { key: 'close_adjusted', label: 'Adjusted close (₹)' },
    { key: 'marketcap', label: 'Market cap (₹ crore)' },
    { key: 'price_to_earnings', label: 'P/E' },
    { key: 'absolute_return_one_year', label: '1-year return' },
    { key: 'beta', label: 'Beta' },
];
function numberValue(value: NumericValue): number | null {
    if (value === null || value === '') {
        return null;
    }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}
function formatNumber(value: NumericValue, decimals = 2): string {
    const number = numberValue(value);
    return number === null ? '—' : number.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
function formatReturn(value: NumericValue): string {
    const number = numberValue(value);
    return number === null ? '—' : `${number > 0 ? '+' : ''}${formatNumber(number)}%`;
}
function returnClass(value: NumericValue): string {
    const number = numberValue(value);
    return number === null || number === 0 ? 'text-gray-600' : number > 0 ? 'text-emerald-700' : 'text-rose-700';
}
</script>
