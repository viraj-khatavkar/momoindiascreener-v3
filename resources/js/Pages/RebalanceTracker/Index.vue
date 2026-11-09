<template>
    <div class="mx-auto max-w-7xl">
        <Head title="Rebalance Tracker" />
        <div class="flex flex-wrap items-start justify-between gap-4">
            <PageHeader description="Compare your portfolio with a saved screen to find exits, retained holdings, and new entries.">
                Rebalance Tracker
            </PageHeader>
            <span v-if="latestDate" class="rounded-full bg-purple-50 px-3 py-1.5 text-xs font-medium text-purple-700">
                Latest prices: {{ latestDate }}
            </span>
        </div>

        <div class="flex flex-col gap-6">
            <WarningAlert v-if="!latestDate">No market data is available. Try again after prices are imported.</WarningAlert>
            <WarningAlert v-if="screens.length === 0">
                Create a screen before you analyze a portfolio.
                <Link href="/screens/create" class="font-semibold underline">Create Screen</Link>
            </WarningAlert>

            <section aria-labelledby="upload-title" class="rounded-lg border border-gray-200 bg-white p-5 shadow-xs sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 id="upload-title" class="text-lg font-semibold text-gray-900">1. Upload Portfolio</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Add a CSV with a <code class="rounded bg-gray-100 px-1 text-gray-700">symbol</code> column. Maximum 1 MB and 1,000
                            symbols.
                        </p>
                    </div>
                    <a
                        href="/sample-portfolio.csv"
                        download
                        class="inline-flex items-center gap-1.5 rounded text-sm font-medium text-purple-700 hover:text-purple-500 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-600"
                    >
                        <ArrowDownTrayIcon class="size-4" aria-hidden="true" />
                        Download Sample CSV
                    </a>
                </div>
                <form class="mt-5" @submit.prevent="uploadPortfolio">
                    <fieldset :disabled="busy" class="flex flex-col items-start gap-4 sm:flex-row sm:items-end">
                        <div class="w-full sm:max-w-lg">
                            <label for="portfolio-csv" class="block text-sm font-medium text-gray-900">CSV File</label>
                            <input
                                id="portfolio-csv"
                                ref="fileInput"
                                type="file"
                                name="csv"
                                accept=".csv,.txt,text/csv,text/plain"
                                :aria-invalid="!!uploadForm.errors.csv"
                                :aria-describedby="uploadForm.errors.csv ? 'csv-error' : undefined"
                                class="mt-2 block w-full cursor-pointer rounded-md border border-gray-300 text-sm text-gray-600 file:mr-4 file:cursor-pointer file:border-0 file:bg-purple-50 file:px-4 file:py-2.5 file:font-semibold file:text-purple-700 hover:file:bg-purple-100 focus-visible:outline-2 focus-visible:outline-purple-600"
                                @change="selectFile"
                            />
                        </div>
                        <PrimaryButton
                            type="submit"
                            :disabled="busy || !uploadForm.csv || !latestDate"
                            class="mt-0 shrink-0 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ uploadForm.processing ? 'Uploading…' : 'Upload' }}
                        </PrimaryButton>
                    </fieldset>
                    <progress
                        v-if="uploadForm.progress"
                        :value="uploadForm.progress.percentage"
                        max="100"
                        aria-label="Upload progress"
                        class="mt-3 h-2 w-full accent-purple-600"
                    >
                        {{ uploadForm.progress.percentage }}%
                    </progress>
                    <p v-if="uploadForm.errors.csv" id="csv-error" role="alert" class="mt-3 text-sm text-red-700">{{ uploadForm.errors.csv }}</p>
                </form>
            </section>

            <section v-if="portfolio" aria-labelledby="analyze-title" class="rounded-lg border border-gray-200 bg-white p-5 shadow-xs sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="analyze-title" class="text-lg font-semibold text-gray-900">2. Analyze Portfolio</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ portfolio.total }} unique symbols · {{ portfolio.matched.length }} matched · Checked against {{ portfolio.date }}
                        </p>
                        <p v-if="portfolio.duplicates" class="mt-1 text-sm text-gray-500">
                            Duplicate rows removed: {{ portfolio.duplicates }}. Each symbol is counted once.
                        </p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Symbols only; quantities are not used</span>
                </div>

                <ErrorAlert v-if="portfolio.unmatched.length" role="alert" class="mt-5">
                    {{ portfolio.unmatched.length }} symbols were not found in the latest price data: {{ portfolio.unmatched.join(', ') }}. Correct
                    these symbols in your CSV and upload it again.
                </ErrorAlert>

                <div class="mt-5 max-h-72 overflow-y-auto rounded-lg border border-gray-200 p-3 sm:p-4">
                    <ul aria-label="Uploaded symbols" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <li
                            v-for="item in uploadedSymbols"
                            :key="item.symbol"
                            :class="item.matched ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'"
                            class="flex min-w-0 flex-col items-center gap-2 rounded-lg border p-3"
                        >
                            <span class="max-w-full text-center text-sm font-semibold break-all">{{ item.symbol }}</span>
                            <span class="text-xs">{{ item.matched ? 'Matched' : 'Not Found' }}</span>
                        </li>
                    </ul>
                </div>

                <form class="mt-6" @submit.prevent="analyzePortfolio">
                    <fieldset :disabled="busy" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <SelectInput
                                id="screen_id"
                                v-model="analyzeForm.screen_id"
                                label="Select Screen"
                                name="screen_id"
                                :options="screens"
                                :error="analyzeForm.errors.screen_id"
                            />
                            <p v-if="selectedScreen?.apply_historical_date" class="mt-2 text-xs text-amber-700">
                                This screen uses its saved historical date: {{ selectedScreen.historical_date }}.
                            </p>
                        </div>
                        <div>
                            <TextInput
                                id="worst_rank_held"
                                v-model="analyzeForm.worst_rank_held"
                                type="number"
                                label="Worst Rank Held"
                                name="worst_rank_held"
                                placeholder="For example, 30"
                                :error="analyzeForm.errors.worst_rank_held"
                            />
                            <p class="mt-2 text-xs text-gray-500">Keep holdings within this rank. Symbols outside this rank are exits.</p>
                        </div>
                        <div>
                            <TextInput
                                id="max_buy_recommendations"
                                v-model="analyzeForm.max_buy_recommendations"
                                type="number"
                                label="Max Entries to Detect"
                                name="max_buy_recommendations"
                                placeholder="Leave blank to match exit count"
                                :error="analyzeForm.errors.max_buy_recommendations"
                            />
                            <p class="mt-2 text-xs text-gray-500">Optional, from 1 to 100. Entries start at the highest available rank.</p>
                        </div>
                    </fieldset>
                    <ErrorAlert v-if="symbolError" role="alert" class="mt-4">{{ symbolError }}</ErrorAlert>
                    <PrimaryButton type="submit" :disabled="!canAnalyze" class="mt-5 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ analyzeForm.processing ? 'Analyzing…' : 'Analyze Portfolio' }}
                    </PrimaryButton>
                </form>
            </section>

            <section v-if="analysis" aria-labelledby="results-title" class="rounded-lg border border-gray-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 id="results-title" class="text-lg font-semibold text-gray-900">3. Analysis Results</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ analysis.screenName }} · {{ analysis.historical ? 'Historical prices' : 'Prices' }}: {{ analysis.date }} · Worst rank held:
                    {{ analysis.worstRankHeld }}
                </p>
                <WarningAlert v-for="warning in analysis.warnings" :key="warning" class="mt-4">{{ warning }}</WarningAlert>
                <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-3">
                    <section
                        v-for="group in resultGroups"
                        :key="group.key"
                        :aria-labelledby="'result-' + group.key"
                        :class="group.color"
                        class="min-w-0 rounded-lg border p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 :id="'result-' + group.key" class="text-sm font-semibold">{{ group.label }} ({{ group.symbols.length }})</h3>
                            <button
                                type="button"
                                :disabled="group.symbols.length === 0"
                                :aria-label="'Copy ' + group.label"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-md bg-white/70 px-2 py-1 text-xs font-semibold hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="copySymbols(group.label, group.symbols)"
                            >
                                <ClipboardDocumentIcon class="size-4" aria-hidden="true" />
                                Copy
                            </button>
                        </div>
                        <p class="mt-2 text-xs">{{ group.description }}</p>
                        <ul v-if="group.symbols.length" class="mt-4 flex flex-col gap-2">
                            <li v-for="symbol in group.symbols" :key="symbol" class="text-sm font-medium break-all">{{ symbol }}</li>
                        </ul>
                        <p v-else class="mt-4 text-sm">No symbols in this group.</p>
                    </section>
                </div>
                <p role="status" aria-live="polite" class="mt-3 text-sm text-gray-600">{{ copyMessage }}</p>
            </section>
        </div>
    </div>
