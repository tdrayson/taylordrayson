<script setup>
import { usePage } from '@inertiajs/vue3';
import TinkerLayout from '../../Layouts/TinkerLayout.vue';
import Avatar from '../../Components/Profile/Avatar.vue';
import BezierMark from '../../Components/LinkPage/BezierMark.vue';
import ContactActions from '../../Components/LinkPage/ContactActions.vue';
import LinkRow from '../../Components/LinkPage/LinkRow.vue';
import SectionHeading from '../../Components/LinkPage/SectionHeading.vue';
import TinkerMark from '../../Components/LinkPage/TinkerMark.vue';
import ThemeToggle from '../../Components/LinkPage/ThemeToggle.vue';
import TinkerBanner from '../../Components/LinkPage/TinkerBanner.vue';
import { SOCIALS } from '../../Components/LinkPage/shared.js';

defineProps({
    card: { type: Object, required: true },
});

const page = usePage();
</script>

<template>
    <TinkerLayout :title="card.organisation">
        <TinkerBanner>
            <ThemeToggle class="absolute right-4 top-4 size-11 rounded-full bg-white/20 text-white hover:bg-white/30" />
        </TinkerBanner>

        <div class="mx-auto flex max-w-sm flex-col px-6 pb-12">
            <Avatar :src="card.avatar" :alt="card.name" size="size-28" class="relative -mt-14 border-4 border-neutral-25 bg-tinker-100 shadow-lg dark:border-neutral-0" />

            <h1 class="mt-5 font-open-sans text-3xl font-bold tracking-normal text-neutral-900">{{ card.name }}</h1>
            <p class="font-bebas text-2xl uppercase leading-tight tracking-wide text-neutral-500">{{ card.organisation }}</p>
            <p class="mt-3 text-base/relaxed text-neutral-600">{{ card.bio }}</p>

            <ContactActions :card="card" variant="business" class="mt-7" />

            <section v-for="section in card.sections" :key="section.heading" class="mt-9 flex flex-col gap-3">
                <SectionHeading variant="business">{{ section.heading }}</SectionHeading>
                <LinkRow
                    v-for="link in section.links"
                    :key="link.href"
                    :href="link.href"
                    :label="link.label"
                    :description="link.description"
                    :logo="link.logo"
                    variant="business"
                >
                    <template v-if="link.icon === 'tinker'" #media>
                        <TinkerMark />
                    </template>
                </LinkRow>
            </section>

            <section v-if="card.socials.length || card.detailsHref" class="mt-9 flex flex-col gap-3">
                <SectionHeading variant="business">{{ card.socialHeading }}</SectionHeading>
                <LinkRow
                    v-for="social in card.socials"
                    :key="social.icon"
                    :href="social.href"
                    :label="social.label"
                    :icon="SOCIALS[social.icon].icon"
                    :icon-class="SOCIALS[social.icon].class"
                    variant="business"
                />
                <LinkRow
                    v-if="card.detailsHref"
                    :href="card.detailsHref"
                    label="Send me your details"
                    description="So I can save your number too"
                    icon="ArrowDataTransferHorizontalIcon"
                    variant="business"
                    internal
                />
            </section>

            <footer class="mt-10 flex flex-col items-center gap-3 text-center">
                <BezierMark class="text-tinker-500" />
                <p class="text-sm text-neutral-500">
                    <strong class="font-semibold text-neutral-600">{{ card.coffees.toLocaleString('en-GB') }}</strong> coffees this year
                </p>
                <a :href="page.props.appUrl" class="text-lg font-semibold text-neutral-600 transition-colors hover:text-tinker-600">
                    Me outside of work: taylordrayson.com
                </a>
            </footer>
        </div>
    </TinkerLayout>
</template>
