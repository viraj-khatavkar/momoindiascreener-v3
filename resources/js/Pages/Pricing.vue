<template>
    <div class="bg-white">
        <Head title="Pricing">
            <meta head-key="og-title" property="og:title" content="Pricing" />
            <meta
                head-key="og-description"
                property="og:description"
                content="Pricing details for momoindiascreener.in. There are three plans available - Monthly, Yearly and Forever"
            />
            <meta head-key="twitter-title" name="twitter:title" content="Pricing" />
            <meta
                head-key="twitter-description"
                name="twitter:description"
                content="Pricing details for momoindiascreener.in. There are three plans available - Monthly, Yearly and Forever"
            />
        </Head>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-4xl text-center">
                <h2 class="text-base/7 font-semibold text-purple-600">Pricing</h2>
                <p class="mt-2 text-5xl font-semibold tracking-tight text-balance text-gray-900 sm:text-6xl">Simple no-tricks pricing</p>
            </div>
            <p class="mx-auto mt-6 max-w-2xl text-center text-lg font-medium text-pretty text-gray-600 sm:text-xl/8">
                Get access to all current and future screening features. All plans include backtests.
            </p>
            <div class="mt-8">
                <InfoAlert>
                    If you subscribe to the forever plan, your purchase will grant you unlimited access to the screener and backtests on
                    momoindiascreener for the lifetime of the website. Your access is valid
                    <span class="font-bold text-red-600"> until the website is operational. Forever doesn't mean for your lifetime! </span>
                </InfoAlert>
            </div>
            <div class="isolate mx-auto mt-16 grid max-w-md grid-cols-1 gap-y-8 sm:mt-20 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                <div
                    v-for="(tier, tierIdx) in tiers"
                    :key="tier.id"
                    :class="[
                        tier.mostPopular ? 'lg:z-10 lg:rounded-b-none' : 'lg:mt-8',
                        tierIdx === 0 ? 'lg:rounded-r-none' : '',
                        tierIdx === tiers.length - 1 ? 'lg:rounded-l-none' : '',
                        'flex flex-col justify-between rounded-3xl bg-white p-8 ring-1 ring-gray-200 xl:p-10',
                    ]"
                >
                    <div>
                        <div class="flex items-center justify-between gap-x-4">
                            <h3 :id="tier.id" :class="[tier.mostPopular ? 'text-purple-600' : 'text-gray-900', 'text-lg/8 font-semibold']">
                                {{ tier.name }}
                            </h3>
                            <p v-if="tier.mostPopular" class="rounded-full bg-purple-600/10 px-2.5 py-1 text-xs/5 font-semibold text-purple-600">
                                Most popular
                            </p>
                        </div>
                        <p class="mt-6 flex items-baseline gap-x-1">
                            <span class="text-4xl font-semibold tracking-tight text-gray-900">
                                {{ formatCurrency(prices[tier.plan]) }}
                            </span>
                            <span class="text-sm/6 font-semibold text-gray-600">
                                {{ tier.priceAppend }}
                            </span>
                        </p>
                        <ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
                            <li v-for="feature in tier.features" :key="feature" class="flex gap-x-3">
                                <CheckIcon aria-hidden="true" class="h-6 w-5 flex-none text-purple-600" />
                                {{ feature }}
                            </li>
                        </ul>
                    </div>
                    <Link
                        :href="tier.href"
                        :aria-describedby="tier.id"
                        :class="[
                            tier.mostPopular
                                ? 'bg-purple-600 text-white shadow-xs hover:bg-purple-500'
                                : 'text-purple-600 ring-1 ring-purple-200 ring-inset hover:ring-purple-300',
                            'mt-8 block rounded-md px-3 py-2 text-center text-sm/6 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 focus-visible:outline-solid',
                        ]"
                    >
                        Buy plan
                    </Link>
                </div>
            </div>

            <div class="mx-auto mt-8 font-bold tracking-tight text-gray-900">* Adjusted historical price data is available from 2011.</div>
            <div class="mx-auto mt-8 font-bold tracking-tight text-gray-900">Go through the FAQs at bottom of this page for more information.</div>

            <div
                class="mt-8 mb-16 flex flex-col items-start gap-x-8 gap-y-6 rounded-3xl p-8 ring-1 ring-gray-900/10 sm:gap-y-10 sm:p-10 lg:col-span-2 lg:flex-row lg:items-center"
            >
                <div class="lg:min-w-0 lg:flex-1">
                    <h3 class="text-lg leading-8 font-semibold tracking-tight text-purple-600">
                        Please read below points before buying Premium Plan
                    </h3>
                    <ul class="mt-1 list-disc text-base leading-7 text-gray-600">
                        <li>We're not a SEBI registered advisor. We're just providing a tool to screen for momentum stocks.</li>
                        <li>We're not responsible for any loss or profit made by using our tool.</li>
                        <li>
                            We strive to update the data at end of day. But there is no guarantee for the same. It can even be delayed by few days in
                            certain cases.
                        </li>
                        <li>Please read our Terms & Conditions before buying the plan.</li>
                    </ul>
                </div>
                <Link
                    href="/terms-conditions"
                    class="rounded-md px-3.5 py-2 text-sm leading-6 font-semibold text-purple-600 ring-1 ring-purple-200 ring-inset hover:ring-purple-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 focus-visible:outline-solid"
                >
                    Check Terms & Conditions &rarr;
                </Link>
            </div>
        </div>
        <Faqs />
    </div>
</template>

<script setup lang="ts">
import { CheckIcon } from '@heroicons/vue/20/solid';
import { Head, Link } from '@inertiajs/vue3';
import InfoAlert from '@/Components/Alerts/InfoAlert.vue';
import Faqs from '@/Pages/Faqs.vue';
import { formatCurrency } from '@/utils';

type Plan = 'monthly' | 'yearly' | 'forever';

defineProps<{
    prices: Record<Plan, number>;
}>();

const tiers = [
    {
        name: 'Monthly',
        id: 'tier-monthly',
        href: '/billing/monthly/accept-terms',
        plan: 'monthly',
        priceAppend: '/month',
        features: ['Momentum Screener', 'Backtests', 'Export to Excel', 'Customize Columns for Results', 'Check Historical Ranks *', 'Email Support'],
        mostPopular: false,
    },
    {
        name: 'Yearly',
        id: 'tier-yearly',
        href: '/billing/yearly/accept-terms',
        plan: 'yearly',
        priceAppend: '/year',
        features: [
            'Momentum Screener',
            'Backtests',
            'Monthly AMAs with Viraj',
            'Export to Excel',
            'Customize Columns for Results',
            'Community Slack Access',
            'Check Historical Ranks *',
            'Email Support',
        ],
        mostPopular: true,
    },
    {
        name: 'Forever',
        id: 'tier-forever',
        href: '/billing/forever/accept-terms',
        plan: 'forever',
        priceAppend: '/forever',
        features: [
            'Momentum Screener',
            'Backtests',
            'Monthly AMAs with Viraj',
            'Export to Excel',
            'Customize Columns for Results',
            'Community Slack Access',
            'Check Historical Ranks *',
            'Email Support',
        ],
        mostPopular: false,
    },
] satisfies {
    name: string;
    id: string;
    href: string;
    plan: Plan;
    priceAppend: string;
    features: string[];
    mostPopular: boolean;
}[];
</script>
