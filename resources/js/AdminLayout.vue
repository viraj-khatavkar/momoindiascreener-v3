<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <TransitionRoot as="template" :show="sidebarOpen">
            <Dialog class="relative z-50 lg:hidden" @close="sidebarOpen = false">
                <TransitionChild
                    as="template"
                    enter="transition-opacity ease-linear duration-200"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="transition-opacity ease-linear duration-200"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-slate-200/70 backdrop-blur-sm" />
                </TransitionChild>

                <div class="fixed inset-0 flex">
                    <TransitionChild
                        as="template"
                        enter="transition ease-out duration-200"
                        enter-from="-translate-x-full"
                        enter-to="translate-x-0"
                        leave="transition ease-in duration-200"
                        leave-from="translate-x-0"
                        leave-to="-translate-x-full"
                    >
                        <DialogPanel class="relative flex w-full max-w-72 flex-1">
                            <div class="flex grow flex-col overflow-y-auto border-r border-slate-200 bg-white px-4 pb-5 shadow-2xl">
                                <div class="flex h-18 shrink-0 items-center justify-between">
                                    <Link href="/admin" class="flex flex-col items-start gap-1 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-700" prefetch>
                                        <BrandLogo class="w-52" />
                                        <span class="text-xs text-slate-500">Admin workspace</span>
                                    </Link>
                                    <button
                                        type="button"
                                        class="flex size-10 cursor-pointer items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-950"
                                        @click="sidebarOpen = false"
                                    >
                                        <span class="sr-only">Close admin menu</span>
                                        <XMarkIcon class="size-5" aria-hidden="true" />
                                    </button>
                                </div>

                                <AdminNavigation @navigate="sidebarOpen = false" />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-slate-200 bg-white shadow-sm lg:flex">
            <div class="flex h-20 shrink-0 items-center px-5">
                <Link href="/admin" class="flex flex-col items-start gap-1 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-700" prefetch>
                    <BrandLogo class="w-52" />
                    <span class="text-xs text-slate-500">Admin workspace</span>
                </Link>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-3 pb-5">
                <AdminNavigation />
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                <button
                    type="button"
                    class="-ml-2 flex size-10 cursor-pointer items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-950 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <span class="sr-only">Open admin menu</span>
                    <Bars3Icon class="size-6" aria-hidden="true" />
                </button>

                <div class="ml-3 min-w-0 lg:ml-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ currentPageName }}
                    </p>
                    <p class="hidden text-xs text-slate-500 sm:block">Admin workspace</p>
                </div>

                <div class="ml-auto flex items-center gap-2 sm:gap-4">
                    <Link
                        href="/"
                        class="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-950 sm:flex"
                    >
                        <ArrowLeftIcon class="size-4" aria-hidden="true" />
                        View site
                    </Link>
                    <div class="h-6 w-px bg-slate-200" aria-hidden="true" />
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-700">
                            {{ userInitials }}
                        </span>
                        <div class="hidden min-w-0 sm:block">
                            <p class="max-w-40 truncate text-sm font-semibold text-slate-900">
                                {{ page.props.auth.user?.name }}
                            </p>
                            <p class="text-xs text-slate-500">Administrator</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <SuccessAlert v-if="page.props.flash.success" class="mb-6">
                        {{ page.props.flash.success }}
                    </SuccessAlert>
                    <ErrorAlert v-if="page.props.flash.error" class="mb-6">
                        {{ page.props.flash.error }}
                    </ErrorAlert>
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue';
import {
    ArrowLeftIcon,
    ArrowLeftStartOnRectangleIcon,
    ArrowsRightLeftIcon,
    BanknotesIcon,
    Bars3Icon,
    CircleStackIcon,
    NewspaperIcon,
    PlayCircleIcon,
    ShoppingBagIcon,
    Squares2X2Icon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h, ref } from 'vue';
import ErrorAlert from './Components/Alerts/ErrorAlert.vue';
import SuccessAlert from './Components/Alerts/SuccessAlert.vue';
import BrandLogo from './Components/BrandLogo.vue';

