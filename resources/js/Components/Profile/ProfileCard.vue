<script setup>
import { ref, onBeforeUnmount } from 'vue';
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

// Tuning for the gravity simulation. All in px and seconds so the maths reads
// physically; the loop is frame-rate independent (dt-based) so it looks the
// same at 60 or 120Hz.
const GRAVITY = 2600; // downward acceleration, px/s^2
const RESTITUTION = 0.72; // vertical speed kept on each floor bounce
const FRICTION = 0.95; // horizontal speed kept on each bounce
const LAUNCH_VX = 420; // initial rightward speed, px/s
const LAUNCH_VY = -120; // initial upward nudge, px/s
const SQUASH = 0.16; // strongest squash on a hard landing (scaleY = 1 - this)
const SQUASH_DECAY = 0.82; // how quickly a squash relaxes back to round each frame
const MAX_BOUNCES = 3; // floor bounces before it drops off the bottom
const WRAP_DELAY = 0.35; // pause (s) below the screen before re-entering from the top
const MAX_SECONDS = 6; // safety cap so the loop always ends

const avatarEl = ref(null);
const flying = ref(false);
const ballStyle = ref({});
let raf = null;

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Boing: clone the avatar and run a real gravity simulation so it pops up,
 * bounces with naturally decaying height as it travels right, then rolls
 * off-screen. Smoother and more organic than scripted CSS keyframes. The
 * original stays hidden until the clone finishes.
 */
function launchAvatar() {
    if (flying.value || prefersReducedMotion()) {
        return;
    }

    // Measure the wrapping button, not the avatar itself: the hover "boop"
    // transform (scale/rotate) inflates the avatar's own rect, which would make
    // the clone pop out oversized and land off-position. The button's layout box
    // is unaffected by the child transform, so it gives the true resting rect.
    const rect = avatarEl.value.$el.parentElement.getBoundingClientRect();
    const floor = window.innerHeight - rect.height - 4; // viewport bottom
    const below = window.innerHeight + rect.height; // fully past the bottom edge
    const homeX = rect.left;
    const homeY = rect.top;

    let x = homeX;
    let y = homeY;
    let vx = LAUNCH_VX;
    let vy = LAUNCH_VY;
    let squash = 0;
    let bounces = 0;
    let phase = 'bounce'; // bounce -> falling -> wrap -> home
    let wrapAt = 0;
    let last = null;
    let elapsed = 0;

    // Squash only on ground contact (set in land(), eased away each frame); the
    // avatar stays perfectly round while airborne.
    const draw = () => {
        const scaleX = (1 + squash * 0.5).toFixed(3);
        const scaleY = (1 - squash).toFixed(3);

        ballStyle.value = {
            left: '0px',
            top: '0px',
            width: `${rect.width}px`,
            height: `${rect.height}px`,
            transform: `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px) scale(${scaleX}, ${scaleY})`,
        };
    };

    const land = (speed) => {
        squash = Math.min(SQUASH, speed / 3000); // harder landing, bigger squash
    };

    const step = (now) => {
        last ??= now;
        const dt = Math.min((now - last) / 1000, 0.032); // clamp tab-switch jumps
        last = now;
        elapsed += dt;
        squash *= SQUASH_DECAY;

        // Off the bottom: hold for a beat, then re-enter falling from the top
        // at the home column (Pac-Man wrap).
        if (phase === 'wrap') {
            if (elapsed - wrapAt >= WRAP_DELAY) {
                phase = 'home';
                x = homeX;
                y = -rect.height;
                vx = 0;
                vy = 0;
            }

            draw();
            raf = requestAnimationFrame(step);

            return;
        }

        vy += GRAVITY * dt;
        x += vx * dt;
        y += vy * dt;

        if (phase === 'bounce' && y >= floor) {
            y = floor;
            land(Math.abs(vy));
            vy = -vy * RESTITUTION;
            vx *= FRICTION;

            if (++bounces >= MAX_BOUNCES) {
                phase = 'falling'; // let the next descent carry it off the bottom
            }
        } else if (phase === 'falling' && y >= below) {
            phase = 'wrap';
            wrapAt = elapsed;
        } else if (phase === 'home' && y >= homeY) {
            y = homeY;

            if (Math.abs(vy) < 80) {
                draw();
                stopBounce(); // settled back home; the static avatar takes over

                return;
            }

            land(Math.abs(vy));
            vy = -vy * 0.4; // small settle hop, like it has dropped into place
        }

        draw();

        if (elapsed > MAX_SECONDS) {
            stopBounce();

            return;
        }

        raf = requestAnimationFrame(step);
    };

    flying.value = true;
    draw();
    raf = requestAnimationFrame(step);
}

function stopBounce() {
    if (raf) {
        cancelAnimationFrame(raf);
        raf = null;
    }

    flying.value = false;
}

onBeforeUnmount(() => {
    if (raf) {
        cancelAnimationFrame(raf);
    }
});
</script>

<template>
    <div class="h-card">
        <Link
            href="/"
            aria-label="Home"
            class="group mb-3 block w-fit"
            @click="launchAvatar"
        >
            <Avatar
                ref="avatarEl"
                :src="avatar"
                :alt="name"
                img-class="u-photo"
                :class="['group-hover:animate-avatar-boop', { 'opacity-0': flying }]"
            />
        </Link>
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
}

</style>
