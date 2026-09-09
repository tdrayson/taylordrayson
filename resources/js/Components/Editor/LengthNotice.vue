<script setup>
import { computed } from 'vue';
import { Alert02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

/**
 * The offer to move the writing up a type, sat directly under the field it is
 * about.
 *
 * Deliberately not a panel. A tinted box with a button in it reads as an error
 * the form has thrown, and this is a suggestion about what is being written:
 * a line of text with the offer as a link in it, the way a hint under a field
 * reads. It also has to survive being the thing you see on a phone, which is
 * why it sits with the field rather than down at the save button.
 */
const props = defineProps({
    used: { type: Number, required: true },
    max: { type: Number, required: true },
    // The type this graduates into, e.g. 'article'.
    convertTo: { type: String, required: true },
});

defineEmits(['convert']);

/** How far ahead of the cap the suggestion starts, matching the ring's count. */
const NUDGE_WITHIN = 50;

const over = computed(() => props.used > props.max);

const shown = computed(() => props.used >= props.max - NUDGE_WITHIN);

const overBy = computed(() => props.used - props.max);

const article = computed(() => ('aeiou'.includes(props.convertTo[0]) ? 'an' : 'a'));
</script>

<template>
    <p
        v-if="shown"
        class="mt-2 flex items-start gap-2 text-meta"
        :class="over ? 'text-neutral-900' : 'text-neutral-500'"
        :role="over ? 'alert' : 'status'"
    >
        <Icon
            :icon="Alert02Icon"
            class="mt-0.5 size-4 shrink-0"
            :class="over ? 'text-red-500' : 'text-amber-500'"
        />

        <span>
            <template v-if="over">
                Too long for a note by {{ overBy.toLocaleString() }} {{ overBy === 1 ? 'character' : 'characters' }}.
            </template>

            <template v-else>
                This is getting long for a note.
            </template>

            <button
                type="button"
                class="text-accent-500 underline underline-offset-2 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                :class="over ? 'font-semibold' : ''"
                @click="$emit('convert')"
            >{{ over ? 'Turn' : 'Maybe turn' }} it into {{ article }} {{ convertTo }}</button>{{ over ? ' to post it.' : '?' }}
        </span>
    </p>
</template>
