<template>
    <div>
        <Head :title="`Daily Process ${processRun.process_date}`" />

        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <Link
                    :href="`/admin/processes?date=${encodeURIComponent(processRun.process_date)}`"
                    class="text-sm font-semibold text-purple-700 hover:text-purple-900"
                >
                    ← Back to daily processes
                </Link>
                <h1 class="mt-3 text-2xl font-bold text-gray-950">Daily process: {{ processRun.process_date }}</h1>
                <p class="mt-2 text-sm text-gray-600">Run each command in order. Review its output before you continue.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span :class="[runStatusClasses(processRun.status), 'rounded-full px-3 py-1.5 text-sm font-semibold']">
                    {{ runStatusLabel(processRun.status) }}
                </span>
                <a
                    v-if="nextRunnableStep"
                    :href="`#step-${nextRunnableStep.id}`"
                    class="rounded-md bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-purple-500"
                >
                    Go to next step
                </a>
            </div>
        </div>

        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-900">
                        {{ processRun.completed_steps }} of {{ processRun.total_steps }} steps completed
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                        Created by {{ processRun.created_by ?? 'an admin' }}. The current command continues if you close this page.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-medium text-gray-500">
                    <span :class="[connectionMessage ? 'bg-amber-500' : 'bg-green-500', 'size-2 rounded-full']" />
                    {{ connectionMessage || 'Live updates connected' }}
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-purple-600 transition-all duration-500" :style="{ width: `${progressPercent}%` }" />
            </div>
        </section>

        <div v-if="actionError" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {{ actionError }}
        </div>

        <div v-if="processRun.status === 'completed'" class="mt-5 rounded-xl border border-green-200 bg-green-50 p-5">
            <p class="font-semibold text-green-900">Daily processing is complete.</p>
            <p class="mt-1 text-sm text-green-800">
                All {{ processRun.total_steps }} commands finished successfully for {{ processRun.process_date }}.
            </p>
        </div>

        <ol class="mt-6 space-y-4">
            <li
                v-for="step in processRun.steps"
                :id="`step-${step.id}`"
                :key="step.id"
                :class="[stepCardClasses(step), 'scroll-mt-24 rounded-xl border bg-white shadow-xs transition-colors']"
            >
                <div class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <div
                                :class="[
                                    stepNumberClasses(step.status),
                                    'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                                ]"
                            >
                                <svg
                                    v-if="step.status === 'queued' || step.status === 'running'"
                                    class="size-5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                                    <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                                </svg>
                                <span v-else-if="step.status === 'completed'">✓</span>
                                <span v-else-if="step.status === 'failed'">!</span>
                                <span v-else>{{ step.position }}</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold text-gray-950">{{ step.position }}. {{ step.name }}</h2>
                                    <span :class="[stepStatusClasses(step.status), 'rounded-full px-2.5 py-1 text-xs font-semibold']">
                                        {{ stepStatusLabel(step.status) }}
                                    </span>
                                    <span v-if="step.is_preview" class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                        Preview only
                                    </span>
                                    <span v-if="step.is_apply" class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">
                                        Applies changes
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-gray-600">{{ step.description }}</p>
                                <p
                                    v-if="step.is_apply"
                                    class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800"
                                >
                                    This command runs without --dry-run and changes stored data.
                                </p>
                                <code class="mt-3 block overflow-x-auto rounded-md bg-gray-100 px-3 py-2 font-mono text-xs text-gray-800">
                                    {{ step.command_line }}
                                </code>
                                <div v-if="step.started_at" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                                    <span>Attempt {{ step.attempts }}</span>
                                    <span v-if="step.duration_seconds !== null">Duration: {{ formatDuration(step.duration_seconds) }}</span>
                                    <span v-if="step.exit_code !== null">Exit code: {{ step.exit_code }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 lg:text-right">
                            <button
                                v-if="step.can_run"
                                type="button"
                                :disabled="submittingStepId !== null"
                                :class="[
                                    step.is_apply ? 'bg-rose-600 hover:bg-rose-500' : 'bg-purple-600 hover:bg-purple-500',
                                    'cursor-pointer rounded-md px-4 py-2 text-sm font-semibold text-white shadow-xs disabled:cursor-not-allowed disabled:opacity-60',
                                ]"
                                @click="runStep(step)"
                            >
                                {{ stepButtonLabel(step) }}
                            </button>
                            <p v-else-if="step.status === 'pending'" class="max-w-48 text-sm text-gray-500">Complete the prior step first.</p>
                            <p v-else-if="step.status === 'queued'" class="text-sm font-medium text-blue-700">Waiting for a queue worker</p>
                            <p v-else-if="step.status === 'running'" class="text-sm font-medium text-blue-700">Command is running</p>
                            <p v-else-if="step.status === 'completed'" class="text-sm font-medium text-green-700">Ready to continue</p>
                            <p v-else class="text-sm font-medium text-red-700">Read the error, then retry</p>
                        </div>
                    </div>

                    <section v-if="step.key === 'check-instruments'" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-amber-950">Resolve symbol name changes</h3>
                            <p class="mt-1 text-sm text-amber-900">
                                If a new symbol is a company name change, map the old symbol to the new symbol. You can add several mappings and
                                repeat this action.
                            </p>
                        </div>

                        <form v-if="step.can_manage_symbol_changes" class="mt-4" @submit.prevent="queueSymbolChanges(step)">
                            <div class="space-y-3">
                                <div
                                    v-for="(symbolChange, index) in symbolChangeForm.symbol_changes"
                                    :key="index"
                                    class="grid gap-3 rounded-lg border border-amber-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] sm:items-start"
                                >
                                    <div>
                                        <label :for="`old-symbol-${index}`" class="block text-xs font-semibold text-gray-700">Old symbol</label>
                                        <input
                                            :id="`old-symbol-${index}`"
                                            v-model="symbolChange.old_symbol"
                                            type="text"
                                            name="old_symbol"
                                            maxlength="50"
                                            required
                                            autocomplete="off"
                                            autocapitalize="characters"
                                            spellcheck="false"
                                            placeholder="BURGERKING"
                                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm text-gray-900 uppercase shadow-xs focus:border-amber-500 focus:ring-amber-500"
                                            @blur="normalizeSymbolChange(index, 'old_symbol')"
                                        />
                                        <p v-if="symbolChangeError(index, 'old_symbol')" class="mt-1 text-xs text-red-700">
                                            {{ symbolChangeError(index, 'old_symbol') }}
                                        </p>
                                    </div>

                                    <span class="hidden pt-8 text-sm font-semibold text-amber-700 sm:block" aria-hidden="true">→</span>

                                    <div>
                                        <label :for="`new-symbol-${index}`" class="block text-xs font-semibold text-gray-700">New symbol</label>
                                        <input
                                            :id="`new-symbol-${index}`"
                                            v-model="symbolChange.new_symbol"
                                            type="text"
                                            name="new_symbol"
                                            maxlength="50"
                                            required
                                            autocomplete="off"
                                            autocapitalize="characters"
                                            spellcheck="false"
                                            placeholder="RBA"
                                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm text-gray-900 uppercase shadow-xs focus:border-amber-500 focus:ring-amber-500"
                                            @blur="normalizeSymbolChange(index, 'new_symbol')"
                                        />
                                        <p v-if="symbolChangeError(index, 'new_symbol')" class="mt-1 text-xs text-red-700">
                                            {{ symbolChangeError(index, 'new_symbol') }}
                                        </p>
                                    </div>

                                    <button
                                        v-if="symbolChangeForm.symbol_changes.length > 1"
                                        type="button"
                                        class="mt-6 inline-flex size-9 cursor-pointer items-center justify-center rounded-md text-gray-500 hover:bg-red-50 hover:text-red-700 sm:mt-6"
                                        :aria-label="`Remove symbol mapping ${index + 1}`"
                                        @click="removeSymbolChange(index)"
                                    >
                                        <TrashIcon class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>

                            <p v-if="formErrorMessage(symbolChangeForm.errors.symbol_changes)" class="mt-3 text-sm text-red-700">
                                {{ formErrorMessage(symbolChangeForm.errors.symbol_changes) }}
                            </p>

                            <div class="mt-4 flex flex-col gap-3 border-t border-amber-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <button
                                    type="button"
                                    :disabled="symbolChangeForm.symbol_changes.length >= 25 || symbolChangeForm.processing"
                                    class="inline-flex w-fit cursor-pointer items-center gap-1.5 text-sm font-semibold text-amber-900 hover:text-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="addSymbolChange"
                                >
                                    <PlusIcon class="size-4" aria-hidden="true" />
                                    Add another mapping
                                </button>

                                <button
                                    type="submit"
                                    :disabled="symbolChangeForm.processing"
                                    class="cursor-pointer rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {{ symbolChangeForm.processing ? 'Adding to queue...' : 'Run changes and check again' }}
                                </button>
                            </div>

                            <p class="mt-3 text-xs leading-5 text-amber-900">
                                This updates symbols across the backtest tables. The queue runs each mapping in order, then reruns the new instrument
                                check.
                            </p>
                        </form>

                        <p v-else-if="step.status === 'queued' || step.status === 'running'" class="mt-3 text-sm font-medium text-blue-800">
                            Wait for this command to finish. The instrument check runs after all queued symbol changes.
                        </p>
                        <p v-else-if="step.attempts === 0" class="mt-3 text-sm text-amber-900">
                            Run the new instrument check first. This tool will become available after the check finishes.
                        </p>
                        <p v-else class="mt-3 text-sm text-gray-600">Symbol changes are locked because a later checklist step has started.</p>
                    </section>

                    <div
                        v-if="dailyDataProgressByStepId[step.id]"
                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4"
                        role="progressbar"
                        :aria-valuenow="dailyDataProgressByStepId[step.id].percentage"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="`Daily data ${dailyDataProgressByStepId[step.id].percentage}% complete`"
                    >
                        <div class="flex items-center justify-between gap-4 text-sm font-semibold text-blue-900">
                            <span>Daily data progress</span>
                            <span class="tabular-nums">{{ dailyDataProgressByStepId[step.id].percentage }}%</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-blue-100">
                            <div
                                :class="[
                                    step.status === 'completed' ? 'bg-green-600' : 'bg-blue-600',
                                    'h-full rounded-full transition-all duration-500',
                                ]"
                                :style="{ width: `${dailyDataProgressByStepId[step.id].percentage}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-blue-800">
                            {{ formatInteger(dailyDataProgressByStepId[step.id].processedJobs) }} of
                            {{ formatInteger(dailyDataProgressByStepId[step.id].totalJobs) }} instrument jobs completed.
                        </p>
                    </div>

                    <div v-if="step.error_message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        {{ step.error_message }}
                    </div>

                    <div v-if="showOutputControl(step)" class="mt-4 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            class="cursor-pointer text-sm font-semibold text-gray-700 hover:text-gray-950"
                            @click="toggleOutput(step.id)"
                        >
                            {{ expandedStepIds.has(step.id) ? 'Hide terminal output' : 'Show terminal output' }}
                        </button>

                        <pre
                            v-if="expandedStepIds.has(step.id)"
                            :id="`process-output-${step.id}`"
                            class="mt-3 max-h-[32rem] min-h-28 overflow-auto rounded-lg bg-gray-950 p-4 font-mono text-xs leading-5 text-gray-100 shadow-inner"
                            aria-live="polite"
                        ><template v-if="visibleOutputChunksByStepId[step.id]?.length"><span
                                    v-for="chunk in visibleOutputChunksByStepId[step.id]"
                                    :key="chunk.id"
                                    :class="chunk.stream === 'stderr' ? 'text-red-300' : 'text-gray-100'"
                                >{{ chunk.output }}</span></template><span v-else class="text-gray-400">{{ outputPlaceholder(step) }}</span></pre>
                    </div>
                </div>
            </li>
        </ol>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-2 opacity-0"
        >
            <button
                v-if="showGoToTopButton"
                type="button"
                class="fixed right-4 bottom-4 z-30 inline-flex cursor-pointer items-center gap-2 rounded-full bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg hover:bg-purple-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 sm:right-6 sm:bottom-6"
                @click="goToTop"
            >
                <ArrowUpIcon class="size-4" aria-hidden="true" />
                Go to top
            </button>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { ArrowUpIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { AdminProcessOutputChunk, AdminProcessRun, AdminProcessRunStatus, AdminProcessStep, AdminProcessStepStatus } from '@/types/AdminProcess';

interface UpdateResponse {
    run: AdminProcessRun;
    output_chunks: AdminProcessOutputChunk[];
    next_cursor: number;
    has_more: boolean;
}

interface DailyDataProgress {
    percentage: number;
    processedJobs: number;
    totalJobs: number;
}

interface SymbolChangeInput {
    old_symbol: string;
    new_symbol: string;
}

interface SymbolChangeForm {
    symbol_changes: SymbolChangeInput[];
}

type SymbolChangeField = keyof SymbolChangeInput;

const props = defineProps<{
    processRun: AdminProcessRun;
}>();

const processRun = ref<AdminProcessRun>(copyRun(props.processRun));
const outputChunks = ref<Record<number, AdminProcessOutputChunk[]>>({});
const expandedStepIds = ref<Set<number>>(new Set());
const outputCursor = ref(0);
const submittingStepId = ref<number | null>(null);
const actionError = ref('');
const connectionMessage = ref('');
const showGoToTopButton = ref(false);
const symbolChangeForm = useForm<SymbolChangeForm>({
    symbol_changes: [emptySymbolChange()],
});
let pollTimer: number | undefined;
let pollController: AbortController | null = null;

const progressPercent = computed(() => {
    if (processRun.value.total_steps === 0) {
        return 0;
    }

    return Math.round((processRun.value.completed_steps / processRun.value.total_steps) * 100);
});

const nextRunnableStep = computed(() => processRun.value.steps.find((step) => step.can_run) ?? null);

const dailyDataProgressByStepId = computed<Record<number, DailyDataProgress>>(() => {
    const progressByStepId: Record<number, DailyDataProgress> = {};

    for (const step of processRun.value.steps) {
        if (step.key !== 'process-daily-data') {
            continue;
        }

        for (const chunk of outputChunks.value[step.id] ?? []) {
            const matches = chunk.output.matchAll(/Daily data progress:\s*(\d+)%\s*\((\d+)\/(\d+)\)/g);

            for (const match of matches) {
                progressByStepId[step.id] = {
                    percentage: Math.min(100, Math.max(0, Number(match[1]))),
                    processedJobs: Number(match[2]),
                    totalJobs: Number(match[3]),
                };
            }
        }
    }

    return progressByStepId;
});

const visibleOutputChunksByStepId = computed<Record<number, AdminProcessOutputChunk[]>>(() => {
    const visibleChunksByStepId: Record<number, AdminProcessOutputChunk[]> = {};

    for (const step of processRun.value.steps) {
        const chunks = outputChunks.value[step.id] ?? [];

        if (step.key !== 'process-daily-data') {
            visibleChunksByStepId[step.id] = chunks;

            continue;
        }

        visibleChunksByStepId[step.id] = chunks.flatMap((chunk) => {
            const output = chunk.output.replace(/^Daily data progress:\s*\d+%\s*\(\d+\/\d+\)\r?\n?/gm, '');

            return output === '' ? [] : [{ ...chunk, output }];
        });
    }

    return visibleChunksByStepId;
});

watch(
    () => props.processRun,
    (updatedRun) => {
        processRun.value = copyRun(updatedRun);
        expandActiveSteps();
    },
    { deep: true },
);

onMounted(() => {
    expandActiveSteps();
    updateGoToTopButton();
    window.addEventListener('scroll', updateGoToTopButton, { passive: true });
    void pollUpdates();
});

onBeforeUnmount(() => {
    if (pollTimer !== undefined) {
        window.clearTimeout(pollTimer);
    }

    pollController?.abort();
    window.removeEventListener('scroll', updateGoToTopButton);
});

function copyRun(run: AdminProcessRun): AdminProcessRun {
    return {
        ...run,
        steps: run.steps.map((step) => ({ ...step })),
    };
}

async function pollUpdates(): Promise<void> {
    pollController = new AbortController();

    try {
        const response = await fetch(`/admin/process-runs/${processRun.value.id}/updates?after=${outputCursor.value}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: pollController.signal,
        });

        if (!response.ok) {
            throw new Error(`Update request failed with status ${response.status}.`);
        }

        const data = (await response.json()) as UpdateResponse;
        processRun.value = copyRun(data.run);

        for (const chunk of data.output_chunks) {
            outputChunks.value[chunk.step_id] ??= [];
            outputChunks.value[chunk.step_id].push(chunk);
        }

        outputCursor.value = data.next_cursor;
        connectionMessage.value = '';
        expandActiveSteps();
        await scrollActiveOutputToEnd();

        if (data.run.status === 'completed' && !data.has_more) {
            return;
        }

        const hasActiveStep = data.run.steps.some((step) => ['queued', 'running'].includes(step.status));
        scheduleNextPoll(data.has_more ? 0 : hasActiveStep ? 1000 : 2500);
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        connectionMessage.value = 'Live updates paused. Trying again.';
        scheduleNextPoll(3000);
    }
}

function scheduleNextPoll(delay: number): void {
    pollTimer = window.setTimeout(() => void pollUpdates(), delay);
}

function runStep(step: AdminProcessStep): void {
    if (step.is_apply && !window.confirm(`Run "${step.name}" for ${processRun.value.process_date} without --dry-run? This changes stored data.`)) {
        return;
    }

    actionError.value = '';
    submittingStepId.value = step.id;

    router.post(
        `/admin/process-runs/${processRun.value.id}/steps/${step.id}/run`,
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                actionError.value = String(errors.step ?? 'The step could not be added to the queue.');
            },
            onFinish: () => {
                submittingStepId.value = null;
            },
        },
    );
}

function emptySymbolChange(): SymbolChangeInput {
    return {
        old_symbol: '',
        new_symbol: '',
    };
}

function addSymbolChange(): void {
    if (symbolChangeForm.symbol_changes.length >= 25) {
        return;
    }

    symbolChangeForm.symbol_changes.push(emptySymbolChange());
}

function removeSymbolChange(index: number): void {
    symbolChangeForm.symbol_changes.splice(index, 1);
    symbolChangeForm.clearErrors();
}

function normalizeSymbolChange(index: number, field: SymbolChangeField): void {
    const symbolChange = symbolChangeForm.symbol_changes[index];

    if (!symbolChange) {
        return;
    }

    symbolChange[field] = symbolChange[field].trim().toUpperCase();
}

function symbolChangeError(index: number, field: SymbolChangeField): string {
    const errorKey: `symbol_changes.${number}.${SymbolChangeField}` = `symbol_changes.${index}.${field}`;

    return formErrorMessage(symbolChangeForm.errors[errorKey]);
}

function formErrorMessage(error: string | string[] | undefined): string {
    return Array.isArray(error) ? (error[0] ?? '') : (error ?? '');
}

function queueSymbolChanges(step: AdminProcessStep): void {
    for (const index of symbolChangeForm.symbol_changes.keys()) {
        normalizeSymbolChange(index, 'old_symbol');
        normalizeSymbolChange(index, 'new_symbol');
    }

    const mappingCount = symbolChangeForm.symbol_changes.length;
    const mappingLabel = mappingCount === 1 ? 'mapping' : 'mappings';

    if (!window.confirm(`Run ${mappingCount} symbol ${mappingLabel}? This changes backtest data and then runs the new instrument check again.`)) {
        return;
    }

    symbolChangeForm.post(`/admin/process-runs/${processRun.value.id}/steps/${step.id}/symbol-changes`, {
        preserveScroll: true,
        onSuccess: () => {
            symbolChangeForm.reset();
        },
    });
}

function stepButtonLabel(step: AdminProcessStep): string {
    if (submittingStepId.value === step.id) {
        return 'Adding to queue...';
    }

    if (step.status === 'failed') {
        return step.is_apply ? 'Retry apply step' : 'Retry step';
    }

    return step.is_apply ? 'Apply changes' : 'Run step';
}

function expandActiveSteps(): void {
    const expanded = new Set(expandedStepIds.value);

    for (const step of processRun.value.steps) {
        if (['queued', 'running', 'failed'].includes(step.status)) {
            expanded.add(step.id);
        }
    }

    expandedStepIds.value = expanded;
}

function toggleOutput(stepId: number): void {
    const expanded = new Set(expandedStepIds.value);

    if (expanded.has(stepId)) {
        expanded.delete(stepId);
    } else {
        expanded.add(stepId);
    }

    expandedStepIds.value = expanded;
}

async function scrollActiveOutputToEnd(): Promise<void> {
    await nextTick();

    const activeStep = processRun.value.steps.find((step) => ['queued', 'running'].includes(step.status));

    if (!activeStep || !expandedStepIds.value.has(activeStep.id)) {
        return;
    }

    const outputElement = document.getElementById(`process-output-${activeStep.id}`);

    if (outputElement) {
        outputElement.scrollTop = outputElement.scrollHeight;
    }
}

function showOutputControl(step: AdminProcessStep): boolean {
    return step.status !== 'pending' || step.attempts > 0;
}

function outputPlaceholder(step: AdminProcessStep): string {
    if (step.status === 'queued') {
        return 'Waiting for a queue worker...';
    }

    if (step.status === 'running') {
        return 'The command is running. Waiting for output...';
    }

    return 'No terminal output was saved.';
}

function updateGoToTopButton(): void {
    showGoToTopButton.value = window.scrollY >= 600;
}

function goToTop(): void {
    const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

    window.scrollTo({ top: 0, behavior });
}

function formatInteger(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

function formatDuration(totalSeconds: number): string {
    const seconds = Math.max(0, Math.round(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    if (hours > 0) {
        return `${hours}h ${minutes}m ${remainingSeconds}s`;
    }

    if (minutes > 0) {
        return `${minutes}m ${remainingSeconds}s`;
    }

    return `${remainingSeconds}s`;
}

function runStatusLabel(status: AdminProcessRunStatus): string {
    return {
        pending: 'Not started',
        in_progress: 'In progress',
        completed: 'Completed',
        failed: 'Needs attention',
    }[status];
}

function runStatusClasses(status: AdminProcessRunStatus): string {
    return {
        pending: 'bg-gray-100 text-gray-700',
        in_progress: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    }[status];
}

function stepStatusLabel(status: AdminProcessStepStatus): string {
    return {
        pending: 'Pending',
        queued: 'Queued',
        running: 'Running',
        completed: 'Completed',
        failed: 'Failed',
    }[status];
}

function stepStatusClasses(status: AdminProcessStepStatus): string {
    return {
        pending: 'bg-gray-100 text-gray-700',
        queued: 'bg-blue-100 text-blue-700',
        running: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    }[status];
}

function stepNumberClasses(status: AdminProcessStepStatus): string {
    return {
        pending: 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
        queued: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
        running: 'bg-blue-600 text-white',
        completed: 'bg-green-600 text-white',
        failed: 'bg-red-600 text-white',
    }[status];
}

function stepCardClasses(step: AdminProcessStep): string {
    if (step.status === 'running' || step.status === 'queued') {
        return 'border-blue-400 ring-2 ring-blue-100';
    }

    if (step.status === 'failed') {
        return 'border-red-300 ring-2 ring-red-50';
    }

    if (step.can_run) {
        if (step.is_apply) {
            return 'border-rose-400 ring-2 ring-rose-100';
        }

        return 'border-purple-400 ring-2 ring-purple-100';
    }

    return 'border-gray-200';
}
</script>
