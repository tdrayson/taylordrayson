<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import LinkPageLayout from '../../Layouts/LinkPageLayout.vue';
import ActionTile from '../../Components/LinkPage/ActionTile.vue';
import ActivityHeader from '../../Components/LinkPage/ActivityHeader.vue';
import ContactActions from '../../Components/LinkPage/ContactActions.vue';
import LevelSquares from '../../Components/LinkPage/LevelSquares.vue';
import LinkRow from '../../Components/LinkPage/LinkRow.vue';
import SectionHeading from '../../Components/LinkPage/SectionHeading.vue';
import SiteMark from '../../Components/LinkPage/SiteMark.vue';
import TinkerMark from '../../Components/LinkPage/TinkerMark.vue';
import { SOCIALS } from '../../Components/LinkPage/shared.js';

defineProps({
    card: { type: Object, required: true },
});

const page = usePage();

// The shared food-logging streak, the same figure the site's sidebar shows.
const daysLogged = computed(() => page.props.streakDays ?? 0);

const format = (count) => count.toLocaleString('en-GB');
</script>

<template>
    <LinkPageLayout>
        <div class="mx-auto flex max-w-md flex-col px-7 py-10 sm:py-16">
            <ActivityHeader :levels="card.heatmap" :avatar="card.avatar" :name="card.name" />

            <h1 class="mt-12 w-min font-display text-5xl font-extrabold leading-none tracking-tight text-neutral-900">{{ card.name }}</h1>
            <p class="mt-4 text-base/relaxed text-neutral-500">{{ card.bio }}</p>

            <ContactActions :card="card" class="mt-8" />

            <section v-for="section in card.sections" :key="section.heading" class="mt-8 flex flex-col gap-3">
                <SectionHeading>{{ section.heading }}</SectionHeading>
                <LinkRow
                    v-for="link in section.links"
                    :key="link.href"
                    :href="link.href"
                    :label="link.label"
                    :description="link.description"
                    :logo="link.logo"
                    :icon="link.icon"
                >
                    <template v-if="link.icon === 'site'" #media>
                        <SiteMark />
                    </template>
                    <template v-else-if="link.icon === 'tinker'" #media>
                        <TinkerMark />
                    </template>
                </LinkRow>
            </section>

            <section v-if="card.socials.length || card.detailsHref" class="mt-8 flex flex-col gap-3">
                <SectionHeading>{{ card.socialHeading }}</SectionHeading>
                <div v-if="card.socials.length" class="grid grid-cols-4 gap-2.5">
                    <ActionTile
                        v-for="social in card.socials"
                        :key="social.icon"
                        :href="social.href"
                        :label="social.label"
                        :icon="SOCIALS[social.icon].icon"
                        :icon-class="SOCIALS[social.icon].class"
                        external
                    />
                </div>
                <LinkRow
                    v-if="card.detailsHref"
                    :href="card.detailsHref"
                    label="Send me your details"
                    description="So I can save your number too"
                    icon="ArrowDataTransferHorizontalIcon"
                    internal
                />
            </section>

            <footer class="mt-12 flex flex-col items-center gap-3 text-center">
                <LevelSquares />
                <p class="text-sm text-neutral-500">
                    <strong class="font-semibold text-neutral-600">{{ format(daysLogged) }}</strong> days logged,
                    <strong class="font-semibold text-neutral-600">{{ format(card.coffees) }}</strong> coffees this year
                </p>
                <a :href="page.props.appUrl" class="font-display text-lg font-semibold text-neutral-500 transition-colors hover:text-accent-500">
                    More about me at taylordrayson.com
                </a>
            </footer>
        </div>
    </LinkPageLayout>
</template>
