<template>
    <svg
        v-if="points.length >= 2"
        :viewBox="`0 0 ${VIEWBOX_WIDTH} ${VIEWBOX_HEIGHT}`"
        preserveAspectRatio="none"
        class="h-full w-full"
        aria-hidden="true"
    >
        <defs>
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="strokeColor" :stop-opacity="fillTopOpacity" />
                <stop offset="100%" :stop-color="strokeColor" stop-opacity="0" />
            </linearGradient>
        </defs>
        <rect
            v-if="drawdownRect"
            :x="drawdownRect.x"
            y="0"
            :width="drawdownRect.width"
            :height="VIEWBOX_HEIGHT"
            fill="#ef4444"
            :opacity="drawdownOpacity"
        />
        <path :d="areaPath" :fill="`url(#${gradientId})`" />
        <path
            :d="linePath"
            :stroke="strokeColor"
            stroke-width="1.5"
            fill="none"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
        <circle
            v-if="troughPoint"
            :cx="troughPoint.x"
            :cy="troughPoint.y"
            r="3"
            :fill="troughFillColor"
            :stroke="markerStroke"
            stroke-width="1.5"
            vector-effect="non-scaling-stroke"
        />
        <circle
            v-if="lastPoint"
            :cx="lastPoint.x"
            :cy="lastPoint.y"
            r="3"
            :fill="strokeColor"
            :stroke="markerStroke"
            stroke-width="1.5"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { BacktestDailySnapshot } from '@/types/app/Models/BacktestDailySnapshot';

const props = withDefaults(
    defineProps<{
        snapshots: BacktestDailySnapshot[];
        maxDrawdownStartDate?: string | null;
        maxDrawdownEndDate?: string | null;
        positive?: boolean;
        variant?: 'light' | 'dark';
    }>(),
    {
        maxDrawdownStartDate: null,
        maxDrawdownEndDate: null,
        positive: true,
        variant: 'light',
    },
);

const VIEWBOX_WIDTH = 400;
const VIEWBOX_HEIGHT = 100;

const strokeColor = computed(() => {
    if (props.variant === 'dark') {
        return props.positive ? '#34d399' : '#f87171';
    }
    return props.positive ? '#10b981' : '#ef4444';
});
const troughFillColor = computed(() => (props.variant === 'dark' ? '#f87171' : '#ef4444'));
const markerStroke = computed(() => (props.variant === 'dark' ? '#0f172a' : 'white'));
const drawdownOpacity = computed(() => (props.variant === 'dark' ? 0.18 : 0.08));
const fillTopOpacity = computed(() => (props.variant === 'dark' ? 0.35 : 0.22));
const gradientId = computed(() => `eq-spark-${Math.random().toString(36).slice(2, 10)}`);

const points = computed(() => {
    if (!props.snapshots || props.snapshots.length < 2) return [];
    const navs = props.snapshots.map((s) => Number(s.nav));
    const min = Math.min(...navs);
    const max = Math.max(...navs);
    const range = max - min || 1;
    const n = props.snapshots.length;
    return props.snapshots.map((s, i) => ({
        x: (i / (n - 1)) * VIEWBOX_WIDTH,
        y: VIEWBOX_HEIGHT - ((Number(s.nav) - min) / range) * VIEWBOX_HEIGHT,
        date: s.date.substring(0, 10),
    }));
});

const linePath = computed(() => {
    if (points.value.length < 2) return '';
    return 'M ' + points.value.map((p) => `${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' L ');
});

const areaPath = computed(() => {
    if (points.value.length < 2) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `M ${first.x.toFixed(2)} ${VIEWBOX_HEIGHT} L ${points.value
        .map((p) => `${p.x.toFixed(2)} ${p.y.toFixed(2)}`)
        .join(' L ')} L ${last.x.toFixed(2)} ${VIEWBOX_HEIGHT} Z`;
});

const drawdownRect = computed(() => {
    if (!props.maxDrawdownStartDate || !props.maxDrawdownEndDate || points.value.length === 0) return null;
    const start = props.maxDrawdownStartDate.substring(0, 10);
    const end = props.maxDrawdownEndDate.substring(0, 10);
    const startPoint = points.value.find((p) => p.date >= start);
    const endPoint = [...points.value].reverse().find((p) => p.date <= end);
    if (!startPoint || !endPoint || endPoint.x <= startPoint.x) return null;
    return { x: startPoint.x, width: endPoint.x - startPoint.x };
});

const troughPoint = computed(() => {
    if (!props.maxDrawdownEndDate || points.value.length === 0) return null;
    const end = props.maxDrawdownEndDate.substring(0, 10);
    return [...points.value].reverse().find((p) => p.date <= end) ?? null;
});

const lastPoint = computed(() => (points.value.length > 0 ? points.value[points.value.length - 1] : null));
</script>
