<template>
    <Head title="Momo India Screener" />
    <div>
        <!-- Mobile sidebar -->
        <TransitionRoot as="template" :show="sidebarOpen">
            <Dialog class="relative z-50" @close="sidebarOpen = false">
                <TransitionChild
                    as="template"
                    enter="transition-opacity ease-linear duration-300"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="transition-opacity ease-linear duration-300"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-gray-900/80" />
                </TransitionChild>

                <div class="fixed inset-0 flex">
                    <TransitionChild
                        as="template"
                        enter="transition ease-in-out duration-300 transform"
                        enter-from="-translate-x-full"
                        enter-to="translate-x-0"
                        leave="transition ease-in-out duration-300 transform"
                        leave-from="translate-x-0"
                        leave-to="-translate-x-full"
                    >
                        <DialogPanel class="relative mr-16 flex w-full max-w-xs flex-1">
                            <TransitionChild
                                as="template"
                                enter="ease-in-out duration-300"
                                enter-from="opacity-0"
                                enter-to="opacity-100"
                                leave="ease-in-out duration-300"
                                leave-from="opacity-100"
                                leave-to="opacity-0"
                            >
                                <div class="absolute left-full top-0 flex w-16 justify-center pt-5">
                                    <button
                                        type="button"
                                        class="-m-2.5 cursor-pointer p-2.5"
                                        @click="sidebarOpen = false"
                                    >
                                        <span class="sr-only">Close sidebar</span>
                                        <XMarkIcon class="h-6 w-6 text-white" aria-hidden="true" />
                                    </button>
                                </div>
                            </TransitionChild>
                            <!-- Sidebar content -->
                            <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-white px-6 pb-4">
                                <div class="flex h-16 shrink-0 items-center">
                                    <img alt="momoindiascreener logo" src="/images/logo.png" />
                                </div>
                                <nav class="flex flex-1 flex-col">
                                    <ul role="list" class="flex flex-1 flex-col gap-y-7">
                                        <li>
                                            <ul role="list" class="-mx-2 space-y-1">
                                                <li v-for="item in navigation" :key="item.name">
                                                    <Link
                                                        :href="item.href"
                                                        :class="[
                                                            item.current
                                                                ? 'bg-gray-50 text-purple-600'
                                                                : 'text-gray-700 hover:bg-gray-50 hover:text-purple-600',
                                                            'group flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6',
                                                        ]"
                                                        @click="sidebarOpen = false"
                                                    >
                                                        <component
                                                            :is="item.icon"
                                                            :class="[
                                                                item.current
                                                                    ? 'text-purple-600'
                                                                    : 'text-gray-400 group-hover:text-purple-600',
                                                                'h-6 w-6 shrink-0',
                                                            ]"
                                                            aria-hidden="true"
                                                        />
                                                        {{ item.name }}
                                                    </Link>
                                                </li>
                                            </ul>
                                        </li>
                                        <li v-if="page.props.auth.user?.is_admin">
                                            <div
                                                class="text-xs font-semibold leading-6 text-gray-400"
                                            >
                                                Admin
                                            </div>
                                            <ul role="list" class="-mx-2 mt-2 space-y-1">
                                                <li
                                                    v-for="item in adminNavigation"
                                                    :key="item.name"
                                                >
                                                    <Link
                                                        :href="item.href"
                                                        :class="[
                                                            item.current
                                                                ? 'bg-gray-50 text-purple-600'
                                                                : 'text-gray-700 hover:bg-gray-50 hover:text-purple-600',
                                                            'group flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6',
                                                        ]"
                                                        @click="sidebarOpen = false"
                                                    >
                                                        <span
                                                            :class="[
                                                                item.current
                                                                    ? 'border-purple-600 text-purple-600'
                                                                    : 'border-gray-200 text-gray-400 group-hover:border-purple-600 group-hover:text-purple-600',
                                                                'flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border bg-white text-[0.625rem] font-medium',
                                                            ]"
                                                        >
                                                            {{ item.initial }}
                                                        </span>
                                                        {{ item.name }}
                                                    </Link>
                                                </li>
                                            </ul>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </TransitionRoot>

        <div class="flex h-screen flex-col justify-between">
            <div>
                <!-- Sticky header -->
                <div
                    class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-xs sm:gap-x-6 sm:px-6"
                >
                    <button
                        type="button"
                        class="-m-2.5 cursor-pointer p-2.5 text-gray-700"
                        @click="sidebarOpen = true"
                    >
                        <span class="sr-only">Open sidebar</span>
                        <Bars3Icon class="h-6 w-6" aria-hidden="true" />
                    </button>

                    <!-- Separator -->
                    <div class="h-6 w-px bg-gray-200" aria-hidden="true" />

                    <div class="flex flex-1 gap-x-4 self-stretch">
                        <InstrumentSearch />
                        <div class="flex min-w-fit items-center gap-x-4">
                            <!-- Separator -->
                            <div
                                class="hidden xl:block xl:h-6 xl:w-px xl:bg-gray-200"
                                aria-hidden="true"
                            />

                            <!-- Profile dropdown -->
                            <Menu as="div" class="relative">
                                <MenuButton class="-m-1.5 flex items-center p-1.5">
                                    <span class="sr-only">Open user menu</span>
                                    <span class="hidden xl:flex xl:items-center">
                                        <span
                                            class="ml-4 text-sm font-semibold leading-6 text-gray-900"
                                            aria-hidden="true"
                                        >
                                            {{
                                                $page.props.auth.user
                                                    ? $page.props.auth.user.name
                                                    : 'Welcome, Guest'
                                            }}
                                        </span>
                                        <ChevronDownIcon
                                            class="ml-2 h-5 w-5 text-gray-400"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </MenuButton>
                                <transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="transform opacity-0 scale-95"
                                    enter-to-class="transform opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="transform opacity-100 scale-100"
                                    leave-to-class="transform opacity-0 scale-95"
                                >
                                    <MenuItems
                                        class="absolute right-0 z-10 mt-2.5 w-32 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-hidden"
                                    >
                                        <template v-if="$page.props.auth.user">
                                            <MenuItem
                                                v-for="item in authUserNavigation"
                                                :key="item.name"
                                                v-slot="{ active }"
                                            >
                                                <Link
                                                    :href="item.href"
                                                    :class="[
                                                        active ? 'bg-gray-50' : '',
                                                        'block px-3 py-1 text-sm leading-6 text-gray-900',
                                                    ]"
                                                >
                                                    {{ item.name }}
                                                </Link>
                                            </MenuItem>
                                        </template>
                                        <template v-else>
                                            <MenuItem
                                                v-for="item in guestUserNavigation"
                                                :key="item.name"
                                                v-slot="{ active }"
                                            >
                                                <Link
                                                    :href="item.href"
                                                    :class="[
                                                        active ? 'bg-gray-50' : '',
                                                        'block px-3 py-1 text-sm leading-6 text-gray-900',
                                                    ]"
                                                >
                                                    {{ item.name }}
                                                </Link>
                                            </MenuItem>
                                        </template>
                                    </MenuItems>
                                </transition>
                            </Menu>
                        </div>
                    </div>
                </div>

                <main class="py-10">
                    <div class="px-4 sm:px-6 xl:px-8">
                        <SuccessAlert v-if="$page.props.flash.success" class="mb-4">
                            {{ $page.props.flash.success }}
                        </SuccessAlert>
                        <ErrorAlert v-if="$page.props.flash.error" class="mb-4">
                            {{ $page.props.flash.error }}
                        </ErrorAlert>
                        <slot />
                    </div>
                </main>
            </div>
            <Footer />
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    Dialog,
    DialogPanel,
    Menu,
    MenuButton,
    MenuItem,
    MenuItems,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue';
