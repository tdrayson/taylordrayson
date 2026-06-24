<template>
    <div class="flex items-start gap-4">
        <span class="flex h-7 flex-none items-center">
            <span class="flex size-9 items-center justify-center rounded-full border border-line text-ink-3">
                <slot name="icon" />
            </span>
        </span>
        <p class="text-lg leading-7 text-ink-2">
            <slot />
        </p>
    </div>
</template>
