<template>
    <Disclosure v-slot="{ open }" as="div" :default-open="defaultOpen" class="mt-3 overflow-hidden rounded-lg border border-gray-200">
        <DisclosureButton
            class="flex min-h-12 w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-left hover:bg-gray-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600 sm:px-6"
        >
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-900">{{ group.title }}</span>
                <span class="mt-0.5 block text-xs/5 break-words text-gray-600">{{ group.summary }}</span>
            </span>
            <ChevronDownIcon aria-hidden="true" class="size-5 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" />
        </DisclosureButton>
        <DisclosurePanel class="bg-white p-4 sm:p-6"><slot /></DisclosurePanel>
    </Disclosure>
</template>

<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import type { ScreenRuleGroup } from '@/utils/screenRules';

defineProps<{ group: ScreenRuleGroup; defaultOpen?: boolean }>();
</script>
