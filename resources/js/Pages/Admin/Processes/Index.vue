<template>
    <div>
        <Head title="Daily Data Processes" />

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <PageHeader description="Prepare and run the daily backtest commands in their required order."> Daily Data Processes </PageHeader>
            <Link
                href="/admin/nse-files/create"
                class="inline-flex w-fit rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 hover:bg-gray-50"
            >
                Upload NSE files
            </Link>
        </div>

        <section class="rounded-xl border border-purple-200 bg-purple-50 p-5">
            <h2 class="text-sm font-semibold text-purple-950">How this page works</h2>
            <ol class="mt-3 grid gap-3 text-sm text-purple-900 lg:grid-cols-3">
                <li class="flex gap-3">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">1</span>
                    <span>Select the processing date and check the required files.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">2</span>
                    <span>Create one saved checklist for that date.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">3</span>
                    <span>Run and review one command at a time.</span>
                </li>
            </ol>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <form class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applyDate">
                        <div class="w-full max-w-xs">
                            <TextInput v-model="dateForm.date" type="date" label="Processing date" name="date" :error="dateForm.errors.date" />
                        </div>
                        <button
                            type="submit"
                            :disabled="dateForm.processing"
                            class="h-9 cursor-pointer rounded-md bg-purple-600 px-4 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ dateForm.processing ? 'Checking...' : 'Check date' }}
                        </button>
                    </form>
                </div>

                <div class="mt-6 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Required files</h2>
                        <p class="mt-1 text-sm text-gray-500">Files for {{ selectedDate }}</p>
                    </div>
                    <span
                        :class="[
                            allFilesAvailable ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800',
                            'rounded-full px-3 py-1 text-xs font-semibold',
                        ]"
                    >
                        {{ allFilesAvailable ? 'Ready' : 'Files missing' }}
                    </span>
                </div>

                <ul class="mt-4 divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <li v-for="file in requiredFiles" :key="file.key" class="flex items-center justify-between gap-4 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ file.name }}</p>
                            <p class="mt-0.5 font-mono text-xs text-gray-500">{{ file.filename }}</p>
                        </div>
                        <span :class="[file.available ? 'text-green-700' : 'text-red-700', 'inline-flex items-center gap-1.5 text-sm font-semibold']">
                            <span :class="[file.available ? 'bg-green-500' : 'bg-red-500', 'size-2 rounded-full']" />
                            {{ file.available ? 'Available' : 'Missing' }}
                        </span>
                    </li>
                </ul>

                <p v-if="createForm.errors.files" class="mt-3 text-sm text-red-700">
                    {{ createForm.errors.files }}
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <Link
                        v-if="existingRun"
                        :href="`/admin/process-runs/${existingRun.id}`"
                        class="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500"
                    >
                        Open daily run
                    </Link>
                    <button
                        v-else
                        type="button"
                        :disabled="!allFilesAvailable || createForm.processing"
                        class="cursor-pointer rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500 disabled:cursor-not-allowed disabled:bg-gray-300"
                        @click="createRun"
                    >
                        {{ createForm.processing ? 'Creating...' : 'Create daily run' }}
                    </button>
                    <Link
                        :href="`/admin/nse-files?date=${encodeURIComponent(selectedDate)}`"
                        class="text-sm font-semibold text-purple-700 hover:text-purple-900"
                    >
                        View all files for this date
                    </Link>
                </div>

                <div v-if="existingRun" class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                    A run already exists for this date. It has completed
                    <strong>{{ existingRun.completed_steps }} of {{ existingRun.total_steps }}</strong>
                    steps.
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <h2 class="text-base font-semibold text-gray-900">Before you start</h2>
                <ul class="mt-4 space-y-3 text-sm text-gray-600">
                    <li class="flex gap-2">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-gray-400" />
                        <span>The commands use the selected date automatically.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-gray-400" />
                        <span>Preview steps do not save adjustment changes.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-gray-400" />
                        <span>Each adjustment preview is followed by a separate apply step that changes stored data.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-gray-400" />
                        <span>A failed step stops the checklist. You can read its output and retry it.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-gray-400" />
                        <span>You can close the page after a step starts. The queue will continue the command.</span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="mt-8">
            <h2 class="text-base font-semibold text-gray-900">Recent runs</h2>
            <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase">Progress</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase">Created by</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-gray-500 uppercase">
                                    <span class="sr-only">Open</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="run in recentRuns" :key="run.id">
                                <td class="px-4 py-3 text-sm font-medium whitespace-nowrap text-gray-900">{{ run.process_date }}</td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap">
                                    <span :class="[statusClasses(run.status), 'rounded-full px-2.5 py-1 text-xs font-semibold']">
                                        {{ statusLabel(run.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-600">{{ run.completed_steps }} / {{ run.total_steps }}</td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap text-gray-600">{{ run.created_by ?? 'Admin' }}</td>
                                <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    <Link :href="`/admin/process-runs/${run.id}`" class="font-semibold text-purple-700 hover:text-purple-900"
                                        >Open</Link
                                    >
                                </td>
                            </tr>
                            <tr v-if="recentRuns.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No daily runs exist.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import type { AdminProcessRunStatus, AdminProcessRunSummary } from '@/types/AdminProcess';

interface RequiredFile {
    key: string;
    name: string;
    filename: string;
    available: boolean;
}

interface CreateRunForm {
    date: string;
    files?: string;
}

const props = defineProps<{
    selectedDate: string;
    requiredFiles: RequiredFile[];
    allFilesAvailable: boolean;
    existingRun: AdminProcessRunSummary | null;
    recentRuns: AdminProcessRunSummary[];
}>();

const dateForm = useForm({
    date: props.selectedDate,
});

const createForm = useForm<CreateRunForm>({
    date: props.selectedDate,
});

function applyDate() {
    dateForm.get('/admin/processes', {
        preserveScroll: true,
        replace: true,
    });
}

function createRun() {
    createForm.date = props.selectedDate;
    createForm.post('/admin/process-runs');
}

function statusLabel(status: AdminProcessRunStatus): string {
    return {
        pending: 'Not started',
        in_progress: 'In progress',
        completed: 'Completed',
        failed: 'Needs attention',
    }[status];
}

function statusClasses(status: AdminProcessRunStatus): string {
    return {
        pending: 'bg-gray-100 text-gray-700',
        in_progress: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    }[status];
}
</script>
