<template>
    <div>
        <Head title="Blogs" />
        <div class="flex items-start justify-between">
            <PageHeader description="Manage blog posts.">Blogs</PageHeader>
            <Link
                href="/admin/blogs/create"
                class="rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                New Blog Post
            </Link>
        </div>

        <form @submit.prevent="applyFilter">
            <div class="grid grid-cols-1 gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
                <TextInput
                    v-model="form.search"
                    label="Search"
                    name="search"
                    placeholder="Search by title..."
                />
            </div>
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-75"
            >
                Filter
            </button>
        </form>

        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th
                            class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900"
                        >
                            Title
                        </th>
                        <th
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Slug
                        </th>
                        <th
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Published
                        </th>
                        <th
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Paid
                        </th>
                        <th
                            class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                        >
                            Created
                        </th>
                        <th class="relative py-3.5 pl-3 pr-4">
                            <span class="sr-only">Edit</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="blog in blogs.data" :key="blog.id">
                        <td
                            class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900"
                        >
                            {{ blog.title }}
                        </td>
                        <td
                            class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"
                        >
                            {{ blog.slug }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                :class="[
                                    blog.is_published
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-700',
                                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                                ]"
                            >
                                {{ blog.is_published ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                            <span
                                :class="[
                                    blog.is_paid
                                        ? 'bg-amber-100 text-amber-700'
                                        : 'bg-blue-100 text-blue-700',
                                    'inline-flex rounded-full px-2 text-xs font-semibold leading-5',
                                ]"
                            >
                                {{ blog.is_paid ? 'Paid' : 'Free' }}
                            </span>
                        </td>
                        <td
                            class="whitespace-nowrap px-3 py-4 text-sm text-gray-500"
                        >
                            {{ formatDate(blog.created_at) }}
                        </td>
                        <td
                            class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium"
                        >
                            <Link
                                :href="`/admin/blogs/${blog.id}/edit`"
                                class="text-purple-600 hover:text-purple-900"
                            >
                                Edit
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="blogs.data.length === 0">
                        <td
                            colspan="6"
                            class="py-4 text-center text-sm text-gray-500"
                        >
                            No blog posts found.
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="blogs.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import type { Blog } from '@/types/app/Models/Blog';

const props = defineProps<{
    blogs: {
        data: Blog[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string | null;
    };
}>();

const form = useForm({
    search: props.filters.search ?? '',
});

function applyFilter() {
    form.get('/admin/blogs', { preserveState: true });
}
</script>
