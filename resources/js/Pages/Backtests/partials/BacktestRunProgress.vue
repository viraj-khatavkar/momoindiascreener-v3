<template>
    <div class="run-panel @container rounded-2xl px-5 py-5 text-white shadow-sm sm:px-7 sm:py-6">
        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 aria-live="polite" aria-atomic="true" class="text-lg leading-tight font-semibold tracking-tight sm:text-xl">{{ stageLabel }}</h2>
                <p v-if="waitingForSource && source" class="mt-1 text-sm break-words text-violet-200">
                    Running source backtest: {{ source.name }} ({{ source.progress }}%)
                </p>
            </div>
            <div aria-hidden="true" class="flex shrink-0 items-baseline gap-0.5 font-mono text-3xl font-semibold tabular-nums sm:text-4xl">
                <span v-for="(digit, i) in digits" :key="digits.length - i" class="flap">
                    <Transition name="turn"
                        ><span :key="digit">{{ digit }}</span></Transition
                    >
                </span>
                <span v-if="progress > 0" class="ml-1 text-sm text-violet-200">%</span>
            </div>
        </div>

        <BacktestProgressBar :progress="progress" label="Backtest progress" class="mt-5" />

        <div class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
            <p v-if="waitingForSource" class="detail">
                This backtest will start only after the source completes successfully. You can leave this page.
            </p>
            <p v-else-if="progress === 0" class="detail">Your run will start when a worker is free. You can leave this page.</p>
            <p v-else-if="currentStage === 0" class="detail">Loading prices and strategy data</p>
            <p v-else-if="currentStage === 1" class="detail">{{ simulatedDaysPct }}% of trading days processed</p>
            <p v-else class="detail">All trading days processed</p>
            <button
                type="button"
                :aria-expanded="ticketOpen"
                :aria-controls="ticketId"
                class="ml-auto inline-flex min-h-11 shrink-0 cursor-pointer items-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-300"
                @click="ticketOpen = !ticketOpen"
            >
                <span class="ticket-tab inline-flex items-center gap-2 rounded-sm px-2.5 py-1.5 text-xs font-semibold">
                    <TicketIcon aria-hidden="true" class="size-4" />
                    Run ticket
                    <ChevronDownIcon aria-hidden="true" class="size-3" :class="{ 'rotate-180': ticketOpen }" />
                </span>
            </button>
        </div>

        <Transition name="feed">
            <ol
                v-show="ticketOpen"
                :id="ticketId"
                aria-label="Run stages"
                class="ticket-paper mt-1 grid gap-x-4 gap-y-3 rounded-t-sm p-4 text-slate-900 @md:grid-cols-2 @3xl:grid-cols-4"
            >
                <li
                    v-for="(stage, i) in stages"
                    :key="stage.from"
                    :aria-current="i === currentStage ? 'step' : undefined"
                    class="flex items-start gap-2.5"
                >
                    <span aria-hidden="true" class="punch mt-1.5" :class="{ current: i === currentStage }">
                        <Transition name="punch"><span v-if="i < currentStage" class="hole" /></Transition>
                    </span>
                    <div>
                        <span class="block text-sm leading-snug font-medium">{{ stage.label }}</span>
                        <span class="text-xs" :class="i === currentStage ? 'font-semibold text-purple-800' : 'text-slate-600'">
                            {{ i < currentStage ? 'Done' : i === currentStage ? 'In progress' : 'Upcoming' }}
                        </span>
                    </div>
                </li>
            </ol>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, useId } from 'vue';
import { ChevronDownIcon, TicketIcon } from '@heroicons/vue/20/solid';
import BacktestProgressBar from '@/Components/BacktestProgressBar.vue';
import type { Backtest } from '@/types/app/Models/Backtest';

const props = defineProps<{ progress: number; source: Pick<Backtest, 'name' | 'status' | 'progress'> | null }>();