</template>

<script setup lang="ts">
import ErrorAlert from '@/Components/Alerts/ErrorAlert.vue';
import WarningAlert from '@/Components/Alerts/WarningAlert.vue';
import PrimaryButton from '@/Components/Button/PrimaryButton.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { ArrowDownTrayIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Portfolio {
    matched: string[];
    unmatched: string[];
    total: number;
    duplicates: number;
    date: string;
}

interface Analysis {
    screenName: string;
    date: string;
    historical: boolean;
    worstRankHeld: number;
    sells: string[];
    holds: string[];
    buys: string[];
    warnings: string[];
}

const props = defineProps<{
    screens: { id: number; name: string; apply_historical_date: boolean; historical_date: string | null }[];
    latestDate: string | null;
    portfolio: Portfolio | null;
    analysis: Analysis | null;
    trackerRoutes: { upload: string; analyze: string };
}>();

const portfolio = ref(props.portfolio);
const analysis = ref(props.analysis);
const fileInput = ref<HTMLInputElement | null>(null);
const copyMessage = ref('');
const uploadForm = useForm<{ csv: File | null }>({ csv: null });
const analyzeForm = useForm({
    screen_id: props.screens[0]?.id ?? '',
    worst_rank_held: '' as number | string,
    max_buy_recommendations: '' as number | string,
    symbols: [] as string[],
});

