<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    tabs: { type: Array, default: () => ['Main'] },
});

const active = ref(props.tabs[0] ?? 'Main');
const showTabs = computed(() => props.tabs.length > 1);

defineExpose({ active });
</script>

<template>
    <div class="flex flex-col gap-6">
        <header class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-2.5">
                <slot name="status" />
                <h1 class="truncate text-item-title text-neutral-900">
                    <slot name="heading" />
                </h1>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <nav v-if="showTabs" class="flex gap-6 border-b border-neutral-50">
            <button
                v-for="tab in tabs"
                :key="tab"
                type="button"
                class="-mb-px border-b-2 pb-2.5 text-caption font-medium transition-colors"
                :class="active === tab ? 'border-accent-500 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-900'"
                @click="active = tab"
            >
                {{ tab }}
            </button>
        </nav>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <slot name="main" :active="active" />
            </div>
            <aside class="flex flex-col gap-6 lg:sticky lg:top-24 lg:self-start">
                <slot name="sidebar" :active="active" />
            </aside>
        </div>
    </div>
</template>
