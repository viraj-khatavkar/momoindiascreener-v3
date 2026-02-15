<template>
    <Head title="Change Password" />

    <div>
        <h2 class="text-xl font-semibold text-gray-900">Change Password</h2>
        <p class="mt-1 text-sm text-gray-500">Update your account's password.</p>
    </div>

    <Form class="mt-8" action="/change-password" method="PUT" #default="{ errors }">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <TextInput type="password" name="old_password" label="Old Password" :error="errors.old_password" />
            <TextInput type="password" name="new_password" label="New Password" :error="errors.new_password" />
        </div>
        <PrimaryButton type="submit">Update Password</PrimaryButton>
    </Form>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { Form, Head } from '@inertiajs/vue3';
</script>
