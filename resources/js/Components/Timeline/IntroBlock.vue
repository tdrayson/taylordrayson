<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import InlineBadge from '../Ui/InlineBadge.vue';
import Avatar from '../Profile/Avatar.vue';

defineProps({
    streakDays: { type: [Number, String], default: '2,145' },
    agencyUrl: { type: String, default: 'https://thecreativetinker.com' },
    pluginUrl: { type: String, default: 'https://wpextended.io' },
    podcastEpisodes: { type: Number, default: 0 },
});

const badgeLinkClass =
    'group box-decoration-clone rounded-md bg-neutral-25 px-1.5 py-0.5 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 hover:decoration-accent-500 focus-visible:text-accent-500 focus-visible:decoration-accent-500';

const textLinkClass =
    'font-medium text-neutral-900 underline decoration-neutral-300 underline-offset-2 transition-colors hover:text-accent-500 hover:decoration-accent-500 focus-visible:text-accent-500 focus-visible:decoration-accent-500';

const externalIconClass =
    'ml-0.5 inline-block size-3.5 align-text-top text-neutral-400 transition-transform duration-150 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-focus-visible:-translate-y-0.5 group-focus-visible:translate-x-0.5 motion-reduce:transition-none';
</script>

<template>
    <div class="max-w-2xl text-xl leading-relaxed tracking-tight text-neutral-700">
        <p>
            Hi. I'm
            <span class="whitespace-nowrap">
                Taylor<Avatar size="size-7" alt="" class="ml-1.5 inline-block rounded-sm align-[-0.25em]" />
            </span>,
            a web developer in London who logs far more of his life than is strictly necessary. I've
            <Link href="/food" :class="textLinkClass">logged every calorie</Link>
            for <span class="tnum">{{ streakDays }}</span> days straight, which is either
            impressive or a cry for help depending on who's asking.
        </p>
        <p class="mt-6">
            I started
            <a
                :href="agencyUrl"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="The Creative Tinker, opens in a new tab"
                :class="badgeLinkClass"
            >
                <span class="whitespace-nowrap">
                    <InlineBadge src="/logos/the-creative-tinker.png" alt="The Creative Tinker" class="ml-1 mr-2" />
                    The
                </span>
                Creative
                <span class="whitespace-nowrap">
                    Tinker
                    <Icon name="ArrowUpRight01Icon" :class="externalIconClass" />
                </span>
            </a>
            at 19. My side quests include developing a
            <a
                :href="pluginUrl"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="WordPress plugin, opens in a new tab"
                :class="badgeLinkClass"
            >
                <span class="whitespace-nowrap">
                    <InlineBadge src="/logos/wp-extended.png" alt="WP Extended" class="ml-1 mr-2" />
                    WordPress
                </span>
                <span class="whitespace-nowrap">
                    plugin
                    <Icon name="ArrowUpRight01Icon" :class="externalIconClass" />
                </span>
            </a>,
            and co-hosting a
            <Link href="/this-week-with" :class="badgeLinkClass">
                <span class="whitespace-nowrap">
                    <InlineBadge src="/logos/this-week-with.jpg" alt="This Week With" class="ml-1 mr-2" />
                    weekly
                </span>
                podcast
            </Link>
            with my dad that's somehow at <span class="tnum">{{ podcastEpisodes }}</span> episodes.
            Elsewhere on here you'll find
            <Link href="/flights" :class="textLinkClass">flights</Link>,
            <Link href="/activities" :class="textLinkClass">activities</Link>,
            <Link href="/sleep" :class="textLinkClass">sleep</Link>, and
            <Link href="/more" :class="textLinkClass">whatever else I'm measuring</Link>.
            When I'm offline, I'm either
            <Link href="/activities/padel" :class="textLinkClass">hitting a ball with a racket</Link>
            or making another coffee. Guess which wins more often.
        </p>
    </div>
</template>