import {
    AdjustmentsHorizontalIcon,
    ArrowLeftStartOnRectangleIcon,
    BeakerIcon,
    Bars3Icon,
    ChartBarIcon,
    ChartBarSquareIcon,
    CurrencyDollarIcon,
    DocumentTextIcon,
    HomeIcon,
    KeyIcon,
    LifebuoyIcon,
    QuestionMarkCircleIcon,
    SunIcon,
    UserIcon,
    VideoCameraIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ErrorAlert from './Components/Alerts/ErrorAlert.vue';
import Footer from './Components/Footer.vue';
import InstrumentSearch from './Components/InstrumentSearch.vue';
import SuccessAlert from './Components/Alerts/SuccessAlert.vue';

const page = usePage();
const sidebarOpen = ref(false);

const navigation = computed(() => {
    const items = [
        { name: 'Home', href: '/', icon: HomeIcon, current: page.url === '/' },
        {
            name: 'Indices',
            href: '/dashboard',
            icon: ChartBarSquareIcon,
            current: page.url.startsWith('/dashboard') || page.url.startsWith('/nse-index'),
        },
        {
            name: 'Screens',
            href: '/screens',
            icon: AdjustmentsHorizontalIcon,
            current: page.url.startsWith('/screens'),
        },
        {
            name: 'Backtests',
            href: '/backtests',
            icon: BeakerIcon,
            current: page.url.startsWith('/backtests'),
        },
        {
            name: 'Market Health',
            href: '/market-health',
            icon: ChartBarIcon,
            current: page.url.startsWith('/market-health'),
        },
        {
            name: 'Profile',
            href: '/profile',
            icon: UserIcon,
            current: page.url.startsWith('/profile'),
        },
        {
            name: 'Change Password',
            href: '/change-password',
            icon: KeyIcon,
            current: page.url.startsWith('/change-password'),
        },
        {
            name: 'Pricing',
            href: '/pricing',
            icon: CurrencyDollarIcon,
            current: page.url.startsWith('/pricing'),
        },
        {
            name: 'Invoices',
            href: '/invoices',
            icon: DocumentTextIcon,
            current: page.url.startsWith('/invoices'),
        },
        {
            name: 'FAQ',
            href: '/faq',
            icon: QuestionMarkCircleIcon,
            current: page.url.startsWith('/faq'),
        },
        {
            name: 'AMA Recording',
            href: '/ama-recording',
            icon: VideoCameraIcon,
            current: page.url.startsWith('/ama-recording'),
        },
        {
            name: 'Support',
            href: '/support',
            icon: LifebuoyIcon,
            current: page.url.startsWith('/support'),
        },
        {
            name: 'Inspire',
            href: '/inspire',
            icon: SunIcon,
            current: page.url.startsWith('/inspire'),
        },
    ];

    if (page.props.auth.user) {
        items.push({
            name: 'Sign Out',
            href: '/logout',
            icon: ArrowLeftStartOnRectangleIcon,
            current: false,
        });
    }

    return items;
});

const adminNavigation = computed(() => [
    {
        name: 'Dashboard',
        href: '/admin',
        initial: 'D',
        current: page.url === '/admin',
    },
    {
        name: 'Users',
        href: '/admin/users',
        initial: 'U',
        current: page.url.startsWith('/admin/users'),
    },
    {
        name: 'Orders',
        href: '/admin/orders',
        initial: 'O',
        current: page.url.startsWith('/admin/orders'),
    },
    {
        name: 'NSE Files',
        href: '/admin/nse-files',
        initial: 'N',
        current: page.url.startsWith('/admin/nse-files'),
    },
]);

const authUserNavigation = [
    { name: 'Your profile', href: '/profile' },
    { name: 'Sign out', href: '/logout' },
];

const guestUserNavigation = [
    { name: 'Sign In', href: '/login' },
    { name: 'Register', href: '/register' },
];
</script>
