<script setup>
import { computed, ref } from 'vue';
import { cn } from '../../lib/cn.js';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import { readCookie } from '../../lib/cookies.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { useListNavigation } from '../../lib/editor/listNavigation.js';
import Button from '../Ui/Button.vue';

/**
 * A subject search box with a dropdown and a one-click "create as a person"
 * shortcut. Shared by PhotoPanel's two pickers (tagging a subject, crediting
 * the camera), which differ only in placeholder/hint text and in what the
 * caller does with a pick.
 */
const props = defineProps({
    placeholder: { type: String, required: true },
    hint: { type: String, default: null },
    includeSelf: { type: Boolean, default: true },
});

const emit = defineEmits(['pick', 'cancel']);

const { isOpen: open, root, open: show } = useDismissable();
const query = ref('');
const suggestions = ref([]);
const creating = ref(false);
let searchTimer = null;

async function search() {
    try {
        const response = await fetch(`/lookup/subject?q=${encodeURIComponent(query.value)}&include_self=${props.includeSelf ? 1 : 0}`, {
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
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 200);
}

// The create shortcut only replaces an empty result, not a partial one: a
// name close to an existing subject should still be picked from the list.
const listItems = computed(() => {
    if (suggestions.value.length) {
        return suggestions.value;
    }

    const name = query.value.trim();

    return name === '' ? [] : [{ value: '__create__', label: `Create ${name} as a person`, create: true }];
});

async function createSubject() {
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
        emit('pick', data.id, data.name);
    } finally {
        creating.value = false;
    }
}

function select(item) {
    if (item.create) {
        createSubject();

        return;
    }

    emit('pick', item.value, item.label);
}

const { active, onKeydown: onListKeydown } = useListNavigation(listItems, {
    onSelect: select,
    onDismiss: () => emit('cancel'),
});
</script>

<template>
    <div ref="root" class="relative">
        <input
            :value="query"
            type="text"
            :placeholder="placeholder"
            :class="cn(CONTROL, CONTROL_BORDER, 'text-neutral-900 placeholder:text-neutral-500')"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            aria-autocomplete="list"
            @input="onInput($event.target.value)"
            @focus="search"
            @keydown="onListKeydown"
        >
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
                </button>
            </li>
        </ul>
        <p v-if="hint" class="mt-1 text-caption text-neutral-500">{{ hint }}</p>
        <Button variant="ghost" size="sm" class="mt-1" @click="emit('cancel')">Cancel</Button>
    </div>
</template>
