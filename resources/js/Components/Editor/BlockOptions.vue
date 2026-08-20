<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';

/**
 * The options panel for whichever block the caret is in.
 *
 * One panel rather than controls scattered onto each block: a code block's
 * language and an image's alt text are the same kind of thing, and putting them
 * inside the block means every block grows its own chrome.
 *
 * Fields are declared per block type in blockOptions.js, so a new block needs a
 * definition there and nothing here.
 */
const props = defineProps({
    editor: { type: Object, required: true },
    // { type, label, fields: [{ name, label, type, options? }] }
    definition: { type: Object, required: true },
});

const attributes = computed(() => props.editor.getAttributes(props.definition.type));

function update(name, value) {
    props.editor.commands.updateAttributes(props.definition.type, { [name]: value });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-neutral-100 bg-neutral-0 p-2 shadow-lg">
        <span class="flex items-center gap-1.5 pl-1 pr-2 text-label uppercase text-neutral-500">
            <Icon v-if="definition.icon" :name="definition.icon" class="size-3.5" />
            {{ definition.label }}
        </span>

        <template v-for="field in definition.fields" :key="field.name">
            <label v-if="field.type === 'boolean'" class="flex items-center gap-1.5 text-meta text-neutral-700">
                <input
                    type="checkbox"
                    :checked="Boolean(attributes[field.name])"
                    :aria-label="field.label"
                    class="size-4 rounded border-neutral-100 text-accent-500 focus-visible:ring-2 focus-visible:ring-accent-500"
                    @change="update(field.name, $event.target.checked)"
                >
                {{ field.label }}
            </label>

            <select
                v-else-if="field.type === 'select'"
                :value="attributes[field.name] ?? ''"
                :aria-label="field.label"
                class="rounded-md border border-neutral-100 bg-neutral-0 px-2 py-1 text-meta text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @change="update(field.name, $event.target.value || null)"
            >
                <option value="">{{ field.label }}</option>
                <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <input
                v-else
                :value="attributes[field.name] ?? ''"
                type="text"
                :placeholder="field.label"
                :aria-label="field.label"
                :class="[
                    'min-w-0 rounded-md border border-neutral-100 px-2 py-1 text-meta text-neutral-900 placeholder:text-neutral-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                    field.wide ? 'w-48' : 'w-32',
                ]"
                @input="update(field.name, $event.target.value || null)"
            >
        </template>
    </div>
</template>
