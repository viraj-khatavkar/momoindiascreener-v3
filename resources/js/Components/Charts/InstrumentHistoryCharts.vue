<template>
    <TabGroup as="div" class="min-w-0">
        <TabList aria-label="Instrument history charts" class="mb-5 flex gap-1 overflow-x-auto border-b border-gray-200">
            <Tab v-for="label in ['Price', ...metrics.map((metric) => metric.label)]" :key="label" v-slot="{ selected }" as="template">
                <button
                    type="button"
                    class="min-h-12 shrink-0 cursor-pointer border-b-2 px-3 text-sm font-medium whitespace-nowrap focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-5"
                    :class="selected ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-900'"
                >
                    {{ label }}
                </button>
            </Tab>
        </TabList>
        <TabPanels>
            <TabPanel :unmount="false" class="min-w-0 focus-visible:outline-2 focus-visible:outline-purple-600">
                <InstrumentPriceChart v-model:time-range="selectedTimeRange" :price-history="history" />
                <p class="mt-4 border-t border-gray-100 pt-3 text-xs leading-5 text-gray-500">
                    Adjusted prices in ₹. Move over the chart to inspect values. Use Indicators to add moving averages and other measures.
                </p>
            </TabPanel>
            <TabPanel v-for="metric in metrics" :key="metric.key" class="min-w-0 focus-visible:outline-2 focus-visible:outline-purple-600">
                <InstrumentMetricChart v-model:time-range="selectedTimeRange" :history="history" :metric="metric" />
            </TabPanel>
        </TabPanels>
    </TabGroup>
</template>

<script setup lang="ts">
import InstrumentMetricChart from '@/Components/Charts/InstrumentMetricChart.vue';
import InstrumentPriceChart from '@/Components/Charts/InstrumentPriceChart.vue';
import type { InstrumentHistoryMetric, InstrumentHistoryRecord } from '@/types/InstrumentHistoryRecord';
import type { ChartTimeRangeKey } from '@/utils/chartTimeRanges';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue';
import { computed, ref } from 'vue';

const props = defineProps<{ history: InstrumentHistoryRecord[]; isEtf: boolean }>();
const selectedTimeRange = ref<ChartTimeRangeKey>('1y');

const metrics = computed<InstrumentHistoryMetric[]>(() => [
    {
        key: 'absolute_return_one_year',
        label: '1Y return',
        title: 'Rolling 1-year return',
        color: '#2563eb',
        unit: 'percent',
        description: 'Return over the preceding year, calculated for each trading day.',
    },
    ...(!props.isEtf
        ? ([
              {
                  key: 'price_to_earnings',
                  label: 'P/E',
                  title: 'Price to earnings',
                  color: '#059669',
                  unit: 'number',
                  description: 'Daily P/E values. Missing values are excluded from the median.',
              },
              {
                  key: 'marketcap',
                  label: 'Market cap',
                  title: 'Market capitalisation',
                  color: '#d97706',
                  unit: 'crore',
                  description: 'Daily market capitalisation, in ₹ crore.',
              },
          ] satisfies InstrumentHistoryMetric[])
        : []),
    {
        key: 'rsi_one_year',
        label: '1Y RSI',
        title: '1-year RSI',
        color: '#e11d48',
        unit: 'number',
        description: 'The stored 1-year relative strength index, on a scale from 0 to 100.',
    },
]);
</script>
