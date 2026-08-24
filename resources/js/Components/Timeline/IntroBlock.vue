<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import InlineBadge from '../Ui/InlineBadge.vue';
import Avatar from '../Profile/Avatar.vue';

const props = defineProps({
    // Null means "use the shared count", which is the real one.
    streakDays: { type: [Number, String], default: null },
    agencyUrl: { type: String, default: 'https://thecreativetinker.com' },
    pluginUrl: { type: String, default: 'https://wpextended.io' },
    podcastEpisodes: { type: Number, default: 0 },
});

const page = usePage();

// The live food-logging streak, shared on every page by the middleware.
const streak = computed(() => {
    const days = props.streakDays ?? page.props.streakDays ?? 0;

    return typeof days === 'number' ? days.toLocaleString() : days;
});

const badgeLinkClass =
    'group box-decoration-clone rounded-md bg-neutral-25 px-1.5 py-0.5 no-underline transition-colors hover:text-accent-500 focus-visible:text-accent-500';

const textLinkClass =
    'font-medium text-neutral-900 underline decoration-neutral-300 underline-offset-2 transition-colors hover:text-accent-500 hover:decoration-accent-500 focus-visible:text-accent-500 focus-visible:decoration-accent-500';

const externalIconWrapClass =
    'ml-0.5 inline-block text-neutral-400 transition-colors duration-150 ease-out group-hover:text-accent-500 group-focus-visible:text-accent-500';

const externalIconClass =
    'inline-block size-3.5 align-text-top transition-transform duration-150 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-focus-visible:-translate-y-0.5 group-focus-visible:translate-x-0.5 motion-reduce:transition-none';
</script>

<template>
    <div class="max-w-2xl text-xl leading-relaxed text-neutral-700 sm:tracking-tight">
        <p>
            Hi. I'm
            <span class="whitespace-nowrap">
                Taylor<Avatar size="size-7" alt="" class="ml-1.5 inline-block rounded-sm align-[-0.25em]" />
            </span>,
            a web developer in London. I log far more of my life than is strictly necessary. I've
            <Link href="/food" :class="textLinkClass">logged every calorie</Link>
            for <span class="tnum">{{ streak }}</span> days straight, which is either
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
                    <span :class="externalIconWrapClass">
                        <Icon name="ArrowUpRight01Icon" :class="externalIconClass" />
                    </span>
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
                </span>{{ ' ' }}<span class="whitespace-nowrap">
                    plugin
                    <span :class="externalIconWrapClass">
                        <Icon name="ArrowUpRight01Icon" :class="externalIconClass" />
                    </span>
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
            <Link href="/activities" :class="textLinkClass">activities</Link>,
            <Link href="/sleep" :class="textLinkClass">sleep</Link>,
            <Link href="/flights" :class="textLinkClass">flights</Link>, and
            <Link href="/more" :class="textLinkClass">whatever else I'm measuring</Link>.
            When I'm offline, I'm either
            <Link href="/activities/padel" :class="textLinkClass">hitting a ball with a racket</Link>
            or making another coffee.
        </p>
    </div>
</template>
