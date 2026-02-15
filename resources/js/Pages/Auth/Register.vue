<template>
    <Head title="Register" />
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <div>
            <div class="bg-gray-100 px-6 py-12 shadow-xs sm:rounded-lg sm:px-12">
                <Form class="space-y-6" action="/register" method="POST" #default="{ errors }">
                    <TextInput name="name" label="Name" :error="errors.name" />

                    <TextInput type="email" name="email" label="Email" :error="errors.email" />

                    <TextInput type="password" name="password" label="Password" :error="errors.password" />

                    <div>
                        <button
                            type="submit"
                            class="flex w-full justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm/6 font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
                        >
                            Register
                        </button>
                    </div>
                </Form>
            </div>

            <p class="mt-10 text-center text-sm text-gray-500">
                Have an account?
                <Link href="/login" class="font-semibold text-purple-600 hover:text-purple-500">
                    Sign In Here
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
