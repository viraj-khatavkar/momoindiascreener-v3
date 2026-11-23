<template>
    <div class="overflow-hidden bg-white">
        <Head title="About Viraj Khatavkar - momoindiascreener.in" />
        <div class="relative mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="absolute bottom-0 left-3/4 top-0 hidden w-screen bg-gray-50 lg:block"></div>
            <div class="mt-8 lg:grid lg:grid-cols-2 lg:gap-8">
                <div class="relative lg:col-start-2 lg:row-start-1">
                    <svg
                        class="absolute right-0 top-0 -mr-20 -mt-20 hidden lg:block"
                        width="404"
                        height="384"
                        fill="none"
                        viewBox="0 0 404 384"
                        aria-hidden="true"
                    >
                        <defs>
                            <pattern
                                id="de316486-4a29-4312-bdfc-fbce2132a2c1"
                                x="0"
                                y="0"
                                width="20"
                                height="20"
                                patternUnits="userSpaceOnUse"
                            >
                                <rect
                                    x="0"
                                    y="0"
                                    width="4"
                                    height="4"
                                    class="text-gray-200"
                                    fill="currentColor"
                                />
                            </pattern>
                        </defs>
                        <rect
                            width="404"
                            height="384"
                            fill="url(#de316486-4a29-4312-bdfc-fbce2132a2c1)"
                        />
                    </svg>
                    <div class="relative mx-auto max-w-prose text-base lg:max-w-none">
                        <figure>
                            <div class="aspect-h-7 aspect-w-12 lg:aspect-none">
                                <img
                                    class="rounded-lg object-cover object-center shadow-lg"
                                    src="/images/about.jpg"
                                    width="1184"
                                    height="1376"
                                />
                            </div>
                            <figcaption class="mt-3 flex text-sm text-gray-500">
                                <svg
                                    class="h-5 w-5 flex-none text-gray-400"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M1 8a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 018.07 3h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0016.07 6H17a2 2 0 012 2v7a2 2 0 01-2 2H3a2 2 0 01-2-2V8zm13.5 3a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM10 14a3 3 0 100-6 3 3 0 000 6z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <span class="ml-2">Viraj Khatavkar</span>
                            </figcaption>
                        </figure>
                    </div>
                </div>
                <div class="mt-8 lg:mt-0">
                    <div class="mx-auto max-w-prose text-base lg:max-w-none">
                        <p class="text-lg text-gray-500">
                            This is Viraj Khatavkar, a web developer by profession and a trader by necessity!
                        </p>
                    </div>
                    <div
                        class="prose prose-indigo mx-auto mt-5 text-gray-500 lg:col-start-1 lg:row-start-1 lg:max-w-none"
                    >
                        <p>
                            I entered the stock market at the tail end of the 2017 bull run. Like many, I started
                            with value and quality stocks, inspired by Warren Buffett's investment philosophy and
                            the classic buy-and-hold strategy.
                        </p>
                        <p>
                            Over time, I discovered momentum investing and other market strategies, which opened my
                            eyes to new possibilities. I realized that buy-and-hold isn't the only path to success.
                        </p>
                        <p>
                            I basically want to make a ton of money in life, without guilt! Trading or investing
                            didn't matter as long as I was making money.
                        </p>
                        <p>
                            Currently, I trade options, futures, and stocks. Momentum is my trading slash investing
                            exposure to the markets. Futures and Options is a way to leverage my capital in a
                            risk-defined way.
                        </p>
                        <p>
                            Tomorrow I might trade something else. I am not married to any strategy or asset class.
                            I am married to making money.
                        </p>
                        <p>
                            Here are a few
                            <button
                                type="button"
                                class="relative cursor-pointer rounded-sm underline decoration-purple-300 decoration-dotted underline-offset-4 focus-visible:outline-2 focus-visible:outline-purple-600"
                                :aria-pressed="written"
                                @click="written = !written"
                            >
                                <span class="prefix" :class="{ corrected: written }" :aria-hidden="written || undefined">un</span>written<PencilIcon
                                    v-if="written"
                                    aria-hidden="true"
                                    class="correction-pencil pointer-events-none absolute bottom-1/2 left-0 size-4 text-purple-700"
                                />
                            </button>
                            trading rules that I follow:
                        </p>
                        <ul role="list">
                            <li>Don't marry a stock or a strategy.</li>
                            <li>Don't be afraid to change your mind.</li>
                            <li>Always be open to new ideas.</li>
                            <li>Discard strategies ruthlessly if they don't work.</li>
                            <li>Keep on finding new avenues to make money outside of markets.</li>
                            <li>Always meet new people with a curious mind.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { PencilIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const written = ref(false);
</script>

<style scoped>
.prefix {
    position: relative;
}
.corrected::after {
    content: '';
    position: absolute;
    inset: 55% -1px auto;
    border-top: 2px solid #7515d9;
    transform: rotate(-7deg);
    transform-origin: left;
}
.correction-pencil {
    opacity: 0;
}
@media (prefers-reduced-motion: no-preference) {
    .correction-pencil {
        animation: pencil 700ms both;
    }
    .corrected::after {
        animation: strike 240ms 170ms ease-out backwards;
    }
}
@keyframes pencil {
    0% {
        opacity: 0;
        transform: translate(-1.6em, -0.4em);
    }
    25% {
        opacity: 1;
        transform: translateX(-0.15em);
    }
    60% {
        opacity: 1;
        transform: translateX(1em);
    }
    100% {
        opacity: 0;
        transform: translate(1.8em, -0.6em);
    }
}
@keyframes strike {
    from {
        transform: rotate(-7deg) scaleX(0);
    }
}
</style>
