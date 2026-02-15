<template>
    <Head title="Profile" />

    <div>
        <h2 class="text-xl font-semibold text-gray-900">Profile</h2>
        <p class="mt-1 text-sm text-gray-500">Update your profile information.</p>
    </div>

    <Form class="mt-8" action="/profile" method="POST" #default="{ errors }">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <TextInput name="name" label="Name" :error="errors.name" v-model="form.name" />
            <TextInput name="email" label="Email" :error="errors.email" v-model="form.email" />
            <TextInput
                name="address_line_one"
                label="Address Line One"
                :error="errors.address_line_one"
                v-model="form.address_line_one"
            />
            <TextInput
                name="address_line_two"
                label="Address Line Two"
                :error="errors.address_line_two"
                v-model="form.address_line_two"
            />
            <TextInput name="phone" label="Phone" :error="errors.phone" v-model="form.phone" />
            <TextInput name="city" label="City" :error="errors.city" v-model="form.city" />
            <SelectInput
                name="state"
                label="State"
                :error="errors.state"
                :options="states"
                v-model="form.state"
            />
            <SelectInput
                name="country"
                label="Country"
                :error="errors.country"
                :options="countries"
                v-model="form.country"
            />
            <TextInput
                name="postal_code"
                label="Postal Code"
                :error="errors.postal_code"
                v-model="form.postal_code"
            />
        </div>
        <PrimaryButton type="submit">Update</PrimaryButton>
    </Form>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { Form, Head } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Option {
    id: string | number;
    name: string;
}

interface Profile {
    name: string;
    email: string;
    address_line_one: string;
    address_line_two: string;
    phone: string;
    city: string;
    state: string;
    country: string;
    postal_code: string;
}

const props = defineProps<{
    profile: Profile;
    states: Option[];
    countries: Option[];
}>();

const form = reactive({
    name: props.profile.name || '',
    email: props.profile.email || '',
    address_line_one: props.profile.address_line_one || '',
    address_line_two: props.profile.address_line_two || '',
    phone: props.profile.phone || '',
    city: props.profile.city || '',
    state: props.profile.state || 'Maharashtra',
    country: props.profile.country || 'india',
    postal_code: props.profile.postal_code || '',
});
</script>
