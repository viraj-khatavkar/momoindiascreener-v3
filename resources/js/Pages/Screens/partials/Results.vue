<template>
    <div>
        <!-- Results Header -->
        <div class="relative my-8">
            <div aria-hidden="true" class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300" />
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-2 text-sm font-bold text-gray-500">
                    {{ results.length }} results
                </span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div v-if="showActions" class="flex justify-end">
            <div class="mb-8 flex gap-4">
                <Link
                    :href="`/screens/${screen.id}/columns/edit`"
                    class="flex justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                >
                    Edit Columns
                </Link>
                <a
                    class="flex justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                    target="_blank"
                    :href="`/screens/${screen.id}/csv`"
                >
                    Export
                </a>
            </div>
        </div>

        <!-- Historical Date Warning -->
        <WarningAlert v-if="screen.apply_historical_date" class="mb-4">
            You have currently applied historical date filter.
        </WarningAlert>

        <!-- Date Info -->
        <PurpleAlert v-if="results.length > 0" class="mb-4">
            Results are shown for {{ formatDate(results[0].date) }}
        </PurpleAlert>

        <!-- Sorting Factor Info -->
        <InfoAlert class="mb-4">
            Sorting Factor Column's Value = {{ screenSortByDisplayName(screen.sort_by) }}
        </InfoAlert>

        <!-- Desktop Table -->
        <div class="hidden md:block">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead>
                        <tr>
                            <th
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                            >
                                #
                            </th>
                            <th
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                            >
                                Name
                            </th>
                            <th
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                            >
                                Symbol
                            </th>
                            <th
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                            >
                                Sorting Factor
                            </th>
                            <th
                                v-for="column in columns"
                                :key="column.name"
                                class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                            >
                                {{ column.display_name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template v-for="(item, index) in results" :key="item.id">
                            <!-- Repeat headers every 16 rows -->
                            <tr v-if="(index + 1) % 16 === 0">
                                <th
                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                >
                                    #
                                </th>
                                <th
                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                >
                                    Name
                                </th>
                                <th
                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                >
                                    Symbol
                                </th>
                                <th
                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                >
                                    Sorting Factor
                                </th>
                                <th
                                    v-for="column in columns"
                                    :key="`header-repeat-${column.name}`"
                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                >
                                    {{ column.display_name }}
                                </th>
                            </tr>
                            <tr>
                                <td
                                    class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"
                                >
                                    {{ index + 1 }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-4 text-sm"
                                >
                                    <Link
                                        :href="`/instruments/${item.symbol}`"
                                        class="text-purple-600 hover:text-purple-500 hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 hover:underline"
                                >
                                    {{ item.symbol }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"
                                >
                                    {{ (item as Record<string, any>)[screen.sort_by] }}
                                </td>
                                <td
                                    v-for="column in columns"
                                    :key="`${item.id}-${column.name}`"
                                    class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"
                                >
                                    {{ (item as Record<string, any>)[column.name] }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="md:hidden">
            <Disclosure
                v-for="(item, index) in results"
                :key="item.id"
                v-slot="{ open }"
                as="div"
                class="border-b border-gray-200"
            >
                <DisclosureButton
                    class="flex w-full items-center justify-between py-4 text-left"
                >
                    <span class="text-sm font-semibold text-gray-900">
                        {{ index + 1 }}: {{ item.symbol }}
                    </span>
                    <ChevronDownIcon
                        :class="[open ? 'rotate-180' : '', 'h-5 w-5 text-gray-500']"
                    />
                </DisclosureButton>
                <DisclosurePanel class="pb-4">
                    <div class="flex flex-col">
                        <div class="flex flex-row justify-between">
                            <span class="text-sm font-semibold">Name:</span>
                            <Link
                                :href="`/instruments/${item.symbol}`"
                                class="ml-2 text-sm text-purple-600 hover:text-purple-500 hover:underline"
                            >
                                {{ item.name }}
                            </Link>
                        </div>
                        <div class="flex flex-row justify-between">
                            <span class="text-sm font-semibold">Symbol:</span>
                            <span class="ml-2 text-sm">{{ item.symbol }}</span>
                        </div>
                        <div class="flex flex-row justify-between">
                            <span class="text-sm font-semibold">Sorting Factor:</span>
                            <span class="ml-2 text-sm">
                                {{ (item as Record<string, any>)[screen.sort_by] }}
                            </span>
                        </div>
                        <div
                            v-for="column in columns"
                            :key="column.name"
                            class="flex flex-row justify-between"
                        >
                            <span class="text-sm font-semibold">
                                {{ column.display_name }}:
                            </span>
                            <span class="ml-2 text-sm">
                                {{ (item as Record<string, any>)[column.name] }}
                            </span>
                        </div>
                    </div>
                </DisclosurePanel>
            </Disclosure>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { Link } from '@inertiajs/vue3';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import PurpleAlert from '@/Components/Alerts/PurpleAlert.vue';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
import type { Screen } from '@/types/app/Models/Screen';
import type { BacktestNseInstrumentPriceResource } from '@/types/app/Resources/BacktestNseInstrumentPriceResource';
import type { ScreenResultColumn } from '@/types/ScreenResultColumn';

withDefaults(
    defineProps<{
        screen: Screen;
        results: (BacktestNseInstrumentPriceResource & Record<string, any>)[];
        columns: ScreenResultColumn[];
        showActions?: boolean;
    }>(),
    {
        showActions: true,
    },
);

function formatDate(dateString: string) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>
