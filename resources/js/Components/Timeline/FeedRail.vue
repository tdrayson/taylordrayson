<script setup>
/**
 * The vertical rail every feed hangs off: the gradient line, the dot that caps
 * it, and the left padding that leaves room for each item's icon bubble.
 * Extracted from TimelineFeed so the trip index can present the same way as the
 * timeline without duplicating the geometry.
 */
</script>

<template>
    <div class="timeline-feed flex flex-col gap-9 pl-14 lg:pl-0">
        <slot />
    </div>
</template>

<style scoped>
.timeline-feed {
    position: relative;
}

.timeline-feed::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 14px;
    bottom: 10px;
    width: 2px;
    background: linear-gradient(to bottom, var(--color-neutral-25), var(--color-neutral-50));
    border-radius: 2px;
}

.timeline-feed::after {
    content: '';
    position: absolute;
    left: 14px;
    bottom: 6px;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: var(--color-neutral-50);
}

/* From lg up the cards align to the content column and the icon rail hangs just
   outside it in the grid gutter, so the line/dot follow the icon's new centre. */
@media (min-width: 1024px) {
    .timeline-feed::before {
        left: -31px;
    }

    .timeline-feed::after {
        left: -34px;
    }
}
</style>
