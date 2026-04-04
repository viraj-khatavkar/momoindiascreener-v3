<template>
    <TransitionRoot :show="open" as="template">
        <Dialog class="relative z-50" @close="$emit('close')">
            <TransitionChild
                as="template"
                enter="ease-out duration-200"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-150"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/80" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div
                    class="flex min-h-full items-center justify-center p-4"
                    @click.self="$emit('close')"
                >
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="ease-in duration-150"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="relative max-h-[90vh] max-w-[90vw]">
                            <div
                                class="absolute -top-10 right-0 flex items-center gap-2"
                            >
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md bg-white/10 p-1.5 text-white hover:bg-white/20"
                                    @click="zoomOut"
                                >
                                    <MinusIcon class="h-5 w-5" />
                                </button>
                                <span class="min-w-[3rem] text-center text-sm text-white">
                                    {{ Math.round(scale * 100) }}%
                                </span>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md bg-white/10 p-1.5 text-white hover:bg-white/20"
                                    @click="zoomIn"
                                >
                                    <PlusIcon class="h-5 w-5" />
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md bg-white/10 p-1.5 text-white hover:bg-white/20"
                                    @click="resetZoom"
                                >
                                    <ArrowsPointingOutIcon class="h-5 w-5" />
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-md bg-white/10 p-1.5 text-white hover:bg-white/20"
                                    @click="$emit('close')"
                                >
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>
                            <div class="overflow-auto max-h-[85vh] max-w-[90vw]">
                                <img
                                    :src="src"
                                    :alt="alt"
                                    class="block transition-transform duration-200"
                                    :style="{ transform: `scale(${scale})`, transformOrigin: 'top left' }"
                                />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogPanel,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import {
    ArrowsPointingOutIcon,
    MinusIcon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps<{
    open: boolean;
    src: string;
    alt: string;
}>();

defineEmits<{
    close: [];
}>();

const scale = ref(1);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            scale.value = 1;
        }
    },
);

function zoomIn() {
    scale.value = Math.min(scale.value + 0.25, 4);
}

function zoomOut() {
    scale.value = Math.max(scale.value - 0.25, 0.25);
}

function resetZoom() {
    scale.value = 1;
}
</script>
