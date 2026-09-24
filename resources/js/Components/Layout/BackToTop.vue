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
    <Transition name="rise">
        <button
            v-if="visible"
            type="button"
            class="flex size-12 items-center justify-center rounded-full border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-card transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:text-accent-700"
            aria-label="Back to top"
            @click="toTop"
        >
            <Icon name="ArrowUp01Icon" class="size-5" />
        </button>
    </Transition>
</template>
