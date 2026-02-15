<template>
    <div class="flex min-h-full flex-1 flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/">
                <img alt="momoindiascreener" src="/images/logo.png" class="mx-auto w-auto" />
            </Link>
            <h2
                class="mt-6 text-center text-2xl font-bold leading-9 tracking-tight text-gray-900"
            >
                {{ pageHeader }}
            </h2>
        </div>

        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[480px]">
            <SuccessAlert v-if="$page.props.flash.success" class="mb-4">
                {{ $page.props.flash.success }}
            </SuccessAlert>
            <slot />
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SuccessAlert from './Components/Alerts/SuccessAlert.vue';

const page = usePage();

const pageHeader = computed(() => {
    if (page.url === '/login') {
        return 'Sign in to your account';
    } else if (page.url === '/register') {
        return 'Get started in minutes';
    } else if (page.url === '/forgot-password') {
        return 'Forgot your password?';
    } else if (page.url.startsWith('/reset-password')) {
        return 'Reset your password';
    }
    return '';
});
</script>
