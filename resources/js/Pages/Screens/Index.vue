<template>
    <div class="mx-auto max-w-7xl py-4 sm:py-8">
        <Head title="Screens" />
        <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Screens</h1>
                <p class="mt-2 text-sm text-gray-600">
                    {{
                        signedIn
                            ? 'Find matching stocks. Review results or edit your saved rules.'
                            : 'Explore example screens and view their results and rules.'
                    }}
                </p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
                <Link
                    v-if="exportUrl"
                    :href="exportUrl"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:min-h-10"
                    >Historical ZIP Downloads</Link
                >
                <Link
                    v-if="signedIn"
                    href="/screens/create"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:min-h-10"
                    >Create New Screen</Link
                >
            </div>
        </div>
        <div class="mb-8 grid gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-[1fr_13rem]">
            <TextInput v-model="filters.search" type="search" label="Search screens" name="screens_search" placeholder="Find by name or universe" />
            <SelectInput v-model="filters.sort" label="Sort screens" name="screens_sort" :options="sortOptions" />
        </div>
        <section v-if="signedIn" aria-labelledby="your-screens-heading" class="mb-10">
            <div class="mb-4 flex items-baseline gap-3">
                <h2 id="your-screens-heading" class="text-xl font-semibold text-gray-900">Your Screens</h2>
                <span class="text-sm text-gray-500">{{ personalScreens.length }} of {{ screens.length }}</span>
            </div>
            <div v-if="personalScreens.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <ScreenCard v-for="screen in personalScreens" :key="screen.id" :screen="screen" editable show-actions />
            </div>
            <div v-else class="rounded-xl border border-dashed border-gray-300 p-8 text-center">
                <p class="font-medium text-gray-900">
                    {{ screens.length ? 'No personal screens match your search.' : 'You have no saved screens yet.' }}
                </p>
                <p class="mt-2 text-sm text-gray-500">
                    {{
                        screens.length ? 'Try another name or clear the search.' : 'Create a screen, or use an example to review how the rules work.'
                    }}
                </p>
            </div>
        </section>
        <section aria-labelledby="example-screens-heading">
            <div class="mb-4 flex flex-wrap items-baseline gap-3">
                <h2 id="example-screens-heading" class="text-xl font-semibold text-gray-900">Example Screens</h2>
                <span class="text-sm text-gray-500">{{ exampleScreens.length }} of {{ publicScreens.length }}</span>
            </div>
            <p class="mb-5 text-sm text-gray-600">
                View the saved rules and results. Paid members can duplicate an example to create a private screen.
            </p>
            <div v-if="exampleScreens.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <ScreenCard v-for="screen in exampleScreens" :key="screen.id" :screen="screen" show-actions />
            </div>
            <p v-else class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                {{ publicScreens.length ? 'No examples match your search.' : 'No example screens are available.' }}
            </p>
        </section>
        <button
            v-if="filters.search"
            type="button"
            class="mt-5 min-h-11 rounded text-sm font-semibold text-purple-700 hover:text-purple-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600"
            @click="filters.search = ''"
        >
            Clear screen search
        </button>
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage, useRemember } from '@inertiajs/vue3';
import ScreenCard from '@/Components/ScreenCard.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import type { Screen } from '@/types/app/Models/Screen';
import { screenSortByDisplayName } from '@/utils/screenSortByDisplayName';
const props = defineProps<{ screens: Screen[]; publicScreens: Screen[]; exportUrl: string | null }>();
const page = usePage();
const signedIn = computed(() => Boolean(page.props.auth.user));
const filters = ref(useRemember({ search: '', sort: 'recent' }, 'ScreensList'));
const sortOptions = [
    { id: 'recent', name: 'Recently updated' },
    { id: 'name', name: 'Name: A to Z' },
];
function filterScreens(screens: Screen[]): Screen[] {
    const query = filters.value.search.trim().toLowerCase();
    return screens
        .filter((screen) => `${screen.name} ${screenSortByDisplayName(screen.index)}`.toLowerCase().includes(query))
        .sort((a, b) =>
            filters.value.sort === 'name' ? a.name.localeCompare(b.name) || a.id - b.id : b.updated_at.localeCompare(a.updated_at) || b.id - a.id,
        );
}
const personalScreens = computed(() => filterScreens(props.screens));
const exampleScreens = computed(() => filterScreens(props.publicScreens));
</script>