interface NavigationItem {
    name: string;
    href: string;
    icon: typeof Squares2X2Icon;
    current: boolean;
}

const page = usePage();
const sidebarOpen = ref(false);

const navigationSections = computed(() => [
    {
        name: 'Overview',
        items: [navigationItem('Dashboard', '/admin', Squares2X2Icon, page.url === '/admin')],
    },
    {
        name: 'Customers',
        items: [navigationItem('Users', '/admin/users', UsersIcon), navigationItem('Orders', '/admin/orders', ShoppingBagIcon)],
    },
    {
        name: 'Operations',
        items: [
            navigationItem('Daily processes', '/admin/processes', PlayCircleIcon, ['/admin/processes', '/admin/process-runs']),
            navigationItem('NSE files', '/admin/nse-files', CircleStackIcon),
            navigationItem('ETF index mappings', '/admin/market-index-aliases', ArrowsRightLeftIcon),
            navigationItem('Corporate actions', '/admin/corporate-actions', BanknotesIcon),
        ],
    },
    {
        name: 'Content',
        items: [navigationItem('Blog posts', '/admin/blogs', NewspaperIcon)],
    },
]);

const allNavigationItems = computed(() => navigationSections.value.flatMap((section) => section.items));

const currentPageName = computed(() => allNavigationItems.value.find((item) => item.current)?.name ?? 'Admin');

const userInitials = computed(() => {
    const name = page.props.auth.user?.name?.trim();

    if (!name) {
        return 'A';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
});

function navigationItem(name: string, href: string, icon: typeof Squares2X2Icon, paths: string | string[] | boolean = href): NavigationItem {
    const current = typeof paths === 'boolean' ? paths : (Array.isArray(paths) ? paths : [paths]).some((path) => page.url.startsWith(path));

    return { name, href, icon, current };
}

const AdminNavigation = defineComponent({
    emits: ['navigate'],
    setup(_, { emit }) {
        return () =>
            h('nav', { class: 'flex flex-1 flex-col pt-4' }, [
                h(
                    'div',
                    { class: 'flex flex-1 flex-col gap-6' },
                    navigationSections.value.map((section) =>
                        h('div', { key: section.name }, [
                            h(
                                'p',
                                {
                                    class: 'px-3 text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-slate-500',
                                },
                                section.name,
                            ),
                            h(
                                'ul',
                                { class: 'mt-2 space-y-1', role: 'list' },
                                section.items.map((item) =>
                                    h('li', { key: item.name }, [
                                        h(
                                            Link,
                                            {
                                                href: item.href,
                                                prefetch: true,
                                                class: [
                                                    item.current
                                                        ? 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-100'
                                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
                                                    'group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                                                ],
                                                onClick: () => emit('navigate'),
                                            },
                                            () => [
                                                h(item.icon, {
                                                    class: [
                                                        item.current ? 'text-purple-600' : 'text-slate-400 group-hover:text-slate-600',
                                                        'size-5 shrink-0',
                                                    ],
                                                    'aria-hidden': 'true',
                                                }),
                                                item.name,
                                            ],
                                        ),
                                    ]),
                                ),
                            ),
                        ]),
                    ),
                ),
                h('div', { class: 'mt-6 border-t border-slate-200 pt-4' }, [
                    h(
                        Link,
                        {
                            href: '/',
                            class: 'group flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-950',
                            onClick: () => emit('navigate'),
                        },
                        () => [
                            h(ArrowLeftIcon, {
                                class: 'size-5 text-slate-400 group-hover:text-slate-600',
                                'aria-hidden': 'true',
                            }),
                            'Back to site',
                        ],
                    ),
                    h(
                        Link,
                        {
                            href: '/logout',
                            class: 'group mt-1 flex min-h-10 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-950',
                            onClick: () => emit('navigate'),
                        },
                        () => [
                            h(ArrowLeftStartOnRectangleIcon, {
                                class: 'size-5 text-slate-400 group-hover:text-slate-600',
                                'aria-hidden': 'true',
                            }),
                            'Sign out',
                        ],
                    ),
                ]),
            ]);
    },
});
</script>
