<template>
    <div class="mx-auto max-w-7xl text-gray-900">
        <Head :title="instrument.symbol">
            <meta
                head-key="og-title"
                property="og:title"
                :content="instrument.name ? `${instrument.symbol} (${instrument.name})` : instrument.symbol"
            />
            <meta
                head-key="og-description"
                property="og:description"
                :content="`Explore price history, returns, risk and company events for ${instrument.symbol}.`"
            />
        </Head>

        <header id="instrument-top" class="scroll-mt-32 border-b border-gray-200 pb-8 sm:scroll-mt-24">
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs font-medium">
                <p class="tracking-widest text-purple-700 uppercase">Instrument overview</p>
                <p class="flex items-center gap-1.5 rounded-full bg-gray-50 px-3 py-1.5 text-gray-600">
                    <ClockIcon class="size-4" aria-hidden="true" />
                    Data as of <time :datetime="instrument.date">{{ formatDate(instrument.date) }}</time>
                </p>
            </div>
            <div class="mt-5 flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-semibold tracking-tight break-words sm:text-4xl">{{ instrument.symbol }}</h1>
                        <span class="rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-500"
                            >NSE · {{ instrument.series }}</span
                        >
                    </div>
                    <p v-if="instrument.name" class="mt-2 text-base text-gray-600">{{ instrument.name }}</p>
                    <a
                        :href="`https://www.nseindia.com/get-quotes/equity?symbol=${encodeURIComponent(instrument.symbol)}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-flex min-h-9 items-center gap-1.5 rounded text-sm font-medium text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                    >
                        View on NSE <ArrowTopRightOnSquareIcon class="size-4" aria-hidden="true" />
                        <span class="sr-only">(opens in a new window)</span>
                    </a>
                </div>
                <div class="rounded-xl border border-purple-100 bg-purple-50/60 px-5 py-4 sm:min-w-64 sm:text-right">
                    <p class="text-sm font-medium text-purple-800">Adjusted closing price</p>
                    <p class="mt-1 text-3xl font-semibold tracking-tight text-purple-950 tabular-nums sm:text-4xl">
                        {{ formatPrice(instrument.close_adjusted) }}
                    </p>
                    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600 sm:justify-end">
                        <span
                            class="rounded-md bg-white px-2 py-1 font-semibold tabular-nums"
                            :class="returnColor(instrument.absolute_return_one_months)"
                            >{{ formatPercent(instrument.absolute_return_one_months, true) }}</span
                        >
                        over 1 month
                    </p>
                </div>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                <div class="min-w-0 rounded-xl border border-sky-100 bg-sky-50/60 p-4 sm:p-5">
                    <dt class="flex items-center gap-2 text-xs font-medium text-sky-800 sm:text-sm">
                        <BuildingOffice2Icon class="size-4 shrink-0" aria-hidden="true" />
                        Market cap
                    </dt>
                    <dd class="mt-2">
                        <p class="text-lg font-semibold text-sky-950 tabular-nums sm:text-xl">{{ formatMarketCap(instrument.marketcap) }}</p>
                        <p class="mt-1 text-xs leading-5 text-gray-600">Market value of all shares</p>
                    </dd>
                </div>
                <div class="min-w-0 rounded-xl border border-sky-100 bg-sky-50/60 p-4 sm:p-5">
                    <dt class="flex items-center gap-2 text-xs font-medium text-sky-800 sm:text-sm">
                        <ScaleIcon class="size-4 shrink-0" aria-hidden="true" />
                        Price / earnings
                    </dt>
                    <dd class="mt-2">
                        <p class="text-lg font-semibold text-sky-950 tabular-nums sm:text-xl">{{ formatNumber(instrument.price_to_earnings) }}</p>
                        <p class="mt-1 text-xs leading-5 text-gray-600">Price relative to earnings</p>
                    </dd>
                </div>
                <div class="col-span-2 min-w-0 rounded-xl border border-teal-100 bg-teal-50/60 p-4 sm:col-span-1 sm:p-5">
                    <dt class="flex items-center gap-2 text-xs font-medium text-teal-800 sm:text-sm">
                        <ChartBarIcon class="size-4 shrink-0" aria-hidden="true" />
                        Daily traded value
                    </dt>
                    <dd class="mt-2">
                        <p class="text-lg font-semibold text-teal-950 tabular-nums sm:text-xl">
                            {{ formatPrice(instrument.median_volume_one_year) }} crore
                        </p>
                        <p class="mt-1 text-xs leading-5 text-gray-600">Median over the past year</p>
                    </dd>
                </div>
            </dl>
            <div v-if="instrument.indices.length" class="mt-5 flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs text-gray-500">Member of</span>
                <span v-for="index in instrument.indices" :key="index" class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600">{{
                    index
                }}</span>
            </div>
        </header>

        <nav aria-label="On this page" class="sticky top-26 z-20 overflow-x-auto border-b border-gray-200 bg-white sm:top-16">
            <ul class="flex min-w-max gap-6 sm:gap-8">
                <li v-for="section in sections" :key="section.id">
                    <a
                        :href="`#${section.id}`"
                        :aria-current="activeSection === section.id ? 'location' : undefined"
                        class="inline-flex min-h-12 items-center border-b-2 px-1 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-purple-600"
                        :class="
                            activeSection === section.id
                                ? 'border-purple-600 text-purple-700'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-900'
                        "
                        @click="activeSection = section.id"
                    >
                        {{ section.label }}
                    </a>
                </li>
            </ul>
        </nav>

        <div class="mt-8 flex min-w-0 flex-col gap-12 sm:gap-16">
            <section id="overview" class="instrument-section scroll-mt-44 sm:scroll-mt-36" aria-labelledby="overview-heading" tabindex="-1">
                <div class="mb-5">
                    <h2 id="overview-heading" class="text-xl font-semibold tracking-tight">At a glance</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">Start with performance, the price trend and distance from the high.</p>
                </div>
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-5">
                        <dt class="text-sm text-gray-500">1-year return</dt>
                        <dd class="mt-3 text-2xl font-semibold tabular-nums" :class="returnColor(instrument.absolute_return_one_year)">
                            {{ formatPercent(instrument.absolute_return_one_year, true) }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-gray-500">Change in adjusted price over 1 year.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-5">
                        <dt class="text-sm text-gray-500">200-day price trend</dt>
                        <dd class="mt-3 text-2xl font-semibold" :class="averageColor(instrument.ma_200)">
                            {{ averagePosition(instrument.ma_200) }}
                        </dd>
                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ formatPrice(instrument.ma_200) }} simple moving average.</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-5">
                        <dt class="text-sm text-gray-500">From the 1-year high</dt>
                        <dd class="mt-3 text-2xl font-semibold tabular-nums">{{ distanceFromHigh(instrument.away_from_high_one_year) }}</dd>
                        <p class="mt-2 text-xs leading-5 text-gray-500">1-year high: {{ formatPrice(instrument.high_one_year) }}.</p>
                    </div>
                </dl>
                <div class="mt-5 grid gap-5 rounded-xl bg-gray-50 p-5 sm:grid-cols-2 sm:gap-8 sm:p-6">
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <CheckCircleIcon class="size-5 text-emerald-600" aria-hidden="true" /> Supporting signals
                        </h3>
                        <ul v-if="supportingSignals.length" class="mt-3 space-y-2 text-sm leading-6 text-gray-600">
                            <li v-for="signal in supportingSignals" :key="signal" class="flex gap-2">
                                <span class="text-emerald-600" aria-hidden="true">·</span>{{ signal }}
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm leading-6 text-gray-500">No supporting signals from these checks.</p>
                    </div>
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-semibold">
                            <InformationCircleIcon class="size-5 text-amber-600" aria-hidden="true" /> Points to review
                        </h3>
                        <ul v-if="reviewSignals.length" class="mt-3 space-y-2 text-sm leading-6 text-gray-600">
                            <li v-for="signal in reviewSignals" :key="signal" class="flex gap-2">
                                <span class="text-amber-600" aria-hidden="true">·</span>{{ signal }}
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm leading-6 text-gray-500">
                            None flagged by these checks. See the risk measures below for more context.
                        </p>
                    </div>
                </div>
            </section>

            <section id="price" class="instrument-section min-w-0 scroll-mt-44 sm:scroll-mt-36" aria-labelledby="price-heading" tabindex="-1">
                <div class="mb-5">
                    <p class="mb-2 text-xs font-medium tracking-widest text-purple-700 uppercase">Price & trend</p>
                    <h2 id="price-heading" class="text-xl font-semibold tracking-tight">Price & history</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        Switch between price, returns and other measures. Keep the same period as you compare.
                    </p>
                </div>
                <div class="min-w-0 rounded-xl border border-gray-200 p-3 sm:p-5">
                    <Deferred data="priceHistory">
                        <template #fallback>
                            <div role="status" class="animate-pulse motion-reduce:animate-none">
                                <span class="sr-only">Loading price history</span>
                                <div class="h-11 w-2/3 rounded-lg bg-gray-100" />
                                <div class="mt-4 h-[300px] rounded-lg bg-gray-100 sm:h-[400px]" />
                            </div>
                        </template>
                        <InstrumentHistoryCharts
                            v-if="priceHistory?.length"
                            :key="instrument.symbol"
                            :history="priceHistory"
                            :is-etf="instrument.is_etf"
                        />
                        <p v-else class="py-20 text-center text-sm text-gray-500">No price history is available for {{ instrument.symbol }}.</p>
                    </Deferred>
                </div>
                <dl class="mt-4 grid gap-px overflow-hidden rounded-xl border border-gray-200 bg-gray-200 sm:grid-cols-2">
                    <div v-for="level in priceLevels" :key="level.label" class="flex flex-wrap items-center justify-between gap-3 bg-white p-5">
                        <div>
                            <dt class="text-xs text-gray-500">{{ level.label }}</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatPrice(level.value) }}</dd>
                        </div>
                        <p class="text-sm text-gray-500">{{ distanceFromHigh(level.distance) }}</p>
                    </div>
                </dl>
                <div class="mt-8">
                    <h3 class="text-base font-semibold">Price versus moving averages</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-500">Each label shows whether the closing price is above or below that average.</p>
                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                        <table class="w-full text-sm">
                            <caption class="sr-only">
                                Simple and exponential moving averages, in rupees
                            </caption>
                            <thead class="bg-gray-50 text-xs text-gray-500">
                                <tr>
                                    <th scope="col" class="px-3 py-3 text-left font-medium sm:px-5">Period</th>
                                    <th scope="col" class="px-3 py-3 text-right font-medium sm:px-5">Simple (SMA)</th>
                                    <th scope="col" class="px-3 py-3 text-right font-medium sm:px-5">Exponential (EMA)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="average in movingAverages" :key="average.days">
                                    <th scope="row" class="px-3 py-4 text-left font-medium sm:px-5">{{ average.days }} days</th>
                                    <td v-for="(value, index) in [average.sma, average.ema]" :key="index" class="px-3 py-3 text-right sm:px-5">
                                        <p class="font-medium tabular-nums">{{ formatPrice(value) }}</p>
                                        <p class="mt-1 text-xs" :class="averageColor(value)">{{ averagePosition(value) }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-gray-500">SMA gives each day equal weight. EMA gives more weight to recent prices.</p>
                </div>
            </section>

            <section id="returns" class="instrument-section scroll-mt-44 sm:scroll-mt-36" aria-labelledby="returns-heading" tabindex="-1">
                <div class="mb-5">
                    <p class="mb-2 text-xs font-medium tracking-widest text-purple-700 uppercase">Returns & momentum</p>
                    <h2 id="returns-heading" class="text-xl font-semibold tracking-tight">How do the periods compare?</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        Total change in adjusted price for each period ending {{ formatDate(instrument.date) }}.
                    </p>
                </div>
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div
                        v-for="period in returnPeriods"
                        :key="period.label"
                        class="rounded-xl border border-gray-200 p-4"
                        :class="period.label === '1 year' ? 'col-span-2 bg-gray-50 sm:col-span-1' : ''"
                    >
                        <dt class="text-xs text-gray-500">{{ period.label }}</dt>
                        <dd class="mt-3 text-xl font-semibold tabular-nums" :class="returnColor(period.value)">
                            {{ formatPercent(period.value, true) }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-6 rounded-xl border border-gray-200 p-5 sm:p-6">
                    <h3 class="text-base font-semibold">Momentum without the latest months</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-500">These measures exclude recent price moves from the 12-month period.</p>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2 sm:gap-8">
                        <div v-for="momentum in momentumReturns" :key="momentum.label" class="border-l-2 border-purple-200 pl-4">
                            <dt class="text-sm text-gray-600">{{ momentum.label }}</dt>
                            <dd class="mt-2 text-xl font-semibold tabular-nums" :class="returnColor(momentum.value)">
                                {{ formatPercent(momentum.value, true) }}
                            </dd>
                            <p class="mt-1 text-xs text-gray-500">{{ momentum.description }}</p>
                        </div>
                    </dl>
                </div>
            </section>

            <section id="risk" class="instrument-section min-w-0 scroll-mt-44 sm:scroll-mt-36" aria-labelledby="risk-heading" tabindex="-1">
                <div class="mb-5">
                    <p class="mb-2 text-xs font-medium tracking-widest text-purple-700 uppercase">Risk & consistency</p>
                    <h2 id="risk-heading" class="text-xl font-semibold tracking-tight">What is behind the returns?</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">Compare price movement, return per unit of risk and trading patterns.</p>
                </div>
                <div class="mb-5 flex flex-col gap-3 rounded-xl bg-gray-50 p-5 sm:flex-row sm:items-center sm:gap-6">
                    <p class="shrink-0 text-sm text-gray-600">
                        Beta <span class="ml-2 text-xl font-semibold text-gray-900 tabular-nums">{{ formatNumber(instrument.beta) }}</span>
                    </p>
                    <p class="text-sm leading-6 text-gray-500">
                        Measures sensitivity to market movements. A value of 1 indicates similar sensitivity to the market.
                    </p>
                </div>
                <p class="mb-2 text-xs text-gray-500 sm:hidden">Scroll the table sideways to compare all periods.</p>
                <div
                    role="region"
                    aria-label="Risk measures by period"
                    tabindex="0"
                    class="overflow-x-auto rounded-xl border border-gray-200 focus-visible:outline-2 focus-visible:outline-purple-600"
                >
                    <table class="w-full min-w-[640px] text-sm">
                        <caption class="sr-only">
                            Risk and consistency measures for one, three, six, nine and twelve months
                        </caption>
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th scope="col" class="sticky left-0 bg-gray-50 px-4 py-4 text-left font-medium">Measure</th>
                                <th v-for="period in returnPeriods" :key="period.label" scope="col" class="px-4 py-4 text-right font-medium">
                                    {{ period.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in riskRows" :key="row.label" class="group hover:bg-gray-50">
                                <th scope="row" class="sticky left-0 bg-white px-4 py-4 text-left font-medium group-hover:bg-gray-50">
                                    {{ row.label }}
                                </th>
                                <td
                                    v-for="(value, index) in row.values"
                                    :key="index"
                                    class="px-4 py-4 text-right tabular-nums"
                                    :class="row.signed ? returnColor(value) : 'text-gray-600'"
                                >
                                    {{ row.percent ? formatPercent(value) : formatNumber(value, row.label === 'Circuit days' ? 0 : 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">
                    <h3 class="text-sm font-semibold">How to read these measures</h3>
                    <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                        <div v-for="term in riskGlossary" :key="term.label">
                            <dt class="text-xs font-semibold text-gray-700">{{ term.label }}</dt>
                            <dd class="mt-1 text-xs leading-5 text-gray-500">{{ term.description }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section id="events" class="instrument-section min-w-0 scroll-mt-44 sm:scroll-mt-36" aria-labelledby="events-heading" tabindex="-1">
                <div class="mb-5">
                    <p class="mb-2 text-xs font-medium tracking-widest text-purple-700 uppercase">Company events</p>
                    <h2 id="events-heading" class="text-xl font-semibold tracking-tight">Dividends & corporate actions</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">Recorded events, with the latest first. Dividend amounts are per share.</p>
                </div>
                <Deferred :data="['dividends', 'corporateActions']">
                    <template #fallback>
                        <div role="status" class="animate-pulse space-y-3 motion-reduce:animate-none">
                            <span class="sr-only">Loading company events</span>
                            <div v-for="n in 4" :key="n" class="h-14 rounded-lg bg-gray-100" />
                        </div>
                    </template>
                    <TabGroup :key="instrument.symbol" as="div" :default-index="!dividends?.length && corporateActions?.length ? 1 : 0">
                        <TabList
                            aria-label="Company event types"
                            class="mb-5 grid grid-cols-[auto_minmax(0,1fr)] gap-2 border-b border-gray-200 sm:flex sm:gap-5"
                        >
                            <Tab v-for="tab in companyEventTabs" :key="tab.label" v-slot="{ selected }" as="template">
                                <button
                                    type="button"
                                    class="-mb-px flex min-h-12 min-w-0 cursor-pointer items-center justify-center gap-2 border-b-2 px-2 py-3 text-left text-xs font-medium focus-visible:rounded-t-md focus-visible:outline-2 focus-visible:outline-purple-600 sm:px-3 sm:text-sm"
                                    :class="
                                        selected
                                            ? 'border-purple-600 text-purple-700'
                                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                    "
                                >
                                    <span>{{ tab.label }}</span>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-xs tabular-nums"
                                        :class="selected ? 'bg-purple-50 text-purple-700' : 'bg-gray-100 text-gray-500'"
                                        >{{ tab.count }}</span
                                    >
                                </button>
                            </Tab>
                        </TabList>
                        <TabPanels>
                            <TabPanel class="rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-600">
                                <div v-if="dividendGroups.length" class="overflow-hidden rounded-xl border border-gray-200">
                                    <table class="w-full table-fixed text-sm">
                                        <caption class="sr-only">
                                            Dividend history grouped by year
                                        </caption>
                                        <thead class="bg-gray-50 text-xs text-gray-500">
                                            <tr>
                                                <th scope="col" class="w-24 px-3 py-3 text-left font-medium sm:w-36 sm:px-5">Date</th>
                                                <th scope="col" class="px-3 py-3 text-left font-medium sm:px-5">Description</th>
                                                <th scope="col" class="w-24 px-3 py-3 text-right font-medium sm:w-32 sm:px-5">₹ / share</th>
                                            </tr>
                                        </thead>
                                        <tbody v-for="group in dividendGroups" :key="group.year" class="divide-y divide-gray-100">
                                            <tr>
                                                <th
                                                    colspan="3"
                                                    scope="rowgroup"
                                                    class="border-y border-gray-100 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-600 sm:px-5"
                                                >
                                                    {{ group.year
                                                    }}<span class="ml-2 font-normal text-gray-500"
                                                        >· {{ group.actions.length }} {{ group.actions.length === 1 ? 'event' : 'events' }}</span
                                                    >
                                                </th>
                                            </tr>
                                            <tr v-for="(action, index) in group.actions" :key="`${action.date}-${index}`" class="align-top">
                                                <td class="px-3 py-4 text-xs leading-6 text-gray-500 sm:px-5 sm:text-sm">
                                                    <time :datetime="action.date">{{ formatDate(action.date) }}</time>
                                                </td>
                                                <td class="px-3 py-4 text-xs leading-6 break-words text-gray-600 sm:px-5 sm:text-sm">
                                                    {{ action.description }}
                                                </td>
                                                <td class="px-3 py-4 text-right text-sm leading-6 font-medium tabular-nums sm:px-5">
                                                    {{ formatNumber(action.dividend) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="rounded-xl border border-dashed border-gray-200 p-6 text-sm text-gray-500">
                                    No dividends are recorded for {{ instrument.symbol }}.
                                </p>
                            </TabPanel>
                            <TabPanel class="rounded-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-purple-600">
                                <div v-if="corporateActions?.length" class="overflow-hidden rounded-xl border border-gray-200">
                                    <table class="w-full table-fixed text-sm">
                                        <caption class="sr-only">
                                            Rights, bonus and split history
                                        </caption>
                                        <thead class="bg-gray-50 text-xs text-gray-500">
                                            <tr>
                                                <th scope="col" class="w-28 px-4 py-3 text-left font-medium sm:w-36 sm:px-5">Date</th>
                                                <th scope="col" class="px-4 py-3 text-left font-medium sm:px-5">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(action, index) in corporateActions" :key="`${action.date}-${index}`" class="align-top">
                                                <td class="px-4 py-4 text-xs leading-6 text-gray-500 sm:px-5 sm:text-sm">
                                                    <time :datetime="action.date">{{ formatDate(action.date) }}</time>
                                                </td>
                                                <td class="px-4 py-4 text-sm leading-6 break-words text-gray-600 sm:px-5">
                                                    {{ action.description }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="rounded-xl border border-dashed border-gray-200 p-6 text-sm text-gray-500">
                                    No rights, bonus or split events are recorded for {{ instrument.symbol }}.
                                </p>
                            </TabPanel>
                        </TabPanels>
                    </TabGroup>
                </Deferred>
            </section>
            <div class="flex items-center justify-between gap-4 border-t border-gray-200 pt-5 text-xs text-gray-500">
                <p>— means data is not available.</p>
                <a
                    href="#instrument-top"
                    class="inline-flex min-h-11 items-center gap-2 rounded font-medium text-purple-700 hover:underline focus-visible:outline-2 focus-visible:outline-purple-600"
                    >Back to top <ArrowUpIcon class="size-3.5" aria-hidden="true"
                /></a>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import InstrumentHistoryCharts from '@/Components/Charts/InstrumentHistoryCharts.vue';
import type { BacktestNseInstrumentViewResource } from '@/types/app/Resources/BacktestNseInstrumentViewResource';
import { formatDate } from '@/utils';
import type { InstrumentHistoryRecord } from '@/types/InstrumentHistoryRecord';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue';
import {
    ArrowTopRightOnSquareIcon,
    ArrowUpIcon,
    BuildingOffice2Icon,
    ChartBarIcon,
    CheckCircleIcon,
    ClockIcon,
    InformationCircleIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';
import { Deferred, Head } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface CorporateAction {
    date: string;
    description: string;
}

interface DividendAction extends CorporateAction {
    dividend: string | null;
}

type MetricValue = string | number | null | undefined;

const props = defineProps<{
    instrument: BacktestNseInstrumentViewResource;
    priceHistory?: InstrumentHistoryRecord[];
    dividends?: DividendAction[];
    corporateActions?: CorporateAction[];
    pros: string[];
    cons: string[];
}>();

const companyEventTabs = computed(() => [
    { label: 'Dividends', count: props.dividends?.length ?? 0 },
    { label: 'Rights, bonus & splits', count: props.corporateActions?.length ?? 0 },
]);

const sections = [
    { id: 'overview', label: 'At a glance' },
    { id: 'price', label: 'Price & trend' },
    { id: 'returns', label: 'Returns' },
    { id: 'risk', label: 'Risk & consistency' },
    { id: 'events', label: 'Company events' },
];
const activeSection = ref('overview');
let sectionObserver: IntersectionObserver | undefined;

onMounted(() => {
    sectionObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            }
        },
        { rootMargin: '-176px 0px -60% 0px', threshold: 0 },
    );

    for (const section of sections) {
        const element = document.getElementById(section.id);
        if (element) {
            sectionObserver.observe(element);
        }
    }
});
onUnmounted(() => sectionObserver?.disconnect());

function numericValue(value: MetricValue): number | null {
    if (value === null || value === undefined) {
        return null;
    }
    const normalized = String(value).replaceAll(',', '').replace(/%+$/, '').trim();
    if (normalized === '' || normalized === '-' || normalized === '—') {
        return null;
    }
    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

function formatNumber(value: MetricValue, decimals = 2): string {
    const number = numericValue(value);
    return number === null ? '—' : number.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function formatPrice(value: MetricValue): string {
    return numericValue(value) === null ? '—' : `₹${formatNumber(value)}`;
}

function formatMarketCap(value: MetricValue): string {
    return numericValue(value) === null ? '—' : `₹${formatNumber(value, 0)} crore`;
}

function formatPercent(value: MetricValue, signed = false): string {
    const number = numericValue(value);
    if (number === null) {
        return '—';
    }
    return `${signed && number > 0 ? '+' : ''}${formatNumber(number)}%`;
}

function returnColor(value: MetricValue): string {
    const number = numericValue(value);
    if (number === null || number === 0) {
        return 'text-gray-600';
    }
    return number > 0 ? 'text-emerald-700' : 'text-rose-700';
}

function averagePosition(value: MetricValue): string {
    const average = numericValue(value);
    const close = numericValue(props.instrument.close_adjusted);
    if (average === null || average <= 0 || close === null) {
        return 'Unavailable';
    }
    return close > average ? 'Above' : close < average ? 'Below' : 'At average';
}

function averageColor(value: MetricValue): string {
    const position = averagePosition(value);
    return position === 'Above' ? 'text-emerald-700' : position === 'Below' ? 'text-rose-700' : 'text-gray-600';
}

function distanceFromHigh(value: MetricValue): string {
    const number = numericValue(value);
    if (number === null) {
        return '—';
    }
    if (number === 0) {
        return 'At the high';
    }
    return `${formatPercent(Math.abs(number))} ${number < 0 ? 'below' : 'above'}`;
}

function groupMovingAverageSignals(signals: string[]): string[] {
    const periods: string[] = [];
    const otherSignals: string[] = [];
    let direction = '';
    for (const signal of signals) {
        const match = signal.match(/^The close is (above|below) (\d+)-day moving average\.$/);
        if (match) {
            direction = match[1];
            periods.push(match[2]);
        } else {
            otherSignals.push(signal);
        }
    }
    if (periods.length) {
        periods.sort((first, second) => Number(first) - Number(second));
        const last = periods.pop();
        const label = periods.length ? `${periods.join(', ')} and ${last}` : last;
        otherSignals.unshift(`Price is ${direction} the ${label}-day moving ${periods.length ? 'averages' : 'average'}.`);
    }
    return otherSignals;
}

const supportingSignals = computed(() => groupMovingAverageSignals(props.pros));
const reviewSignals = computed(() => groupMovingAverageSignals(props.cons));
const priceLevels = computed(() => [
    { label: '1-year high', value: props.instrument.high_one_year, distance: props.instrument.away_from_high_one_year },
    { label: 'All-time high', value: props.instrument.high_all_time, distance: props.instrument.away_from_high_all_time },
]);
const movingAverages = computed(() => [
    { days: 20, sma: props.instrument.ma_20, ema: props.instrument.ema_20 },
    { days: 50, sma: props.instrument.ma_50, ema: props.instrument.ema_50 },
    { days: 100, sma: props.instrument.ma_100, ema: props.instrument.ema_100 },
    { days: 200, sma: props.instrument.ma_200, ema: props.instrument.ema_200 },
]);
const returnPeriods = computed(() => [
    { label: '1 month', value: props.instrument.absolute_return_one_months },
    { label: '3 months', value: props.instrument.absolute_return_three_months },
    { label: '6 months', value: props.instrument.absolute_return_six_months },
    { label: '9 months', value: props.instrument.absolute_return_nine_months },
    { label: '1 year', value: props.instrument.absolute_return_one_year },
]);
const momentumReturns = computed(() => [
    { label: 'Exclude the latest month', description: '12M − 1M return', value: props.instrument.return_twelve_minus_one_months },
    { label: 'Exclude the latest 2 months', description: '12M − 2M return', value: props.instrument.return_twelve_minus_two_months },
]);
const riskRows = computed(() => {
    const instrument = props.instrument;
    return [
        {
            label: 'Volatility',
            percent: true,
            signed: false,
            values: [
                instrument.volatility_one_months,
                instrument.volatility_three_months,
                instrument.volatility_six_months,
                instrument.volatility_nine_months,
                instrument.volatility_one_year,
            ],
        },
        {
            label: 'Sharpe returns',
            percent: false,
            signed: true,
            values: [
                instrument.sharpe_return_one_months,
                instrument.sharpe_return_three_months,
                instrument.sharpe_return_six_months,
                instrument.sharpe_return_nine_months,
                instrument.sharpe_return_one_year,
            ],
        },
        {
            label: 'RSI',
            percent: false,
            signed: false,
            values: [
                instrument.rsi_one_months,
                instrument.rsi_three_months,
                instrument.rsi_six_months,
                instrument.rsi_nine_months,
                instrument.rsi_one_year,
            ],
        },
        {
            label: 'Positive days',
            percent: true,
            signed: false,
            values: [
                instrument.positive_days_percent_one_months,
                instrument.positive_days_percent_three_months,
                instrument.positive_days_percent_six_months,
                instrument.positive_days_percent_nine_months,
                instrument.positive_days_percent_one_year,
            ],
        },
        {
            label: 'Circuit days',
            percent: false,
            signed: false,
            values: [
                instrument.circuits_one_months,
                instrument.circuits_three_months,
                instrument.circuits_six_months,
                instrument.circuits_nine_months,
                instrument.circuits_one_year,
            ],
        },
    ];
});
const riskGlossary = [
    { label: 'Volatility', description: 'How much daily returns vary. Higher values indicate larger price swings.' },
    { label: 'Sharpe returns', description: 'Period return divided by volatility. This ratio has no percentage unit.' },
    { label: 'RSI · Relative Strength Index', description: 'A momentum measure from 0 to 100 that compares recent gains and losses.' },
    { label: 'Positive days', description: 'The percentage of trading days when the price increased.' },
    { label: 'Circuit days', description: 'The number of days when the price closed at a circuit limit.' },
];
const dividendGroups = computed(() => {
    const groups = new Map<string, DividendAction[]>();
    const actions = [...(props.dividends ?? [])].sort((first, second) => second.date.localeCompare(first.date));
    for (const action of actions) {
        const year = action.date.slice(0, 4);
        const group = groups.get(year) ?? [];
        group.push(action);
        groups.set(year, group);
    }
    return [...groups].map(([year, actions]) => ({ year, actions }));
});
</script>
