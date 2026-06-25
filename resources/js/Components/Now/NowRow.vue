<template>
    <div class="flex items-start gap-4">
        <span class="flex h-7 flex-none items-center">
            <span class="flex size-9 items-center justify-center rounded-full border border-neutral-100 text-neutral-500">
                <slot name="icon" />
            </span>
        </span>
        <p class="text-lg leading-7 text-neutral-700">
            <slot />
        </p>
    </div>
</template>
