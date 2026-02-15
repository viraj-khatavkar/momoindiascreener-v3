<template>
    <Head title="Accept Terms | Billing" />
    <div class="mx-auto max-w-xl">
        <h1 class="font-semibold">
            You are subscribing to the {{ plan.toUpperCase() }} plan
        </h1>
        <ul class="ml-5 list-disc leading-loose">
            <li class="my-5">
                Please wait for ten to fifteen seconds after completing payment and allow the page to
                refresh automatically. This will ensure that your premium plan is activated
                successfully.
            </li>
        </ul>

        <form @submit.prevent="create">
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-4 cursor-pointer rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:opacity-50"
            >
                Accept & Continue to Payment
            </button>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    plan: string;
}>();

const form = useForm({
    plan: props.plan,
});

function create() {
    form.post('/orders');
}
</script>
