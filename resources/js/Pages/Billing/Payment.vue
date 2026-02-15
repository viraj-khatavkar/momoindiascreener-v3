<template>
    <Head title="Payment" />
    <div class="mx-auto max-w-xl text-2xl">Please wait.. Redirecting you to the payment gateway!</div>
</template>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps<{
    apiKey: string;
    amount: number;
    description: string;
    order_id: string;
    callback_url: string;
}>();

const page = usePage();

onMounted(() => {
    setTimeout(() => {
        const options = {
            key: props.apiKey,
            amount: props.amount,
            currency: 'INR',
            name: 'momoindiascreener.in',
            description: props.description,
            image: 'https://momoindiascreener.in/images/logo.png',
            order_id: props.order_id,
            callback_url: props.callback_url,
            prefill: {
                name: page.props.auth.user?.name,
                email: page.props.auth.user?.email,
                contact: page.props.auth.user?.phone,
            },
            modal: {
                ondismiss() {
                    router.get('/razorpay/cancel');
                },
            },
        };

        // @ts-expect-error Razorpay is loaded globally via script tag
        const rzp = new Razorpay(options);
        rzp.open();
    }, 500);
});
</script>
