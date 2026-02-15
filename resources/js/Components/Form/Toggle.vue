<template>
    <SwitchGroup as="div" class="flex items-center">
        <Switch
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
            :class="[
                modelValue ? 'bg-purple-600' : 'bg-gray-200',
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-purple-600 focus:ring-offset-2',
            ]"
        >
            <span
                aria-hidden="true"
                :class="[
                    modelValue ? 'translate-x-5' : 'translate-x-0',
                    'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                ]"
            />
        </Switch>
        <SwitchLabel as="span" class="ml-3 text-sm">
            <span class="font-medium text-gray-900">{{ label }}</span>
        </SwitchLabel>
    </SwitchGroup>
</template>

<script setup lang="ts">
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';

defineProps<{
    modelValue: boolean;
    label: string;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>
