<script setup>
defineProps({
    size: { type: Number, default: 150 },
});
</script>

<template>
    <svg class="scene" :width="size" :height="size" viewBox="0 0 150 150" fill="none" aria-hidden="true">
        <g class="sun">
            <g class="rays" stroke="#ffb300" stroke-width="6" stroke-linecap="round">
                <line x1="58" y1="10" x2="58" y2="24" />
                <line x1="58" y1="92" x2="58" y2="106" />
                <line x1="10" y1="58" x2="24" y2="58" />
                <line x1="92" y1="58" x2="106" y2="58" />
                <line x1="24" y1="24" x2="34" y2="34" />
                <line x1="82" y1="82" x2="92" y2="92" />
                <line x1="92" y1="24" x2="82" y2="34" />
                <line x1="34" y1="82" x2="24" y2="92" />
            </g>
            <circle cx="58" cy="58" r="26" fill="#ffd23f" />
        </g>
        <g class="cloud">
            <path
                d="M52 108c-12 0-22-9-22-21s10-21 22-21c3 0 6 .6 8.6 1.8C65 58 75 51 86 51c16 0 29 12 29 28 0 .9 0 1.8-.1 2.6 9 1.6 15.7 9 15.7 18 0 10-8.5 18.4-19 18.4H52Z"
                fill="#fff"
                stroke="rgba(0,0,0,0.06)"
                stroke-width="2"
            />
        </g>
    </svg>
</template>

<style scoped>
.scene {
    overflow: visible;
    filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.12));
}

.rays {
    transform-origin: 58px 58px;
    animation: spin 24s linear infinite;
}

.cloud {
    transform-origin: center;
    animation: drift 5s ease-in-out infinite alternate;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes drift {
    from {
        transform: translateX(-5px);
    }

    to {
        transform: translateX(7px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .rays,
    .cloud {
        animation: none;
    }
}
</style>
