<template>
    <SwitchGroup as="div" class="flex min-h-10 items-center gap-3 sm:min-h-6">
        <Switch
            :model-value="modelValue"
            :disabled="disabled"
            @update:model-value="$emit('update:modelValue', $event)"
            :class="[
                modelValue ? 'bg-purple-600' : 'bg-gray-200',
                'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-purple-600 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50',
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
        <SwitchLabel
            as="span"
            class="min-w-0 py-2 text-sm/6 font-medium sm:py-0"
            :class="disabled ? 'cursor-not-allowed text-gray-500' : 'cursor-pointer text-gray-900'"
            >{{ label }}</SwitchLabel
        >
    </SwitchGroup>
</template>

<script setup lang="ts">
import { Switch, SwitchGroup, SwitchLabel } from '@headlessui/vue';

defineProps<{
    modelValue: boolean;
    label: string;
    disabled?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: boolean];
}>();
</script>
