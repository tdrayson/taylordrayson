<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Icon from '../Ui/Icon.vue';
import { CONTROL } from '../../lib/editor/control.js';
import { readCookie } from '../../lib/cookies.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { useListNavigation } from '../../lib/editor/listNavigation.js';

/**
 * Adds and removes an entry's direct subjects, autocompleting over existing
 * ones ranked by use, mirroring TagsInput. Two differences: chips are subject
 * ids rather than name strings, since a subject is a row and renaming one
 * must not orphan the tag; and every change saves itself immediately rather
 * than waiting on a surrounding form, since a synced entry (a Strava
 * activity, a Swarm check-in) has no form to wait on.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // list<{id, name}>
    // list<{id, name}>, subjects the prose already names but hasn't tagged,
    // offered as one-tap adds. A mention is a suggestion, never a tag: picking
    // one here is the author's own decision, not something done for them.
    mentioned: { type: Array, default: () => [] },
    type: { type: String, required: true },
    entryId: { type: [Number, String], required: true },
});

const emit = defineEmits(['close']);

const { isOpen: open, root, open: show, close } = useDismissable();
const query = ref('');
const suggestions = ref([]);
const creating = ref(false);
let timer = null;

const subjects = computed(() => props.modelValue);

// Already-tagged subjects are dropped from the list: offering one already on
// the entry is a row that does nothing.
const offered = computed(() => suggestions.value.filter(
    (suggestion) => ! subjects.value.some((subject) => subject.id === suggestion.value),
));

// Re-filtered client-side too: a suggestion tapped a moment ago is still on
// the entry until the sync round-trips, and shouldn't flash back into view.
const mentionedOffered = computed(() => props.mentioned.filter(
    (subject) => ! subjects.value.some((tagged) => tagged.id === subject.id),
));

// The create shortcut only replaces an empty result, not a partial one: a
// name close to an existing subject should still be picked from the list
// rather than filed under a near-duplicate.
const listItems = computed(() => {
    if (offered.value.length) {
        return offered.value;
    }

    const name = query.value.trim();

    return name === '' ? [] : [{ value: '__create__', label: `Create ${name} as a person`, create: true }];
});

async function search() {
    try {
        const response = await fetch(`/lookup/subject?q=${encodeURIComponent(query.value)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        suggestions.value = response.ok ? (await response.json()).data ?? [] : [];
        show();
    } catch {
        suggestions.value = [];
    }
}

function onInput(value) {
    query.value = value;
    clearTimeout(timer);
    timer = setTimeout(search, 200);
}

/** Sync the entry's whole subject list, wholesale, matching what the server expects. */
function sync(next) {
    router.post(`/entries/${props.type}/${props.entryId}/subjects`, {
        subjects: next.map((subject) => subject.id),
    }, { preserveScroll: true });
}

function pick(suggestion) {
    if (subjects.value.some((subject) => subject.id === suggestion.value)) {
        return;
    }

    sync([...subjects.value, { id: suggestion.value, name: suggestion.label }]);
    query.value = '';
    suggestions.value = [];
    close();
}

function remove(id) {
    sync(subjects.value.filter((subject) => subject.id !== id));
}

/** The one-click shortcut: a brand new person, attached straight away. Kind
 * choices beyond person go through a subject's own page instead. */
async function create() {
    const name = query.value.trim();

    if (name === '' || creating.value) {
        return;
    }

    creating.value = true;

    try {
        const response = await fetch('/subjects', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name, kind: 'person' }),
        });

        if (! response.ok) {
            return;
        }

        const { data } = await response.json();
        pick({ value: data.id, label: data.name });
    } finally {
        creating.value = false;
    }
}

function select(item) {
    if (item.create) {
        create();

        return;
    }

    pick(item);
}

const { active, onKeydown: onListKeydown } = useListNavigation(listItems, {
    onSelect: select,
    onDismiss: () => close(),
});
</script>

<template>
    <div ref="root" class="relative">
        <div :class="[CONTROL, 'flex flex-wrap items-center gap-1.5 border-neutral-100 focus-within:border-accent-500']">
            <span
                v-for="subject in subjects"
                :key="subject.id"
                class="inline-flex items-center gap-1 rounded bg-accent-50 py-0.5 pl-2 pr-1 text-meta text-accent-700"
            >
                {{ subject.name }}
                <Button
                    variant="ghost"
                    size="icon"
                    pill
                    class="p-0.5 text-accent-700/70 hover:text-accent-700"
                    :aria-label="`Remove ${subject.name}`"
                    @click="remove(subject.id)"
                >
                    <Icon name="Cancel01Icon" class="size-3" />
                </Button>
            </span>

            <input
                :value="query"
                type="text"
                placeholder="Tag a person, pet, spot or thing"
                class="min-w-40 flex-1 border-none bg-transparent p-0 text-meta text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                autocomplete="off"
                role="combobox"
                :aria-expanded="open"
                aria-autocomplete="list"
                @input="onInput($event.target.value)"
                @focus="search"
                @keydown="onListKeydown"
            >

            <Button
                variant="ghost"
                size="icon"
                pill
                class="shrink-0 p-1"
                aria-label="Done tagging"
                @click="$emit('close')"
            >
                <Icon name="Cancel01Icon" class="size-4" />
            </Button>
        </div>

        <!-- One-tap suggestions from the prose, never auto-attached: naming
        someone in a sentence isn't the same claim as tagging them here. -->
        <div v-if="mentionedOffered.length" class="mt-1.5 flex flex-wrap gap-1.5">
            <Button
                v-for="subject in mentionedOffered"
                :key="subject.id"
                variant="chip"
                size="sm"
                pill
                @click="pick({ value: subject.id, label: subject.name })"
            >
                <Icon name="PlusSignIcon" class="size-3" />
                {{ subject.name }}
            </Button>
        </div>

        <ul
            v-if="open && listItems.length"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
            role="listbox"
        >
            <li v-for="(item, index) in listItems" :key="item.value">
                <button
                    type="button"
                    role="option"
                    :aria-selected="index === active"
                    class="flex w-full items-baseline justify-between gap-3 px-3 py-1.5 text-left text-meta transition-colors"
                    :class="index === active ? 'bg-accent-50 text-accent-700' : 'text-neutral-900 hover:bg-accent-50 hover:text-accent-700'"
                    @mousedown.prevent="select(item)"
                >
                    <span class="min-w-0 truncate">{{ item.label }}</span>
                    <span v-if="item.detail" class="shrink-0 text-caption text-neutral-500">{{ item.detail }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
