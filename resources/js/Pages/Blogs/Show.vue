<template>
    <div>
        <Head :title="blog.title" />
        <article class="mx-auto max-w-3xl">
            <div class="mb-8">
                <div class="mb-3 flex items-center gap-2">
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
                    <span v-if="blog.published_at" class="text-sm text-gray-400">
                        {{ formatDate(blog.published_at) }}
                    </span>
                </div>
                <h1
                    class="text-3xl font-bold tracking-tight text-gray-900"
                >
                    {{ blog.title }}
                </h1>
                <p
                    v-if="blog.excerpt"
                    class="mt-3 text-lg text-gray-500"
                >
                    {{ blog.excerpt }}
                </p>
            </div>

            <img
                v-if="blog.featured_image"
                :src="blog.featured_image"
                :alt="blog.title"
                class="mb-8 w-full cursor-pointer rounded-lg object-cover"
                @click="openImage(blog.featured_image!, blog.title)"
            />

            <div
                ref="contentRef"
                class="prose max-w-none prose-purple prose-img:cursor-pointer"
                v-html="contentHtml"
            />

            <CommentsSection :comments="comments" :blog-slug="blog.slug" />

            <div class="mt-12 border-t border-gray-200 pt-6">
                <Link
                    href="/blogs"
                    class="text-sm font-medium text-purple-600 hover:text-purple-500"
                >
                    &larr; Back to all posts
                </Link>
            </div>
        </article>

        <ImageModal
            :open="modalOpen"
            :src="modalSrc"
            :alt="modalAlt"
            @close="modalOpen = false"
        />
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { formatDate } from '@/utils';
import CommentsSection from '@/Components/Comments/CommentsSection.vue';
import ImageModal from '@/Components/ImageModal.vue';
import type { Blog } from '@/types/app/Models/Blog';
import type { Comment } from '@/types/app/Models/Comment';

defineProps<{
    blog: Blog;
    contentHtml: string;
    comments: {
        data: Comment[];
    };
}>();

const contentRef = ref<HTMLElement>();
const modalOpen = ref(false);
const modalSrc = ref('');
const modalAlt = ref('');

function openImage(src: string, alt: string) {
    modalSrc.value = src;
    modalAlt.value = alt;
    modalOpen.value = true;
}

function onContentClick(event: Event) {
    const target = event.target as HTMLElement;
    if (target.tagName === 'IMG') {
        const img = target as HTMLImageElement;
        openImage(img.src, img.alt || '');
    }
}

onMounted(() => {
    contentRef.value?.addEventListener('click', onContentClick);
});

onUnmounted(() => {
    contentRef.value?.removeEventListener('click', onContentClick);
});
</script>
