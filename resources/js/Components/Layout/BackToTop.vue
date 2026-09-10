<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

/**
 * A floating return-to-top button, shown once the reader is far enough down
 * that the header is well out of reach. Positioned by the stack it sits in.
 */
const page = usePage();
const visible = ref(false);

/**
 * Reveal the button past roughly a screen of scrolling, so it never appears on
 * a page whose top is still in view.
 *
 * @returns {void}
 */
function onScroll() {
    visible.value = window.scrollY > 600;
}

/**
 * Return the reader to the top of the page.
 *
 * @returns {void}
 */
function toTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// The layout keeps this mounted across visits, so a new page needs its own read
// rather than inheriting the last page's scroll position.
watch(() => page.url, () => onScroll());

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Transition name="lift">
        <button
            v-if="visible"
            type="button"
            class="flex size-12 items-center justify-center rounded-full border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-card transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:border-accent-500 focus-visible:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
            aria-label="Back to top"
            @click="toTop"
        >
            <Icon name="ArrowUp01Icon" class="size-5" />
        </button>
    </Transition>
</template>

<style scoped>
.lift-enter-active,
.lift-leave-active {
    transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.lift-enter-from,
.lift-leave-to {
    opacity: 0;
    transform: translateY(0.75rem);
}

@media (prefers-reduced-motion: reduce) {
    .lift-enter-active,
    .lift-leave-active {
        transition: opacity 0.2s ease;
    }

    .lift-enter-from,
    .lift-leave-to {
        transform: none;
    }
}
</style>
