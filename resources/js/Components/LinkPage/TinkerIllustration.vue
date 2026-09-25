<script setup>
defineProps({
    // The short banner above the details form shows only the lightbulb.
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="pointer-events-none absolute inset-0 text-white/15" aria-hidden="true">
        <!-- Pen tool: a bezier handle over a nib in a circle. -->
        <svg
            v-if="!compact"
            viewBox="0 0 320 230"
            class="absolute inset-y-0 -left-28 h-full sm:left-1/12"
            fill="none"
            stroke="currentColor"
            stroke-width="12"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M26 68h122M172 68h122" />
            <circle cx="14" cy="68" r="12" />
            <circle cx="306" cy="68" r="12" />
            <rect x="148" y="56" width="24" height="24" />
            <path d="M52 230a114 114 0 1 1 216 0" />
            <path d="M138 230v-60l22-58 22 58v60M138 170h44M160 150v80" />
        </svg>

        <!-- A lightbulb with a pencil for its filament, cropped by the banner's top edge. -->
        <svg
            viewBox="0 0 400 230"
            class="absolute inset-y-0 -right-8 h-full"
            :class="compact ? 'sm:right-1/12' : 'sm:right-1/6'"
            fill="none"
            stroke="currentColor"
            stroke-width="16"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M95 230c0-40-73-70-73-170a178 178 0 0 1 356 0c0 100-73 130-73 170" />
            <path d="M158 230V85l42-90 42 90v145M152 85h96M200 85v145M186 30h28" />
        </svg>
    </div>
</template>
