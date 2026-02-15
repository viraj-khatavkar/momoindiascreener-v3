<template>
    <footer class="bg-white">
        <div class="mx-auto max-w-7xl overflow-hidden px-6 py-20 sm:py-24 lg:px-8">
            <nav aria-label="Footer" class="-mb-6 flex flex-wrap justify-center gap-x-12 gap-y-3 text-sm/6">
                <Link
                    v-for="item in footerNavigation"
                    :key="item.name"
                    :href="item.href"
                    class="text-gray-600 hover:text-gray-900"
                >
                    {{ item.name }}
                </Link>
            </nav>
            <div class="mt-16 flex justify-center gap-x-10">
                <a
                    v-for="item in socialNavigation"
                    :key="item.name"
                    :href="item.href"
                    target="_blank"
                    class="text-gray-600 hover:text-gray-800"
                >
                    <span class="sr-only">{{ item.name }}</span>
                    <component :is="item.icon" aria-hidden="true" class="size-6" />
                </a>
            </div>
            <p class="mt-10 text-center text-sm/6 text-gray-600">
                &copy; {{ currentYear }} Khatavkar Technologies Private Limited. All rights reserved.
            </p>
        </div>
    </footer>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { h } from 'vue';

const currentYear = dayjs().format('YYYY');

const footerNavigation = [
    { name: 'About', href: '/about' },
    { name: 'Privacy Policy', href: '/privacy-policy' },
    { name: 'Refund Policy', href: '/refund-policy' },
    { name: 'Terms', href: '/terms-conditions' },
];

const XIcon = {
    render() {
        return h('svg', { fill: 'currentColor', viewBox: '0 0 24 24' }, [
            h('path', {
                d: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
            }),
        ]);
    },
};

const YouTubeIcon = {
    render() {
        return h('svg', { fill: 'currentColor', viewBox: '0 0 24 24' }, [
            h('path', {
                'fill-rule': 'evenodd',
                d: 'M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.746 22 12 22 12s0 3.255-.418 4.814a2.504 2.504 0 0 1-1.768 1.768c-1.56.419-7.814.419-7.814.419s-6.255 0-7.814-.419a2.505 2.505 0 0 1-1.768-1.768C2 15.255 2 12 2 12s0-3.255.417-4.814a2.507 2.507 0 0 1 1.768-1.768C5.744 5 11.998 5 11.998 5s6.255 0 7.814.418ZM15.194 12 10 15V9l5.194 3Z',
                'clip-rule': 'evenodd',
            }),
        ]);
    },
};

const socialNavigation = [
    { name: 'X', href: 'https://x.com/virajkhatavkar', icon: XIcon },
    { name: 'YouTube', href: 'https://www.youtube.com/@virajkhatavkar', icon: YouTubeIcon },
];
</script>