// Thresholds reported by RunBacktestAction and RunBacktestJob.
const stages = [
    { from: 1, label: 'Preparing market data' },
    { from: 5, label: 'Simulating trades' },
    { from: 95, label: 'Computing metrics' },
    { from: 98, label: 'Calculating market cap allocation' },
];
const currentStage = computed(() => stages.findLastIndex((stage) => props.progress >= stage.from));
const waitingForSource = computed(() => props.progress === 0 && props.source?.status === 'running');
const stageLabel = computed(() => stages[currentStage.value]?.label ?? (waitingForSource.value ? 'Waiting for source' : 'Waiting for a worker'));
const simulatedDaysPct = computed(() => Math.round(((props.progress - 5) / 89) * 100));
const digits = computed(() => (props.progress > 0 ? String(props.progress).split('') : ['–', '–']));
const ticketId = useId();
const ticketOpen = ref(false);
</script>

<style scoped>
.run-panel {
    background: radial-gradient(ellipse at top right, #392d47, transparent 60%), #201d29;
}
.detail {
    flex: 1 1 14rem;
    color: #d2cadf;
    font-size: 0.8125rem;
    line-height: 1.5;
}
.flap {
    position: relative;
    display: grid;
    overflow: hidden;
    min-width: 0.79em;
    border-radius: 4px;
    background: linear-gradient(#48424f 49%, #37323e 50%);
    box-shadow:
        inset 0 1px 0 #ffffff26,
        0 1px 2px #120f19;
    line-height: 1.2;
    text-align: center;
    perspective: 120px;
}
.flap > span {
    grid-area: 1 / 1;
}
.flap::after {
    content: '';
    position: absolute;
    inset: 50% 0 auto;
    border-top: 1px solid #1b162a66;
}
.ticket-tab {
    background: #e8dcf5;
    color: #41324e;
    mask:
        radial-gradient(circle at left, transparent 2px, black 2.5px) left / 52% 100% no-repeat,
        radial-gradient(circle at right, transparent 2px, black 2.5px) right / 52% 100% no-repeat;
}
.ticket-paper {
    background: #f4efe5;
    mask: radial-gradient(circle at 4px 100%, transparent 2px, black 2.5px) 0 0 / 8px 100% repeat-x;
}
@container (min-width: 48rem) {
    .ticket-paper li + li {
        border-inline-start: 1px dashed #c8bfb8;
        padding-inline-start: 0.75rem;
    }
}
.punch {
    position: relative;
    flex: none;
    width: 11px;
    height: 11px;
    border: 1px dashed #8f8590;
    border-radius: 50%;
}
.punch.current {
    border: 3px double #733bb4;
    background: #d9b9f3;
}
.hole {
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: #302938;
    box-shadow:
        inset 0 1px 2px #16101f,
        0 1px 0 white;
}
@media (prefers-reduced-motion: no-preference) {
    .turn-enter-active,
    .turn-leave-active {
        transition:
            transform 300ms cubic-bezier(0.22, 0.75, 0.3, 1),
            opacity 240ms;
    }
    .turn-enter-from {
        opacity: 0;
        transform: translateY(-50%) rotateX(60deg);
    }
    .turn-leave-to {
        opacity: 0;
        transform: translateY(50%) rotateX(-60deg);
    }
    .ticket-tab {
        transition: transform 180ms ease-out;
    }
    button:focus-visible .ticket-tab {
        transform: translateY(-2px) rotate(-2deg);
    }
    @media (hover: hover) {
        button:hover .ticket-tab {
            transform: translateY(-2px) rotate(-2deg);
        }
    }
    .feed-enter-active,
    .feed-leave-active {
        transition:
            transform 220ms ease-out,
            opacity 180ms;
        transform-origin: top right;
    }
    .feed-enter-from,
    .feed-leave-to {
        opacity: 0;
        transform: perspective(400px) translateY(-5px) rotateX(-10deg);
    }
    .punch-enter-active {
        animation: punch-hole 280ms ease-out;
    }
}
@keyframes punch-hole {
    0% {
        transform: scale(0.4);
    }
    60% {
        transform: scale(1.18);
    }
    100% {
        transform: scale(1);
    }
}
</style>
