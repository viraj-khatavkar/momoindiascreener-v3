<template>
    <div>
        <Head title="Historical Screen Downloads" />
        <div class="flex flex-wrap items-start justify-between gap-4">
            <PageHeader description="Download one CSV for each available trading date in a ZIP file.">Historical Screen Downloads</PageHeader>
            <Link href="/screens" class="text-sm font-semibold text-purple-700 hover:underline">Back to Screens</Link>
        </div>

        <form class="rounded-xl border border-gray-200 bg-white p-5 sm:p-6" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <SelectInput v-model="form.screen_id" name="screen_id" label="Saved screen" :options="screenOptions" :error="form.errors.screen_id" />
                <div>
                    <label for="export-start-date" class="block text-sm font-medium text-gray-900">Start date</label>
                    <input
                        id="export-start-date"
                        v-model="form.start_date"
                        name="start_date"
                        type="date"
                        min="2010-01-04"
                        :max="defaultEndDate"
                        required
                        class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-purple-500 focus:outline-purple-500"
                    />
                    <p v-if="form.errors.start_date" class="mt-2 text-sm text-red-600">{{ form.errors.start_date }}</p>
                </div>
                <div>
                    <label for="export-end-date" class="block text-sm font-medium text-gray-900">End date</label>
                    <input
                        id="export-end-date"
                        v-model="form.end_date"
                        name="end_date"
                        type="date"
                        :min="form.start_date || '2010-01-04'"
                        :max="maximumEndDate"
                        required
                        class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-purple-500 focus:outline-purple-500"
                    />
                    <p v-if="form.errors.end_date || rangeError" class="mt-2 text-sm text-red-600">{{ form.errors.end_date || rangeError }}</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-600">
                Select up to {{ maxDays }} calendar days, including both dates. Each export uses your saved screen settings. The screen's single
                historical date is replaced by this range.
            </p>
            <p class="mt-2 text-sm text-gray-600">
                The ZIP includes daily rankings, a data summary, and the settings used. Downloads expire after {{ retentionDays }} days.
            </p>
            <p v-if="screens.length === 0" class="mt-3 text-sm text-purple-700">Create a screen or copy an example screen to get started.</p>
            <p v-if="hasActiveExport" class="mt-3 text-sm text-purple-700" role="status">
                Your current export is in progress. Wait for it to finish before starting another.
            </p>
            <p v-if="errors.export" class="mt-3 text-sm text-red-600" role="alert">{{ errors.export }}</p>

            <button
                type="submit"
                :disabled="form.processing || hasActiveExport || !form.screen_id || !!rangeError"
                class="mt-5 rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ form.processing ? 'Submitting…' : 'Generate ZIP' }}
            </button>
        </form>

        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">Your downloads</h2>
            <p v-if="exports.data.length === 0" class="mt-4 rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600">
                No exports yet. Choose a screen and date range above.
            </p>
            <div v-else class="mt-4 space-y-4">
                <article v-for="item in exports.data" :key="item.id" class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold break-words text-gray-900">{{ item.screen_name }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ item.start_date }} to {{ item.end_date }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ statusLabels[item.status] }}</span>
                    </div>
                    <div v-if="item.status === 'running'" class="mt-4" aria-live="polite">
                        <progress
                            :value="item.processed_dates"
                            :max="item.total_dates || 1"
                            class="h-2 w-full accent-purple-600"
                            :aria-label="`Export progress for ${item.screen_name}`"
                        />
                        <p class="mt-2 text-sm text-gray-600">{{ item.processed_dates }} of {{ item.total_dates }} dates checked</p>
                    </div>
                    <p v-if="item.status === 'queued'" class="mt-3 text-sm text-gray-600">
                        Waiting to start. You can leave this page and return later.
                    </p>
                    <p v-if="item.status === 'failed'" class="mt-3 text-sm text-red-700">{{ item.error_message }}</p>
                    <p v-if="item.status === 'expired'" class="mt-3 text-sm text-gray-600">This download has expired. Generate a new export above.</p>
                    <div v-if="item.status === 'completed'" class="mt-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="text-sm text-gray-600">
                            <p>{{ item.file_count }} daily CSV files · {{ item.row_count.toLocaleString() }} matching rows</p>
                            <p v-if="item.warning_count > 0" class="mt-1 text-amber-800">
                                {{ item.warning_count }} data warnings. Read summary.csv in the ZIP.
                            </p>
                            <p v-if="item.expires_at" class="mt-1">Available until {{ dayjs(item.expires_at).format('D MMM YYYY, HH:mm') }}</p>
                        </div>
                        <a
                            v-if="item.download_url"
                            :href="item.download_url"
                            class="inline-flex rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500"
                            :aria-label="`Download ZIP for ${item.screen_name}`"
                            >Download ZIP</a
                        >
                    </div>
                </article>
                <Pagination :links="exports.links" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { computed, watch } from 'vue';

type ExportStatus = 'queued' | 'running' | 'completed' | 'failed' | 'expired';
interface ScreenExport {
    id: number;
    screen_name: string;
    start_date: string;
    end_date: string;
    status: ExportStatus;
    processed_dates: number;
    total_dates: number;
    file_count: number;
    row_count: number;
    warning_count: number;
    error_message: string | null;
    expires_at: string | null;
    download_url: string | null;
}

const props = defineProps<{
    screens: { id: number; name: string }[];
    exports: { data: ScreenExport[]; links: { url: string | null; label: string; active: boolean }[] };
    hasActiveExport: boolean;
    selectedScreenId: number | null;
    defaultStartDate: string;
    defaultEndDate: string;
    maxDays: number;
    retentionDays: number;
    storeUrl: string;
}>();

const form = useForm({
    screen_id: props.screens.some((screen) => screen.id === props.selectedScreenId) ? props.selectedScreenId : '',
    start_date: props.defaultStartDate,
    end_date: props.defaultEndDate,
});
const errors = computed(() => form.errors as Record<string, string>);
const screenOptions = computed(() => [{ id: '', name: 'Choose a screen' }, ...props.screens]);
const statusLabels: Record<ExportStatus, string> = {
    queued: 'Queued',
    running: 'Preparing',
    completed: 'Ready',
    failed: 'Failed',
    expired: 'Expired',
};
const rangeError = computed(() => {
    if (!form.start_date || !form.end_date) return 'Select a start date and an end date.';
    const days = dayjs(form.end_date).diff(dayjs(form.start_date), 'day') + 1;
    if (!Number.isFinite(days) || days < 1) return 'The end date must be on or after the start date.';
    return days > props.maxDays ? `Select no more than ${props.maxDays} calendar days, including both dates.` : '';
});
const maximumEndDate = computed(() => {
    if (!form.start_date) return props.defaultEndDate;
    const lastDate = dayjs(form.start_date)
        .add(props.maxDays - 1, 'day')
        .format('YYYY-MM-DD');
    return lastDate < props.defaultEndDate ? lastDate : props.defaultEndDate;
});
const { start, stop } = usePoll(3000, { only: ['exports', 'hasActiveExport'] }, { autoStart: false });
watch(
    () => props.hasActiveExport,
    (active) => (active ? start() : stop()),
    { immediate: true },
);

function submit(): void {
    if (form.processing || props.hasActiveExport || rangeError.value) return;
    form.post(props.storeUrl, { preserveScroll: true });
}
</script>
