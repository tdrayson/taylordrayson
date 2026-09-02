<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';
import Switch from '../Ui/Switch.vue';
import Icon from '../Ui/Icon.vue';
import { readCookie } from '../../lib/cookies.js';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { useListNavigation } from '../../lib/editor/listNavigation.js';

/**
 * The signed-in editing panel beside the lightbox image: alt text, caption,
 * tagged subjects with their positions, the camera credit, and the reviewed
 * toggle. Opens on any photograph at any time; review is a filter over
 * unreviewed photos, never a lock on editing.
 */
const props = defineProps({
    photo: { type: Object, required: true }, // { id, alt, caption, tags, reviewed }
    placing: { type: Boolean, default: false },
    pendingPosition: { type: Object, default: null }, // {x, y} | null, set by clicking the photo while placing
});

const emit = defineEmits(['start-placing', 'cancel-placing', 'placed']);

const form = reactive({ alt: '', caption: '' });
const saving = ref(false);

function resetForm() {
    form.alt = props.photo.alt ?? '';
    form.caption = props.photo.caption ?? '';
}

watch(() => props.photo.id, resetForm, { immediate: true });

function saveDetails() {
    saving.value = true;
    router.patch(`/attachments/${props.photo.id}`, { alt: form.alt, caption: form.caption }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}

const subjectTags = computed(() => props.photo.tags.filter((tag) => tag.role === 'subject'));
const cameraTags = computed(() => props.photo.tags.filter((tag) => tag.role === 'camera'));
const reviewed = computed(() => props.photo.reviewed?.subjects === true);

function toggleReviewed(value) {
    const method = value ? 'post' : 'delete';

    router[method](`/attachments/${props.photo.id}/review`, { data: { kind: 'subjects' }, preserveScroll: true });
}

function removeTag(tag) {
    router.delete(`/attachments/${props.photo.id}/subjects`, {
        data: { subject_id: tag.subjectId, role: tag.role },
        preserveScroll: true,
    });
}

// The inline picker: which role it's placing (null when closed), shared by
// "Tag someone" (a point, with the crosshair armed on the photo) and "Taken
// with" (no position, the image is left alone).
const pickerRole = ref(null);
const pickerOpen = computed(() => pickerRole.value !== null);

const { isOpen: suggestionsOpen, root: pickerRoot, open: showSuggestions, close: closeSuggestions } = useDismissable();
const query = ref('');
const suggestions = ref([]);
const creating = ref(false);
let searchTimer = null;

function openSubjectPicker() {
    pickerRole.value = 'subject';
    emit('start-placing');
}

function openCameraPicker() {
    pickerRole.value = 'camera';
}

function closePicker() {
    pickerRole.value = null;
    query.value = '';
    suggestions.value = [];
    closeSuggestions();
    emit('cancel-placing');
}

async function search() {
    try {
        const response = await fetch(`/lookup/subject?q=${encodeURIComponent(query.value)}&include_self=1`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        suggestions.value = response.ok ? (await response.json()).data ?? [] : [];
        showSuggestions();
    } catch {
        suggestions.value = [];
    }
}

function onInput(value) {
    query.value = value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 200);
}

const listItems = computed(() => {
    if (suggestions.value.length) {
        return suggestions.value;
    }

    const name = query.value.trim();

    return name === '' ? [] : [{ value: '__create__', label: `Create ${name} as a person`, create: true }];
});

function place(subjectId, name) {
    const role = pickerRole.value;
    const point = role === 'subject' ? (props.pendingPosition ?? { x: 50, y: 50 }) : null;

    router.post(`/attachments/${props.photo.id}/subjects`, {
        subject_id: subjectId,
        role,
        ...(point ? { x: point.x, y: point.y } : {}),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closePicker();
            emit('placed');
        },
    });
}

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
        place(data.id, data.name);
    } finally {
        creating.value = false;
    }
}

function select(item) {
    if (item.create) {
        createSubject();

        return;
    }

    place(item.value, item.label);
}

const { active, onKeydown: onListKeydown } = useListNavigation(listItems, {
    onSelect: select,
    onDismiss: closePicker,
});
</script>

