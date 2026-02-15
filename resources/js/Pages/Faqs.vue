<template>
    <Head title="FAQs" />
    <div class="mx-auto max-w-3xl py-12">
        <div class="mb-10">
            <h2 class="text-3xl font-bold tracking-tight text-gray-900">Frequently asked questions</h2>
            <p class="mt-2 text-sm text-gray-500">Everything you need to know about momoindiascreener.</p>
        </div>

        <dl class="space-y-4">
            <Disclosure v-for="faq in faqs" :key="faq.question" v-slot="{ open }" as="div">
                <div
                    class="overflow-hidden rounded-lg border transition-colors duration-200"
                    :class="open ? 'border-purple-200 bg-purple-50/50' : 'border-gray-200 bg-white'"
                >
                    <dt>
                        <DisclosureButton
                            class="flex w-full items-center justify-between gap-x-4 px-5 py-4 text-left"
                        >
                            <span
                                class="text-sm font-semibold transition-colors duration-200"
                                :class="open ? 'text-purple-700' : 'text-gray-900'"
                            >
                                {{ faq.question }}
                            </span>
                            <ChevronDownIcon
                                class="h-5 w-5 shrink-0 transition-transform duration-200"
                                :class="open ? 'rotate-180 text-purple-500' : 'text-gray-400'"
                                aria-hidden="true"
                            />
                        </DisclosureButton>
                    </dt>
                    <DisclosurePanel as="dd" class="px-5 pb-4">
                        <p class="text-sm leading-relaxed text-gray-600">{{ faq.answer }}</p>
                    </DisclosurePanel>
                </div>
            </Disclosure>
        </dl>
    </div>
</template>

<script setup lang="ts">
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { ChevronDownIcon } from '@heroicons/vue/20/solid';
import { Head } from '@inertiajs/vue3';

const faqs = [
    {
        question: 'What is momoindiascreener?',
        answer: 'momoindiascreener is a website that helps you rank stocks by factors like momentum, low-volatility, low-beta, etc. The website is built and maintained by Viraj Khatavkar.',
    },
    {
        question: 'Is there a free trial?',
        answer: 'No! I observed that people were abusing the website by signing up for free trial with random temporary email addresses. You can try out the monthly plan if you want to test the website. A small monthly fee allows me to dedicate as much energy as possible into providing the best service possible.',
    },
    {
        question: 'How often is the data updated?',
        answer: 'The data is updated every day at EOD. However, there can be delay of 2 to 3 days in updating data in certain situations. Daily updates is not a guarantee!',
    },
    {
        question: 'What is your data source?',
        answer: 'The data is sourced though daily bhavcopy from NSE. The data is then processed and stored in our database.',
    },
    {
        question: 'What does forever plan actually mean?',
        answer: 'It means your purchase will grant you unlimited access to screening related features of momoindiascreener for the lifetime of the website. Your access is valid until the website is operational.',
    },
    {
        question: 'Is it a smart idea to sell lifetime subscriptions?',
        answer: 'Probably not! But I am doing it anyway. I am betting that the website will be around for a long time. I am betting that the website will continue to grow and improve. I am betting that you will love the website and tell your friends. I am betting that you will be happy to support the website by purchasing a lifetime subscription.',
    },
    {
        question: 'What is the refund policy?',
        answer: 'There are no refunds.',
    },
    {
        question: 'Is this website only for momentum investors?',
        answer: "The site is surely fine-tuned for momentum investors but can be used to screen for any price-based factors like low-volatility, low-beta, etc. If you want to screen for value or quality stocks then momoindiascreener is not a correct website. You should probably look at screener.in. They are doing good work in that area!",
    },
    {
        question: 'I love momoindiascreener! How can I help?',
        answer: 'Thanks! momoindiascreener is a different kind of site. Most companies spend thousands on marketing to tell the world about their products. momoindiascreener spends zero; I rely entirely on word of mouth. If you enjoy the site, please tell your friends. Send an email, or tweet out a link to the site. I promise it helps!',
    },
    {
        question: 'I have a question that is not answered here. How can I contact you?',
        answer: 'Please send an email to viraj@momoindiascreener.in',
    },
    {
        question: 'Why do you never see elephants hiding in trees?',
        answer: 'Because they are so good at it.',
    },
];
</script>
