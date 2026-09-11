<script setup>
import { computed, ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import Input from '../Ui/Input.vue';
import DatePicker from '../Overlays/DatePicker.vue';
import { CONTROL } from '../../lib/editor/control.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { resolveDate } from '../../composables/useDynamicTags';

/**
 * One bound of a dynamic tag's custom date range: a calendar, a few relative
 * presets, and typed free text resolved through the server's own date grammar
 * (`Period::parse`). Separate from `DateTimeField`, which stamps a full
 * wall-clock instant for an entry's own date; this only ever picks a bare day
 * for a filter bound.
 */
const props = defineProps({
    modelValue: { type: String, default: '' }, // 'YYYY-MM-DD'
    label: { type: String, required: true },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const { isOpen: open, root, close, toggle } = useDismissable();
const typed = ref('');
const resolving = ref(false);

const PRESETS = ['Today', 'Yesterday', 'Last week', 'Last month'];

const display = computed(() => {
    if (! props.modelValue) {
        return null;
    }

    return new Date(`${props.modelValue}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
});

function choose(value) {
    emit('update:modelValue', value);
    typed.value = '';
    close();
}

function clear() {
    emit('update:modelValue', '');
    typed.value = '';
    close();
}

/**
 * Resolves whatever was typed through the server, so "last tuesday" and
 * "1 march" land on the same date `Period::parse` would give them at render
 * time. Silently does nothing on a string it cannot read, the same "swallow
 * rather than throw" the server itself applies to an unparseable bound.
 */
async function resolveTyped() {
    const value = typed.value.trim();

    if (value === '') {
        return;
    }

    resolving.value = true;
    const resolved = await resolveDate(value);
    resolving.value = false;

    if (resolved) {
        choose(resolved);
    }
}

function choosePreset(preset) {
    typed.value = preset;
    resolveTyped();
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            :id="id"
            type="button"
            :aria-label="`${label} date`"
            :class="[CONTROL, 'flex items-center gap-2 border-neutral-100 text-left hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500', display ? 'text-neutral-900' : 'text-neutral-500']"
            @click="toggle"
        >
            <Icon name="Calendar03Icon" class="size-4 shrink-0" />
            <span class="flex-1 truncate">{{ display ?? label }}</span>
        </button>

        <div v-if="open" class="absolute inset-x-0 z-30 mt-1 rounded-lg border border-neutral-100 bg-neutral-0 p-3 shadow-lg sm:right-auto sm:w-72">
            <Input
                v-model="typed"
                placeholder="1 March, last tuesday"
                :aria-label="`${label} date, typed`"
                :disabled="resolving"
                class="mb-2"
                @keydown.enter.prevent="resolveTyped"
            />

            <ul class="mb-2 flex flex-wrap gap-1.5">
                <li v-for="preset in PRESETS" :key="preset">
                    <button
                        type="button"
                        class="rounded-full bg-neutral-25 px-2.5 py-1 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                        @click="choosePreset(preset)"
                    >
                        {{ preset }}
                    </button>
                </li>
            </ul>

            <DatePicker :model-value="modelValue" mode="day" inline @update:model-value="choose" />

            <button
                v-if="modelValue"
                type="button"
                class="mt-2 w-full rounded-md bg-neutral-25 py-2 text-caption text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @click="clear"
            >
                Clear
            </button>
        </div>
    </div>
</template>
