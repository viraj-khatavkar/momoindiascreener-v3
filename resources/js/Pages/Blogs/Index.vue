<template>
    <div>
        <Head title="Blog" />
        <div class="mx-auto max-w-4xl">
            <div class="mb-10">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">
                    Blog
                </h1>
                <p class="mt-2 text-sm text-gray-500">
                    Insights, updates and ideas from the momoindiascreener
                    team.
                </p>
            </div>

            <div v-if="blogs.data.length" class="space-y-8">
                <Link
                    v-for="blog in blogs.data"
                    :key="blog.id"
                    :href="`/blogs/${blog.slug}`"
                    class="block rounded-lg border border-gray-200 bg-white p-6 transition hover:border-purple-300 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex items-center gap-2">
                                <span
                                    :class="[
                                        blog.is_paid
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-green-100 text-green-700',
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                    ]"
                                >
                                    {{ blog.is_paid ? 'Paid' : 'Free' }}
                                </span>
                                <span v-if="blog.published_at" class="text-xs text-gray-400">
                                    {{ formatDate(blog.published_at) }}
                                </span>
                            </div>
                            <h2
                                class="text-lg font-semibold text-gray-900"
                            >
                                {{ blog.title }}
                            </h2>
                            <p
                                v-if="blog.excerpt"
                                class="mt-1 text-sm text-gray-500"
                            >
                                {{ blog.excerpt }}
                            </p>
                        </div>
                        <img
                            v-if="blog.featured_image"
                            :src="blog.featured_image"
                            :alt="blog.title"
                            class="h-24 w-36 shrink-0 rounded-md object-cover"
                        />
                    </div>
                </Link>
            </div>
            <p v-else class="text-sm text-gray-500">
                No blog posts yet. Check back soon!
            </p>

            <Pagination :links="blogs.links" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import type { Blog } from '@/types/app/Models/Blog';

defineProps<{
    blogs: {
        data: Blog[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();
</script>
