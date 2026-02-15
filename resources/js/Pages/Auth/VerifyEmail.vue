<template>
    <Head title="Verify Email" />
    <div class="flex min-h-full flex-1 flex-col justify-center px-6 py-12 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <SuccessAlert v-if="$page.props.flash.success" class="mb-6">
                {{ $page.props.flash.success }}
            </SuccessAlert>

            <div class="rounded-md bg-yellow-50 p-4">
                <div class="flex">
                    <div class="shrink-0">
                        <ExclamationTriangleIcon class="size-5 text-yellow-400" aria-hidden="true" />
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-yellow-800">
                            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another.
                        </p>
                    </div>
                </div>
            </div>

            <Link
                as="button"
                href="/email/verification-notification"
                method="post"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            >
                Resend Verification Email
            </Link>
        </div>
    </div>
</template>

<script setup lang="ts">
import SuccessAlert from '@/Components/Alerts/SuccessAlert.vue';
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid';
import { Head, Link } from '@inertiajs/vue3';
</script>
