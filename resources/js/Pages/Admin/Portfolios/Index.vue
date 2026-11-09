<template>
    <div>
        <Head title="Portfolios" />
        <div class="flex flex-wrap items-start justify-between gap-4">
            <PageHeader description="Manage your portfolios and review their holdings.">Portfolios</PageHeader>
            <Link :href="portfolioRoutes.create" class="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500">
                Create Portfolio
            </Link>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
                <TextInput
                    id="search"
                    v-model="form.search"
                    label="Search portfolios"
                    name="search"
                    placeholder="Search by name"
                    :error="form.errors.search"
                />
                <PrimaryButton type="submit" :disabled="form.processing" class="mt-0 disabled:opacity-50">Search</PrimaryButton>
                <Link v-if="filters.search" :href="portfolioRoutes.index" class="px-2 py-2 text-sm font-medium text-slate-600">Clear</Link>
            </form>
            <div class="mt-6 overflow-x-auto">
                <table class="w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="text-slate-600">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Name</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Holding entries</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Updated</th>
                            <th scope="col" class="relative px-3 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="portfolio in portfolios.data" :key="portfolio.id" class="hover:bg-slate-50">
                            <td class="min-w-48 px-3 py-4 font-medium break-words text-slate-900">
                                <Link :href="portfolio.editUrl" class="hover:text-purple-700">{{ portfolio.name }}</Link>
                            </td>
                            <td class="px-3 py-4 text-slate-600">{{ portfolio.holdings_count }}</td>
                            <td class="px-3 py-4 whitespace-nowrap text-slate-600">{{ formatDate(portfolio.updated_at) }}</td>
                            <td class="px-3 py-4 text-right">
                                <Link :href="portfolio.editUrl" class="font-semibold text-purple-700 hover:text-purple-500">View / Edit</Link>
                            </td>
                        </tr>
                        <tr v-if="portfolios.data.length === 0">
                            <td colspan="4" class="px-3 py-12 text-center text-slate-500">
                                {{
                                    filters.search
                                        ? 'No portfolios match your search.'
                                        : 'No portfolios yet. Create a portfolio to add your holdings.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-slate-500">{{ portfolios.total }} portfolios</p>
            <Pagination :links="portfolios.links" />
        </section>
    </div>
</template>

<script setup lang="ts">
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import { formatDate } from '@/utils';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    portfolios: {
        data: { id: number; name: string; holdings_count: number; updated_at: string; editUrl: string }[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string };
    portfolioRoutes: { index: string; create: string };
}>();

const form = useForm({ search: props.filters.search });

function search() {
    form.get(props.portfolioRoutes.index, { preserveState: true });
}
</script>
