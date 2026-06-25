<script setup>
defineProps({
    fill: { type: Boolean, default: false },
    title: { type: String, default: 'How to Win Friends and Influence People' },
    author: { type: String, default: 'Dale Carnegie' },
    cover: { type: String, default: 'https://duckduckgo.com/i/3d84f7550ffc1d4c.jpg' },
});

const onCoverError = (event) => {
    event.target.style.display = 'none';
};
</script>

<template>
    <div class="reading rounded-3xl" :class="{ 'reading--has-aspect': !fill }">
        <div class="reading__cover">
            <div class="reading__cover-fallback"><span>Cover</span></div>
            <img v-if="cover" class="reading__cover-image" :src="cover" alt="" referrerpolicy="no-referrer" @error="onCoverError" />
        </div>

        <div class="reading__info">
            <div class="reading__eyebrow">Currently reading</div>
            <div class="reading__title">{{ title }}</div>
            <div class="reading__author">{{ author }}</div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 4.52). Children are absolutely placed, so they reference the card. */
.reading {
    container-type: inline-size;
    position: relative;
    overflow: hidden;
    background: var(--color-neutral-0);
    box-shadow: var(--shadow-card);
}

.reading--has-aspect {
    aspect-ratio: 2 / 1;
}

/* Tilted cover that bleeds off the bottom-left, sliding up into place. */
.reading__cover {
    position: absolute;
    left: 5.3cqw;
    bottom: -4.9cqw;
    width: 33.6cqw;
    aspect-ratio: 2 / 3;
    z-index: 2;
    transform: rotate(-5deg);
    transform-origin: bottom center;
    border-radius: 1.8cqw;
    overflow: hidden;
    box-shadow:
        0 3.5cqw 7.5cqw rgba(20, 22, 30, 0.32),
        0 0.9cqw 2.2cqw rgba(20, 22, 30, 0.2);
    animation: reading-book-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
}

.reading__cover-fallback {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.65cqw;
    text-align: center;
    background: linear-gradient(150deg, #efe7d6, #f6f1e6);
}

.reading__cover-fallback span {
    font-size: 2.2cqw;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #aa9999;
}

.reading__cover-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 1;
}

.reading__info {
    position: absolute;
    left: 45.6cqw;
    right: 5.75cqw;
    top: 0;
    bottom: 0;
    z-index: 3;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.reading__eyebrow {
    font-size: 2.4cqw;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #9298a2;
}

.reading__title {
    margin-top: 1.55cqw;
    font-size: 4.8cqw;
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.12;
    color: #16181c;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;
}

.reading__author {
    margin-top: 2cqw;
    font-size: 3.1cqw;
    font-weight: 500;
    color: #9298a2;
}

@keyframes reading-book-in {
    from {
        transform: translateY(45%) rotate(-5deg);
        opacity: 0;
    }

    to {
        transform: translateY(0) rotate(-5deg);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .reading__cover {
        animation: none;
    }
}
</style>