const busy = computed(() => uploadForm.processing || analyzeForm.processing);
const selectedScreen = computed(() => props.screens.find((screen) => String(screen.id) === String(analyzeForm.screen_id)));
const uploadedSymbols = computed(() => [
    ...(portfolio.value?.matched.map((symbol) => ({ symbol, matched: true })) ?? []),
    ...(portfolio.value?.unmatched.map((symbol) => ({ symbol, matched: false })) ?? []),
]);
const canAnalyze = computed(
    () =>
        !busy.value &&
        !!analyzeForm.screen_id &&
        Number(analyzeForm.worst_rank_held) >= 1 &&
        !!portfolio.value?.matched.length &&
        portfolio.value.unmatched.length === 0,
);
const symbolError = computed(() =>
    Object.entries(analyzeForm.errors)
        .filter(([key]) => key === 'symbols' || key.startsWith('symbols.'))
        .map(([, message]) => message)
        .join(' '),
);
const resultGroups = computed(() => [
    {
        key: 'sells',
        label: 'Exits',
        description: 'Holdings outside the selected rank or screen filters.',
        color: 'border-red-200 bg-red-50 text-red-800',
        symbols: analysis.value?.sells ?? [],
    },
    {
        key: 'holds',
        label: 'Inside WRH',
        description: 'Holdings within the worst rank held.',
        color: 'border-amber-200 bg-amber-50 text-amber-800',
        symbols: analysis.value?.holds ?? [],
    },
    {
        key: 'buys',
        label: 'Entries',
        description: 'Unheld symbols within the selected rank, in screen order.',
        color: 'border-green-200 bg-green-50 text-green-800',
        symbols: analysis.value?.buys ?? [],
    },
]);

function clearAnalysis() {
    analysis.value = null;
    copyMessage.value = '';
}

watch(() => [analyzeForm.screen_id, analyzeForm.worst_rank_held, analyzeForm.max_buy_recommendations], clearAnalysis);

function selectFile(event: Event) {
    uploadForm.csv = (event.target as HTMLInputElement).files?.[0] ?? null;
    uploadForm.clearErrors();
    analyzeForm.clearErrors();
    portfolio.value = null;
    clearAnalysis();
}

function uploadPortfolio() {
    clearAnalysis();
    analyzeForm.clearErrors();
    uploadForm.post(props.trackerRoutes.upload, {
        preserveScroll: true,
        onSuccess: (page) => {
            portfolio.value = page.props.portfolio as Portfolio;
            uploadForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function analyzePortfolio() {
    if (!canAnalyze.value || !portfolio.value) {
        return;
    }

    clearAnalysis();
    analyzeForm.symbols = portfolio.value.matched;
    analyzeForm
        .transform((data) => ({ ...data, max_buy_recommendations: data.max_buy_recommendations === '' ? null : data.max_buy_recommendations }))
        .post(props.trackerRoutes.analyze, {
            preserveScroll: true,
            onSuccess: (page) => {
                analysis.value = page.props.analysis as Analysis;
            },
        });
}

async function copySymbols(label: string, symbols: string[]) {
    try {
        await navigator.clipboard.writeText(symbols.join('\n'));
        copyMessage.value = label + ' copied.';
    } catch {
        copyMessage.value = 'Copy was not available. Select the symbols and copy them manually.';
    }
}
</script>
