<template>
    <section class="mt-12 border-t border-gray-200 pt-8">
        <h2 class="text-xl font-bold text-gray-900">Comments</h2>

        <div v-if="currentUser" class="mt-6">
            <CommentForm :blog-slug="blogSlug" />
        </div>
        <p v-else class="mt-4 text-sm text-gray-500">
            <Link
                href="/login"
                class="text-purple-600 hover:text-purple-500"
            >
                Sign in
            </Link>
            to leave a comment.
        </p>

        <InfiniteScroll data="comments" preserve-url only-next>
            <div class="mt-8 space-y-6">
                <CommentItem
                    v-for="comment in comments.data"
                    :key="comment.id"
                    :comment="comment"
                    :blog-slug="blogSlug"
                />
            </div>

            <template #next="{ loading }">
                <div
                    v-if="loading"
                    class="py-4 text-center text-sm text-gray-500"
                >
                    Loading more comments...
                </div>
            </template>
        </InfiniteScroll>

        <p
            v-if="!comments.data.length"
            class="mt-6 text-sm text-gray-500"
        >
            No comments yet. Be the first to comment!
        </p>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { InfiniteScroll, Link, usePage } from '@inertiajs/vue3';
import CommentForm from './CommentForm.vue';
import CommentItem from './CommentItem.vue';
import type { Comment } from '@/types/app/Models/Comment';

defineProps<{
    comments: {
        data: Comment[];
    };
    blogSlug: string;
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user);
</script>
