<script setup>
import InlineBadge from '../Ui/InlineBadge.vue';

defineProps({
    streakDays: { type: [Number, String], default: '2,145' },
    agencyUrl: { type: String, default: 'https://thecreativetinker.com' },
    pluginUrl: { type: String, default: 'https://wpextended.io' },
    podcastUrl: { type: String, default: 'https://www.thisweekwith.co.uk' },
});

const linkClass =
    'whitespace-nowrap rounded-md bg-neutral-25 px-1.5 py-0.5 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 hover:decoration-accent-500 focus-visible:text-accent-500 focus-visible:decoration-accent-500';
</script>

<template>
    <div class="max-w-2xl text-xl leading-relaxed tracking-tight text-neutral-700">
        <p>
            Hey! I'm Taylor<InlineBadge src="/headshot-taylor.jpg" alt="Taylor Drayson" class="ml-2" />, a web developer in
            London who tracks everything including every calorie for
            <span class="tnum">{{ streakDays }}</span> days straight (and counting).
        </p>
        <p class="mt-6">
            I run a <a :href="agencyUrl" target="_blank" rel="noopener noreferrer" :class="linkClass"><InlineBadge src="/logos/the-creative-tinker.png" alt="The Creative Tinker" class="ml-1 mr-2" />small web agency</a>
            I started at 19, develop a <a :href="pluginUrl" target="_blank" rel="noopener noreferrer" :class="linkClass"><InlineBadge src="/logos/wp-extended.png" alt="WP Extended" class="ml-1 mr-2" />WordPress plugin</a>,
            and co-host a <a :href="podcastUrl" target="_blank" rel="noopener noreferrer" :class="linkClass"><InlineBadge src="/logos/this-week-with.jpg" alt="This Week With" class="ml-1 mr-2" />weekly podcast</a>
            with my dad with over 230 episodes. When I touch grass, I'm probably playing a racket sport or making another coffee.
        </p>
    </div>
</template>
