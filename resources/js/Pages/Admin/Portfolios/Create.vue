<template>
    <div>
        <Head title="Create Portfolio" />
        <PageHeader description="Create a portfolio, then add symbols, quantities, and purchase prices.">Create Portfolio</PageHeader>
        <form class="max-w-2xl rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6" @submit.prevent="submit">
            <TextInput
                id="name"
                v-model="form.name"
                label="Name"
                name="name"
                placeholder="For example, Long-term Momentum"
                :error="form.errors.name"
            />
            <div class="mt-5 flex items-center gap-4">
                <PrimaryButton type="submit" :disabled="form.processing" class="mt-0 disabled:opacity-50">
                    {{ form.processing ? 'Creating…' : 'Create Portfolio' }}
                </PrimaryButton>
                <Link :href="portfolioRoutes.index" class="text-sm font-medium text-slate-600 hover:text-slate-900">Cancel</Link>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ portfolioRoutes: { index: string; store: string } }>();
const form = useForm({ name: '' });

function submit() {
    form.post(props.portfolioRoutes.store);
}
</script>
