<template>
    <div>
        <label class="block text-sm/6 font-medium text-gray-900">
            {{ label }}
        </label>
        <div class="mt-2">
            <MdEditor
                v-model="model"
                language="en-US"
                :preview="true"
                :toolbars="toolbars"
                :style="{ height: height ?? '500px' }"
            />
        </div>
        <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
    </div>
</template>

<script setup lang="ts">
import { MdEditor } from 'md-editor-v3';
import 'md-editor-v3/lib/style.css';

defineProps<{
    label: string;
    error?: string;
    height?: string;
}>();

const model = defineModel<string>();

const toolbars = [
    'bold',
    'italic',
    'strikeThrough',
    '-',
    'title',
    'quote',
    'unorderedList',
    'orderedList',
    '-',
    'link',
    'image',
    'code',
    'codeRow',
    '-',
    'preview',
];
</script>
