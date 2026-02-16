<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-300 text-sm">
            <thead>
                <tr>
                    <th class="whitespace-nowrap px-3 py-3 text-left font-semibold text-gray-900">Year</th>
                    <th
                        v-for="m in monthLabels"
                        :key="m"
                        class="whitespace-nowrap px-3 py-3 text-right font-semibold text-gray-900"
                    >
                        {{ m }}
                    </th>
                    <th class="whitespace-nowrap px-3 py-3 text-right font-semibold text-gray-900">Year</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr v-for="row in years" :key="row.year">
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-700">{{ row.year }}</td>
                    <td
                        v-for="m in 12"
                        :key="m"
                        class="whitespace-nowrap px-3 py-2 text-right"
                        :class="cellClass(row.months[m])"
                    >
                        {{ formatReturn(row.months[m]) }}
                    </td>
                    <td
                        class="whitespace-nowrap px-3 py-2 text-right font-medium"
                        :class="cellClass(row.yearReturn)"
                    >
                        {{ formatReturn(row.yearReturn) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup lang="ts">
interface YearRow {
    year: number;
    months: Record<number, number | null>;
    yearReturn: number | null;
}

defineProps<{
    years: YearRow[];
}>();

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function formatReturn(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '-';
    }
    const sign = value > 0 ? '+' : '';
    return `${sign}${value.toFixed(1)}%`;
}

function cellClass(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'text-gray-400';
    }
    if (value >= 5) {
        return 'bg-green-100 text-green-800';
    }
    if (value > 0) {
        return 'bg-green-50 text-green-700';
    }
    if (value <= -5) {
        return 'bg-red-100 text-red-800';
    }
    return 'bg-red-50 text-red-700';
}
</script>