<template>
    <div class="flex w-full shrink-0 flex-col gap-4 overflow-y-auto rounded-lg bg-neutral-0 p-4 sm:w-72">
        <div class="flex flex-col gap-2">
            <label class="text-caption font-medium text-neutral-700" :for="`photo-alt-${photo.id}`">Alt text</label>
            <Input :id="`photo-alt-${photo.id}`" v-model="form.alt" placeholder="Describe the photo" />

            <label class="text-caption font-medium text-neutral-700" :for="`photo-caption-${photo.id}`">Caption</label>
            <Input :id="`photo-caption-${photo.id}`" v-model="form.caption" placeholder="Caption" />

            <Button variant="primary" size="sm" class="self-start" :disabled="saving" @click="saveDetails">Save</Button>
        </div>

        <div class="flex flex-col gap-2">
            <p class="text-caption font-medium text-neutral-700">Tagged</p>

            <ul v-if="subjectTags.length" class="flex flex-wrap gap-1.5">
                <li v-for="tag in subjectTags" :key="tag.subjectId">
                    <span class="inline-flex items-center gap-1 rounded bg-accent-50 py-0.5 pl-2 pr-1 text-meta text-accent-700">
                        {{ tag.name }}
                        <Button
                            variant="ghost"
                            size="icon"
                            pill
                            class="p-0.5 text-accent-700/70 hover:text-accent-700"
                            :aria-label="`Remove ${tag.name}`"
                            @click="removeTag(tag)"
                        >
                            <Icon name="Cancel01Icon" class="size-3" />
                        </Button>
                    </span>
                </li>
            </ul>

            <div v-if="pickerRole === 'subject'" ref="pickerRoot" class="relative">
                <input
                    :value="query"
                    type="text"
                    placeholder="Tag a person, pet, spot or thing"
                    class="w-full min-h-11 rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2 text-meta text-neutral-900 placeholder:text-neutral-500 focus:border-accent-500 focus:outline-none"
                    autocomplete="off"
                    role="combobox"
                    :aria-expanded="suggestionsOpen"
                    aria-autocomplete="list"
                    @input="onInput($event.target.value)"
                    @focus="search"
                    @keydown="onListKeydown"
                >
                <ul
                    v-if="suggestionsOpen && listItems.length"
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
                <p class="mt-1 text-caption text-neutral-500">
                    {{ pendingPosition ? 'Point set. Pick who it is.' : 'Click the photo to place a point, or pick someone for the centre.' }}
                </p>
                <Button variant="ghost" size="sm" class="mt-1" @click="closePicker">Cancel</Button>
            </div>
            <Button v-else variant="secondary" size="sm" class="self-start" @click="openSubjectPicker">
                <Icon name="CrosshairIcon" class="size-4" />
                Tag someone
            </Button>
        </div>

        <div class="flex flex-col gap-2">
            <p class="text-caption font-medium text-neutral-700">Taken with</p>

            <ul v-if="cameraTags.length" class="flex flex-wrap gap-1.5">
                <li v-for="tag in cameraTags" :key="tag.subjectId">
                    <span class="inline-flex items-center gap-1 rounded bg-neutral-25 py-0.5 pl-2 pr-1 text-meta text-neutral-700">
                        {{ tag.name }}
                        <Button
                            variant="ghost"
                            size="icon"
                            pill
                            class="p-0.5 text-neutral-500 hover:text-neutral-900"
                            :aria-label="`Remove ${tag.name}`"
                            @click="removeTag(tag)"
                        >
                            <Icon name="Cancel01Icon" class="size-3" />
                        </Button>
                    </span>
                </li>
            </ul>

            <div v-if="pickerRole === 'camera'" ref="pickerRoot" class="relative">
                <input
                    :value="query"
                    type="text"
                    placeholder="Who took it"
                    class="w-full min-h-11 rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2 text-meta text-neutral-900 placeholder:text-neutral-500 focus:border-accent-500 focus:outline-none"
                    autocomplete="off"
                    role="combobox"
                    :aria-expanded="suggestionsOpen"
                    aria-autocomplete="list"
                    @input="onInput($event.target.value)"
                    @focus="search"
                    @keydown="onListKeydown"
                >
                <ul
                    v-if="suggestionsOpen && listItems.length"
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
                <Button variant="ghost" size="sm" class="mt-1" @click="closePicker">Cancel</Button>
            </div>
            <Button v-else variant="secondary" size="sm" class="self-start" @click="openCameraPicker">
                <Icon name="Camera01Icon" class="size-4" />
                Add camera credit
            </Button>
        </div>

        <label class="flex items-center justify-between gap-4">
            <span class="text-caption font-medium text-neutral-700">Reviewed</span>
            <Switch :model-value="reviewed" @update:model-value="toggleReviewed" />
        </label>
    </div>
</template>
