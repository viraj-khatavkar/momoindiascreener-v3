<template>
    <div>
        <Head title="Viraj's Portfolio Performance" />
        <PageHeader>Viraj's Portfolio Performance</PageHeader>
        <InfoAlert class="mb-4">
            No part of this page should be treated as investment or financial advice. I am not promoting nor endorsing any stock or investment
            strategy. This page is purely for the sake of reader transparency who are interested into my own portfolio's performance. I am not a SEBI
            registered investment advisor.
        </InfoAlert>
        <iframe :src="spreadsheetUrl" title="Viraj's portfolio performance spreadsheet" class="w-full" height="1000"></iframe>
    </div>
</template>

<script setup lang="ts">
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    spreadsheetUrl: string;
}>();
</script>
