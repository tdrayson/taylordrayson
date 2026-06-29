<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from './Avatar.vue';
import SocialLinks from './SocialLinks.vue';

defineProps({
    name: { type: String, default: 'Taylor Drayson' },
    bio: {
        type: String,
        default: 'I build stuff on the internet, track everything, and drink too much coffee.',
    },
    avatar: { type: String, default: '/taylor-cutout.png' },
});

const avatarEl = ref(null);
const flying = ref(false);
const ballStyle = ref({});

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Pop the avatar off the sidebar and run the CSS bounce. The dynamic distances
 * (floor, off-screen offsets, forward travel) are passed to the keyframes as
 * custom properties so the static CSS adapts to wherever the avatar sits.
 */
function launchAvatar() {
    if (flying.value || prefersReducedMotion()) {
        return;
    }

    const rect = avatarEl.value.$el.getBoundingClientRect();
    const floor = window.innerHeight - rect.bottom + 6; // bottom edge meets the viewport floor
    const offBottom = window.innerHeight - rect.top + 80; // fully below the viewport
    const offTop = -(rect.bottom + 80); // fully above the viewport
    const forward = Math.max(220, Math.min(window.innerWidth - rect.right - 48, window.innerWidth * 0.62));

    ballStyle.value = {
        left: `${rect.left}px`,
        top: `${rect.top}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
        '--floor': `${floor}px`,
        '--off-bottom': `${offBottom}px`,
        '--off-top': `${offTop}px`,
        '--fwd': `${forward}px`,
    };
    flying.value = true;
}
</script>

<template>
    <div class="h-card">
        <button type="button" aria-label="Boing" class="group mb-3 block w-fit" @click="launchAvatar">
            <Avatar
                ref="avatarEl"
                :src="avatar"
                :alt="name"
                img-class="u-photo"
                :class="['group-hover:animate-avatar-boop', { 'opacity-0': flying }]"
            />
        </button>
        <p class="mb-2 font-display text-name">
            <Link href="/" class="p-name u-url u-uid">{{ name }}</Link>
        </p>
        <p class="mb-4 max-w-50 p-note text-caption text-neutral-500">{{ bio }}</p>
        <SocialLinks />

        <Teleport to="body">
            <Avatar
                v-if="flying"
                :src="avatar"
                alt=""
                class="avatar-ball"
                :style="ballStyle"
                @animationend="flying = false"
            />
        </Teleport>
    </div>
</template>

<style scoped>
/* The flying clone lives on <body> (via Teleport) so the sidebar's overflow
   never clips it; position/size come from the original avatar's rect. */
.avatar-ball {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    transform-origin: center bottom;
    will-change: transform, opacity;
    animation: avatar-bounce 3.1s forwards;
}

/* Forward arcs decay in height as the ball rolls right; rises ease-out, falls
   ease-in. It drops off the bottom, hides (opacity 0) while it teleports above
   the top, then bounces back in. Pure translation, no squash. */
@keyframes avatar-bounce {
    0% {
        transform: translate(0px, 0px);
        opacity: 1;
        animation-timing-function: ease-out;
    }

    5% {
        transform: translate(calc(var(--fwd) * 0.04), -160px);
        animation-timing-function: ease-in;
    }

    16% {
        transform: translate(calc(var(--fwd) * 0.18), var(--floor));
        animation-timing-function: ease-out;
    }

    24% {
        transform: translate(calc(var(--fwd) * 0.3), calc(var(--floor) - 300px));
        animation-timing-function: ease-in;
    }

    32% {
        transform: translate(calc(var(--fwd) * 0.42), var(--floor));
        animation-timing-function: ease-out;
    }

    39% {
        transform: translate(calc(var(--fwd) * 0.52), calc(var(--floor) - 200px));
        animation-timing-function: ease-in;
    }

    46% {
        transform: translate(calc(var(--fwd) * 0.62), var(--floor));
        animation-timing-function: ease-out;
    }

    52% {
        transform: translate(calc(var(--fwd) * 0.7), calc(var(--floor) - 120px));
        animation-timing-function: ease-in;
    }

    58% {
        transform: translate(calc(var(--fwd) * 0.8), var(--floor));
        animation-timing-function: ease-in;
    }

    66% {
        transform: translate(calc(var(--fwd) * 0.9), var(--off-bottom));
        opacity: 1;
        animation-timing-function: linear;
    }

    68.5% {
        transform: translate(calc(var(--fwd) * 0.95), calc(var(--off-bottom) + 60px));
        opacity: 0;
        animation-timing-function: linear;
    }

    73% {
        transform: translate(0px, var(--off-top));
        opacity: 0;
        animation-timing-function: ease-out;
    }

    75% {
        transform: translate(0px, calc(var(--off-top) + 10px));
        opacity: 1;
        animation-timing-function: ease-in;
    }

    85% {
        transform: translate(0px, 0px);
        animation-timing-function: ease-out;
    }

    89% {
        transform: translate(0px, -72px);
        animation-timing-function: ease-in;
    }

    93% {
        transform: translate(0px, 0px);
        animation-timing-function: ease-out;
    }

    96% {
        transform: translate(0px, -30px);
        animation-timing-function: ease-in;
    }

    98.5% {
        transform: translate(0px, 0px);
        animation-timing-function: ease-out;
    }

    100% {
        transform: translate(0px, 0px);
        opacity: 1;
    }
}
</style>
