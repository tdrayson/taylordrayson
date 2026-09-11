<script setup>
import { computed, ref } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import Icon from '../Ui/Icon.vue';
import BatteryStatus from '../Layout/BatteryStatus.vue';
import DynamicTagOptions from './DynamicTagOptions.vue';
import { useDynamicTags } from '../../composables/useDynamicTags';
import { dynamicTagIcon } from '../../lib/dynamicTagIcon';

/**
 * A dynamic tag while the editor is open: a reference to live site data,
 * rendered as a quiet inline chip showing what it currently resolves to
 * rather than the raw `{tag options}` token. Clicking one that takes options
 * reopens the options popup, the same way clicking a link reopens its editor.
 */
const props = defineProps({
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
    selected: { type: Boolean, default: false },
});

const { tags, previewFor, iconFor, valueFor } = useDynamicTags();

const name = computed(() => props.node.attrs.tag ?? '');
const options = computed(() => props.node.attrs.options ?? {});
// Null while the registry is still loading, or if the stored tag name is
// no longer registered.
const schema = computed(() => tags.value.find((candidate) => candidate.name === name.value) ?? null);
const hasOptions = computed(() => (schema.value?.options.length ?? 0) > 0);

const label = computed(() => (hasOptions.value ? `Edit ${schema.value.label} options` : `Dynamic tag: ${name.value}`));
// Falls back to the tag name while the registry is still loading, or while a
// non-default option set is still resolving its own preview.
const display = computed(() => previewFor(name.value, options.value) ?? name.value);

// The same glyph the published render shows, so a chip with its icon option
// on never looks different while writing than it will once published.
const icon = computed(() => dynamicTagIcon(
    name.value,
    valueFor(name.value, options.value),
    iconFor(name.value, options.value),
));

const popupOpen = ref(false);

function openOptions() {
    if (hasOptions.value) {
        popupOpen.value = true;
    }
}

function applyOptions(newOptions) {
    props.updateAttributes({ options: newOptions });
}
</script>

<template>
    <NodeViewWrapper
        as="span"
        contenteditable="false"
        :aria-label="label"
        :role="hasOptions ? 'button' : undefined"
        :tabindex="hasOptions ? 0 : undefined"
        :class="[
            'inline-flex items-center gap-1 rounded bg-neutral-25 px-1 py-0.5 align-baseline font-medium text-neutral-900 transition-colors',
            selected ? 'ring-2 ring-accent-500' : '',
            hasOptions ? 'cursor-pointer hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500' : '',
        ]"
        @click="openOptions"
        @keydown.enter.prevent="openOptions"
        @keydown.space.prevent="openOptions"
    >
        <BatteryStatus
            v-if="icon?.kind === 'battery'"
            :level="icon.level"
            :charging="icon.charging"
            :low-power="icon.lowPower"
            class="shrink-0"
        />
        <img v-else-if="icon?.kind === 'favicon'" :src="icon.src" alt="" class="size-3.5 shrink-0 object-contain">
        <Icon v-else :icon="icon?.icon ?? 'ChartColumnIcon'" class="size-3.5 shrink-0" />{{ display }}

        <DynamicTagOptions
            v-if="schema"
            :open="popupOpen"
            :tag="schema"
            :options="options"
            @apply="applyOptions"
            @update:open="popupOpen = false"
        />
    </NodeViewWrapper>
</template>
