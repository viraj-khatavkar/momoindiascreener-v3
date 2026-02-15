<template>
    <Head title="AMA Recordings" />
    <PageHeader description="Link to the recordings of AMA for paid subscribers.">AMA Recordings</PageHeader>
    <div v-for="amaLink in amaLinks" :key="amaLink.url" class="mb-4">
        <a :href="amaLink.url" target="_blank" rel="noreferrer" class="text-blue-700 underline">
            {{ amaLink.title }}
        </a>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';

const amaLinks = [
    { url: 'https://youtu.be/Yg2tSATUn4k', title: 'January 2026 AMA' },
    { url: 'https://www.youtube.com/watch?v=Fyy-1lADpww', title: 'November 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=jx74Bjml6Tk', title: 'October 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=hCKb2dXinfs', title: 'September 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=udtLa6Cprk4', title: 'August 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=ZU811dOL9_g', title: 'July 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=8h7kPFrxykI', title: 'June 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=e4O8mFOowwI', title: 'April 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=Sv7i4j5W7TQ', title: 'March 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=LBqITUvdHUo', title: 'February 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=6lQ37Uofr4I', title: 'January 2025 AMA' },
    { url: 'https://www.youtube.com/watch?v=8-YHChH9DbI', title: 'October 2024 AMA' },
    { url: 'https://www.youtube.com/watch?v=CqQAG1Inj5M', title: 'August 2024 AMA' },
    { url: 'https://www.youtube.com/watch?v=pgWY5gzz6cI', title: 'June 2024 AMA' },
];
</script>
