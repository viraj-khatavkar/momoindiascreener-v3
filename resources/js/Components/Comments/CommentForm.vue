<template>
    <form @submit.prevent="submit">
        <MarkdownEditor
            v-model="form.body"
            :label="label"
            :error="form.errors.body"
            height="200px"
        />
        <button
            type="submit"
            :disabled="form.processing"
            class="mt-3 cursor-pointer rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
        >
            {{ submitLabel }}
        </button>
    </form>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import MarkdownEditor from '@/Components/Form/MarkdownEditor.vue';

const props = withDefaults(
    defineProps<{
        blogSlug: string;
        parentId?: number | null;
        label?: string;
        submitLabel?: string;
    }>(),
    {
        parentId: null,
        label: 'Comment',
        submitLabel: 'Post Comment',
    },
);

const emit = defineEmits<{
    submitted: [];
}>();

const form = useForm({
    body: '',
    parent_id: props.parentId,
});

function submit() {
    form.post(`/blogs/${props.blogSlug}/comments`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body');
            emit('submitted');
        },
    });
}
</script>
