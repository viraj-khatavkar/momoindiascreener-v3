<template>
    <div :class="isReply ? 'ml-8 border-l-2 border-gray-100 pl-4' : ''">
        <div class="flex items-start gap-3">
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-purple-100 text-sm font-semibold text-purple-600"
            >
                {{ comment.user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-900">
                        {{ comment.user.name }}
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ formatRelativeDate(comment.created_at) }}
                    </span>
                </div>
                <div class="mt-1">
                    <MdPreview
                        :model-value="comment.body"
                        class="comment-preview"
                    />
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <button
                        v-if="currentUser && !isReply"
                        type="button"
                        class="cursor-pointer text-xs font-medium text-gray-500 hover:text-purple-600"
                        @click="showReplyForm = !showReplyForm"
                    >
                        {{ showReplyForm ? 'Cancel' : 'Reply' }}
                    </button>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="cursor-pointer text-xs font-medium text-gray-500 hover:text-red-600"
                        @click="deleteComment"
                    >
                        Delete
                    </button>
                </div>
                <div v-if="showReplyForm" class="mt-3">
                    <CommentForm
                        :blog-slug="blogSlug"
                        :parent-id="comment.id"
                        label="Reply"
                        submit-label="Post Reply"
                        @submitted="showReplyForm = false"
                    />
                </div>
            </div>
        </div>

        <div v-if="comment.replies?.length" class="mt-4 space-y-4">
            <CommentItem
                v-for="reply in comment.replies"
                :key="reply.id"
                :comment="reply"
                :blog-slug="blogSlug"
                :is-reply="true"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { MdPreview } from 'md-editor-v3';
import { formatRelativeDate } from '@/utils';
import CommentForm from './CommentForm.vue';
import type { Comment } from '@/types/app/Models/Comment';

const props = withDefaults(
    defineProps<{
        comment: Comment;
        blogSlug: string;
        isReply?: boolean;
    }>(),
    {
        isReply: false,
    },
);

const page = usePage();
const showReplyForm = ref(false);

const currentUser = computed(
    () => page.props.auth.user as { id: number; is_admin: boolean } | null,
);

const canDelete = computed(
    () =>
        currentUser.value?.id === props.comment.user_id ||
        currentUser.value?.is_admin,
);

function deleteComment() {
    router.delete(`/comments/${props.comment.id}`, {
        preserveScroll: true,
    });
}
</script>

<style>
.comment-preview .md-editor-preview-wrapper {
    padding: 0;
}

.comment-preview .md-editor-preview {
    font-size: 0.875rem;
}
</style>
