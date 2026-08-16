<script setup>
import { computed } from 'vue';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import Input from '../Ui/Input.vue';
import LocationMap from '../Maps/LocationMap.vue';
import RichTextEditor from './RichTextEditor.vue';
import LookupInput from './LookupInput.vue';
import LocationInput from './LocationInput.vue';
import DateTimeField from './DateTimeField.vue';
import TagsInput from './TagsInput.vue';
import DurationInput from './DurationInput.vue';
import DistanceInput from './DistanceInput.vue';

/**
 * One field, drawn from its definition. The `type` on the definition is the
 * only thing deciding what appears, so a field added in PHP needs no change
 * here unless it introduces a genuinely new kind of input.
 */
const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array, Object], default: null },
    // kind:id -> resolved mention, forwarded to the rich-text editor.
    resolved: { type: Object, default: () => ({}) },
    // The body carries no label: the placeholder says what it is.
    hideLabel: { type: Boolean, default: false },
    // The value of the field this one is measured from, when it declares one.
    relativeToValue: { type: String, default: null },
    // Filled by the location lookup and never typed, so the map is the only
    // way to check them.
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    // A field drawn inside this one's control rather than as its own row: a
    // timezone belongs to the date it qualifies, not beside it.
    paired: { type: Object, default: null },
    pairedValue: { type: String, default: null },
});

/** The picked point, or null while the lookup has not resolved one. */
const coordinates = computed(() => {
    const lat = Number(props.latitude);
    const lng = Number(props.longitude);

    return Number.isFinite(lat) && Number.isFinite(lng) && (lat !== 0 || lng !== 0) ? { lat, lng } : null;
});

// `fill` carries the sibling values a lookup resolved: a book's author, a
// place's coordinates. The editor applies them; this component does not know
// what other fields exist.
defineEmits(['update:modelValue', 'update:paired', 'fill']);

/**
 * A datetime-local input silently renders blank for anything but
 * YYYY-MM-DDTHH:mm. Sliced rather than parsed through `new Date`, which reads a
 * stored wall-clock time as UTC and walks the clock forward every save.
 */
function toLocalInput(value) {
    if (! value) {
        return '';
    }

    const match = String(value).match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/);

    return match ? `${match[1]}T${match[2]}` : '';
}

/** Tags are stored as a list but typed as a comma-separated line. */
function tagsToText(value) {
    return Array.isArray(value) ? value.join(', ') : '';
}

function textToTags(value) {
    return value.split(',').map((tag) => tag.trim()).filter(Boolean);
}
</script>

<template>
    <div>
        <label v-if="! hideLabel" :for="field.name" class="mb-1 block text-label uppercase text-neutral-500">{{ field.label }}</label>

        <RichTextEditor
            v-if="field.type === 'rich-text'"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            profile="document"
            :placeholder="field.help || 'Write something. Type @ to mention an entry.'"
            :resolved="resolved"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <textarea
            v-else-if="field.type === 'textarea'"
            :id="field.name"
            :value="modelValue ?? ''"
            rows="4"
            :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <label v-else-if="field.type === 'boolean'" class="flex min-h-11 items-center gap-2 text-meta text-neutral-900">
            <input
                :id="field.name"
                type="checkbox"
                :checked="Boolean(modelValue)"
                class="size-5 rounded border-neutral-100 text-accent-500 focus-visible:ring-2 focus-visible:ring-accent-500"
                @change="$emit('update:modelValue', $event.target.checked)"
            >
            {{ field.help || field.label }}
        </label>

        <select
            v-else-if="field.type === 'select'"
            :id="field.name"
            :value="modelValue ?? ''"
            :class="[CONTROL, CONTROL_BORDER, modelValue ? 'text-neutral-900' : 'text-neutral-500']"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="" disabled>Choose {{ field.label.toLowerCase() }}</option>

            <option v-for="option in field.options ?? []" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <TagsInput
            v-else-if="field.type === 'tags'"
            :id="field.name"
            :model-value="Array.isArray(modelValue) ? modelValue : []"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <DateTimeField
            v-else-if="field.type === 'datetime'"
            :id="field.name"
            :model-value="String(modelValue ?? '')"
            :relative-to-value="relativeToValue"
            :timezone="paired ? String(pairedValue ?? '') : null"
            :timezone-label="paired?.label ?? 'Timezone'"
            @update:model-value="$emit('update:modelValue', $event)"
            @update:timezone="$emit('update:paired', $event)"
        />

        <DurationInput
            v-else-if="field.type === 'duration'"
            :id="field.name"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <DistanceInput
            v-else-if="field.type === 'distance'"
            :id="field.name"
            :model-value="modelValue"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <LookupInput
            v-else-if="field.type === 'lookup'"
            :id="field.name"
            :model-value="modelValue ?? ''"
            :source="field.source"
            :placeholder="field.help ?? ''"
            @update:model-value="$emit('update:modelValue', $event)"
            @fill="$emit('fill', $event)"
        />

        <LocationInput
            v-else-if="field.type === 'location'"
            :id="field.name"
            :model-value="modelValue ?? ''"
            :source="field.source ?? 'place'"
            :placeholder="field.help ?? ''"
            @update:model-value="$emit('update:modelValue', $event)"
            @fill="$emit('fill', $event)"
        />

        <Input
            v-else
            :id="field.name"
            :model-value="modelValue ?? ''"
            :type="field.type === 'number' ? 'number' : 'text'"
            :inputmode="field.type === 'number' ? 'decimal' : undefined"
            :step="field.type === 'number' ? 'any' : undefined"
            :prefix="field.prefix"
            :suffix="field.suffix"
            @update:model-value="$emit('update:modelValue', $event)"
        />

        <LocationMap
            v-if="field.type === 'location' && coordinates"
            :lat="coordinates.lat"
            :lng="coordinates.lng"
            :label="String(modelValue ?? '')"
            :zoom="15"
            height-class="h-40 sm:h-56"
            class="mt-3 overflow-hidden rounded-lg"
        />

        <!-- Lookup and location fields already show the help as their
             placeholder, and a boolean shows it beside the checkbox. -->
        <p
            v-if="field.help && ! ['boolean', 'rich-text', 'lookup', 'location'].includes(field.type)"
            class="mt-1 text-caption text-neutral-500"
        >
            {{ field.help }}
        </p>
    </div>
</template>
