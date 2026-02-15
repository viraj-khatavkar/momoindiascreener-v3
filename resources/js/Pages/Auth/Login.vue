<template>
    <Head title="Sign In" />
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <div>
            <div class="bg-gray-100 px-6 py-12 shadow-xs sm:rounded-lg sm:px-12">
                <Form class="space-y-6" action="/login" method="POST" #default="{ errors }">
                    <TextInput type="email" name="email" label="Email" :error="errors.email" />

                    <TextInput type="password" name="password" label="Password" :error="errors.password" />

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                id="remember-me"
                                name="remember_me"
                                type="checkbox"
                                class="h-4 w-4 rounded-xs border-gray-300 text-purple-600 focus:ring-purple-600"
                            />
                            <label for="remember-me" class="ml-3 block text-sm/6 text-gray-900">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm/6">
                            <Link href="/forgot-password" class="font-semibold text-purple-600 hover:text-purple-500">
                                Forgot password?
                            </Link>
                        </div>
                    </div>

                    <div>
                        <button
                            type="submit"
                            class="flex w-full justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                        >
                            Sign In
                        </button>
                    </div>
                </Form>
            </div>

            <p class="mt-10 text-center text-sm text-gray-500">
                Not a member?
                <Link href="/register" class="font-semibold text-purple-600 hover:text-purple-500">
                    Register Here
                </Link>
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import AuthLayout from '@/AuthLayout.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { Form, Head, Link } from '@inertiajs/vue3';

defineOptions({ layout: AuthLayout });
</script>
