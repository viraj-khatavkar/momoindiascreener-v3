<template>
    <div>
        <Head title="Create Blog Post" />
        <PageHeader description="Create a new blog post.">
            Create Blog Post
        </PageHeader>

        <form @submit.prevent="store">
            <div class="space-y-6">
                <TextInput
                    v-model="form.title"
                    label="Title"
                    name="title"
                    :error="form.errors.title"
                />
                <TextInput
                    v-model="form.excerpt"
                    label="Excerpt"
                    name="excerpt"
                    :error="form.errors.excerpt"
                />
                <TextInput
                    v-model="form.featured_image"
                    label="Featured Image URL"
                    name="featured_image"
                    :error="form.errors.featured_image"
                />
                <TextInput
                    v-model="form.published_at"
                    type="datetime-local"
                    label="Published At"
                    name="published_at"
                    :error="form.errors.published_at"
                />
                <div class="flex gap-8">
                    <Toggle
                        :model-value="form.is_published"
                        label="Published"
                        @update:model-value="form.is_published = $event"
                    />
                    <Toggle
                        :model-value="form.is_paid"
                        label="Paid"
                        @update:model-value="form.is_paid = $event"
                    />
                </div>
            </div>
            <div class="mt-6">
                <MarkdownEditor
                    v-model="form.content"
                    label="Content"
                    :error="form.errors.content"
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-6 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Create Blog Post
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Toggle from '@/Components/Form/Toggle.vue';
import MarkdownEditor from '@/Components/Form/MarkdownEditor.vue';

const form = useForm({
    title: '',
    content: '',
    excerpt: '',
    featured_image: '',
    published_at: '',
    is_published: false,
    is_paid: true,
});

function store() {
    form.post('/admin/blogs');
}
</script>
