<template>
    <div class="mt-4" data-testid="returns-history">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[11px] leading-5 text-[#706b7d]">
                Latest month and year through <span class="font-medium text-[#252135]">{{ formatIndexDate(asOfDate) }}</span
                >.
            </p>
            <p class="text-xs text-[#706b7d]">All {{ years.length }} {{ years.length === 1 ? 'year' : 'years' }}</p>
        </div>

        <div class="hidden rounded-md border border-[#e5e2ea] lg:block" data-testid="returns-table">
            <table class="w-full table-fixed border-separate border-spacing-0 text-[11px] tabular-nums xl:text-xs">
                <caption class="sr-only">
                    Monthly percentage changes for all available years. The latest month and year end on
                    {{
                        formatIndexDate(asOfDate)
                    }}. A dash means unavailable.
                </caption>
                <colgroup>
                    <col class="w-14 xl:w-16" />
                    <col v-for="month in 12" :key="month" />
                    <col class="w-22 xl:w-24" />
                </colgroup>
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="sticky top-16 z-10 rounded-tl-md border-r border-b border-[#e5e2ea] bg-[#f7f6f9] px-2 py-3.5 text-left font-semibold text-[#706b7d] xl:px-3"
                        >
                            Year
                        </th>
                        <th
                            v-for="month in monthLabels"
                            :key="month"
                            scope="col"
                            class="sticky top-16 z-10 border-b border-[#e5e2ea] bg-[#f7f6f9] px-1 py-3.5 text-right font-medium text-[#706b7d] xl:px-2"
                        >
                            {{ month }}
                        </th>
                        <th
                            scope="col"
                            class="sticky top-16 z-10 rounded-tr-md border-b border-l border-[#e5e2ea] bg-[#f7f6f9] px-2 py-3.5 text-right font-semibold text-[#706b7d]"
                        >
                            Year / YTD
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in years" :key="row.year">
                        <th
                            scope="row"
                            class="border-r border-b border-[#eeebf2] bg-white px-2 py-3.5 text-left font-semibold text-[#252135] xl:px-3"
                        >
                            {{ row.year }}
                        </th>
                        <td
                            v-for="month in 12"
                            :key="month"
                            class="border-b border-[#eeebf2] px-1 py-3.5 text-right wrap-anywhere xl:px-2"
                            :class="cellClass(row.months[month])"
                            :title="cellLabel(row, month)"
                            :aria-label="cellLabel(row, month)"
                        >
                            {{ formatReturn(row.months[month])
                            }}<sup v-if="row.year === latestYear && month === latestMonth" class="ml-0.5 text-[9px]">*</sup>
                        </td>
                        <td
                            class="border-b border-l border-[#e5e2ea] px-2 py-3.5 text-right font-semibold wrap-anywhere"
                            :class="cellClass(row.yearReturn)"
                            :title="yearLabel(row)"
                            :aria-label="yearLabel(row)"
                        >
                            {{ formatReturn(row.yearReturn) }}<sup v-if="row.year === latestYear" class="ml-0.5 text-[9px]">*</sup>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-5 lg:hidden" data-testid="returns-year-grids">
            <section
                v-for="row in years"
                :key="row.year"
                :aria-labelledby="`returns-year-${row.year}`"
                class="rounded-md border border-[#e5e2ea] p-3 sm:p-4"
                data-testid="returns-year"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 :id="`returns-year-${row.year}`" class="text-base font-semibold">{{ row.year }}</h3>
                    <p class="text-xs text-[#706b7d]">
                        {{ row.year === latestYear ? 'Year to date' : 'Year return' }}
                        <span
                            class="ml-1 inline-block rounded px-2 py-1 font-semibold tabular-nums"
                            :class="cellClass(row.yearReturn)"
                            :title="yearLabel(row)"
                            >{{ formatReturn(row.yearReturn) }}<sup v-if="row.year === latestYear" class="ml-0.5 text-[9px]">*</sup></span
                        >
                    </p>
                </div>
                <dl class="grid grid-cols-3 gap-1.5 sm:grid-cols-6">
                    <div
                        v-for="month in 12"
                        :key="month"
                        class="min-w-0 rounded px-1.5 py-2 text-center"
                        :class="cellClass(row.months[month])"
                        :title="cellLabel(row, month)"
                    >
                        <dt class="text-[10px] text-[#706b7d]">{{ monthLabels[month - 1] }}</dt>
                        <dd class="mt-1 text-xs font-medium wrap-anywhere tabular-nums" :aria-label="cellLabel(row, month)">
                            {{ formatReturn(row.months[month])
                            }}<sup v-if="row.year === latestYear && month === latestMonth" class="ml-0.5 text-[9px]">*</sup>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="mt-3 flex flex-wrap justify-between gap-x-6 gap-y-2 text-[11px] leading-5 text-[#8c8498]">
            <p>* Through {{ formatIndexDate(asOfDate) }}. YTD means year to date.</p>
            <p><span class="text-[#24745e]">Positive</span> · <span class="text-[#b44854]">Negative</span> · — unavailable</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { formatIndexDate } from '@/utils/nseIndices';
interface YearRow {
    year: number;
    months: Record<number, number | null>;
    yearReturn: number | null;
    throughDate: string;
}
const props = defineProps<{ years: YearRow[]; asOfDate: string }>();
const latestYear = computed(() => Number(props.asOfDate.slice(0, 4)));
const latestMonth = computed(() => Number(props.asOfDate.slice(5, 7)));
const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function formatReturn(value: number | null | undefined): string {
    if (value == null) return '—';
    const sign = value > 0 ? '+' : value < 0 ? '−' : '';
    const decimals = value !== 0 && Math.abs(value) < 0.05 ? 2 : 1;
    return `${sign}${Math.abs(value).toFixed(decimals)}%`;
}
function cellClass(value: number | null | undefined): string {
    if (value == null) return 'bg-white text-[#aaa2b5]';
    if (value === 0) return 'bg-[#faf9fc] text-[#706b7d]';
    if (value >= 5) return 'bg-[#dcefe6] text-[#215e4a]';
    if (value > 0) return 'bg-[#f0f8f4] text-[#24745e]';
    if (value <= -5) return 'bg-[#f6dfe3] text-[#953c49]';
    return 'bg-[#fcf2f3] text-[#b44854]';
}
function cellLabel(row: YearRow, month: number): string {
    const period = `${monthLabels[month - 1]} ${row.year}`;
    const through = row.year === latestYear.value && month === latestMonth.value ? ` through ${formatIndexDate(props.asOfDate)}` : '';
    return `${period}${through}: ${row.months[month] == null ? 'Unavailable' : formatReturn(row.months[month])}`;
}
function yearLabel(row: YearRow): string {
    return `${row.year} return through ${formatIndexDate(row.throughDate || `${row.year}-12-31`)}: ${row.yearReturn == null ? 'Unavailable' : formatReturn(row.yearReturn)}`;
}
</script>
