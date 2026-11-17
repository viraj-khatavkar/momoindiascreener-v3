<template>
    <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-6" aria-labelledby="monthly-heading">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 id="monthly-heading" class="text-lg font-semibold text-slate-900">Monthly &amp; yearly returns</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ rows.length }} {{ rows.length === 1 ? 'year' : 'years' }} shown. Select a month to inspect its return<span
                        v-if="benchmarkName"
                    >
                        against {{ benchmarkName }}</span
                    >.
                </p>
            </div>
            <span class="rounded-md bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">Through {{ formatDate(endDate) }}</span>
        </div>
        <div class="hidden lg:block">
            <table class="w-full table-fixed border-separate border-spacing-1 text-xs tabular-nums">
                <caption class="sr-only">
                    Monthly returns and year totals. The first and last years can cover partial periods.
                </caption>
                <colgroup>
                    <col class="w-14" />
                    <col v-for="month in 12" :key="month" />
                    <col class="w-24" />
                </colgroup>
                <thead>
                    <tr>
                        <th class="py-2 text-left font-medium text-slate-500">Year</th>
                        <th v-for="month in months" :key="month" class="py-2 text-center font-medium text-slate-500">{{ month }}</th>
                        <th class="py-2 text-right font-medium text-slate-500">Year / period</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in rows" :key="row.year">
                        <tr>
                            <th scope="row" class="py-2 text-left font-semibold text-slate-800">{{ row.year }}</th>
                            <td v-for="(value, index) in row.months" :key="index" class="rounded-md text-center" :class="cellClass(value)">
                                <button
                                    type="button"
                                    :disabled="value === null"
                                    :aria-label="monthDescription(row, index)"
                                    :aria-pressed="selected?.year === row.year && selected.month === index"
                                    class="min-h-10 w-full rounded-md px-0.5 py-2 focus-visible:outline-2 focus-visible:outline-purple-600 disabled:cursor-default"
                                    @click="selected = { year: row.year, month: index }"
                                >
                                    {{ percent(value) }}
                                </button>
                            </td>
                            <td class="pl-2 text-right">
                                <span class="font-semibold" :class="row.yearReturn >= 0 ? 'text-emerald-700' : 'text-red-700'">{{
                                    percent(row.yearReturn)
                                }}</span
                                ><span v-if="periodLabel(row) !== 'Year return'" class="mt-1 block text-[11px] text-slate-500">{{
                                    periodLabel(row)
                                }}</span>
                            </td>
                        </tr>
                        <tr v-if="selected?.year === row.year">
                            <td colspan="14" class="rounded-md bg-purple-50 px-3 py-3 text-sm text-purple-900" role="status">
                                {{ monthDescription(row, selected.month) }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="space-y-4 lg:hidden" data-test="mobile-monthly-returns">
            <article v-for="row in rows" :key="row.year" class="rounded-xl border border-slate-200 p-3 sm:p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="font-semibold text-slate-900">{{ row.year }}</h4>
                    <p class="text-sm text-slate-500">
                        {{ periodLabel(row) }}
                        <strong class="ml-1 tabular-nums" :class="row.yearReturn >= 0 ? 'text-emerald-700' : 'text-red-700'">{{
                            percent(row.yearReturn)
                        }}</strong>
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                    <button
                        v-for="(value, index) in row.months"
                        :key="index"
                        type="button"
                        :disabled="value === null"
                        :aria-label="monthDescription(row, index)"
                        :aria-pressed="selected?.year === row.year && selected.month === index"
                        class="min-w-0 rounded-md px-1 py-2.5 text-center focus-visible:outline-2 focus-visible:outline-purple-600"
                        :class="cellClass(value)"
                        @click="selected = { year: row.year, month: index }"
                    >
                        <span class="block text-xs">{{ months[index] }}</span
                        ><span class="mt-1 block text-sm font-medium tabular-nums">{{ percent(value) }}</span>
                    </button>
                </div>
                <p v-if="selected?.year === row.year" class="mt-3 rounded-md bg-purple-50 p-3 text-sm text-purple-900" role="status">
                    {{ monthDescription(row, selected.month) }}
                </p>
            </article>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-slate-500">
            Returns use saved NAV after trading charges. The first period starts on {{ formatDate(startDate) }}. The final month and year end on
            {{ formatDate(endDate) }}. A dash means no data.
        </p>
    </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { formatDate } from '@/utils/format';

interface ReturnYear {
    year: string;
    months: (number | null)[];
    yearReturn: number;
}
const props = defineProps<{
    rows: ReturnYear[];
    startDate: string;
    endDate: string;
    benchmarkName: string;
    benchmarkReturns: Record<string, number>;
}>();
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const selected = ref<{ year: string; month: number } | null>(null);

function percent(value: number | null): string {
    return value === null ? '—' : `${value >= 0 ? '+' : ''}${(value * 100).toFixed(1)}%`;
}
function cellClass(value: number | null): string {
    if (value === null) return 'bg-slate-50 text-slate-400';
    if (Math.abs(value) < 0.0025) return 'bg-slate-50 text-slate-600';
    if (value >= 0.12) return 'bg-emerald-200 text-emerald-950';
    if (value >= 0.06) return 'bg-emerald-100 text-emerald-900';
    if (value > 0) return 'bg-emerald-50 text-emerald-800';
    if (value > -0.06) return 'bg-red-50 text-red-800';
    if (value > -0.12) return 'bg-red-100 text-red-900';
    return 'bg-red-200 text-red-950';
}
function periodLabel(row: ReturnYear): string {
    const first = props.startDate.substring(0, 10);
    const last = props.endDate.substring(0, 10);
    if (row.year === first.substring(0, 4) && first > `${row.year}-01-01`) return 'Partial period';
    return row.year === last.substring(0, 4) && last < `${row.year}-12-31` ? 'YTD' : 'Year return';
}
function monthDescription(row: ReturnYear, month: number): string {
    const key = `${row.year}-${String(month + 1).padStart(2, '0')}`;
    const benchmark = props.benchmarkReturns[key];
    let description = `${months[month]} ${row.year}: strategy ${percent(row.months[month])}`;
    if (props.benchmarkName) description += ` · ${props.benchmarkName} ${benchmark === undefined ? 'unavailable' : percent(benchmark)}`;
    if (key === props.startDate.substring(0, 7)) description += ` · From ${formatDate(props.startDate)}`;
    if (key === props.endDate.substring(0, 7)) description += ` · Through ${formatDate(props.endDate)}`;
    return description;
}
</script>
