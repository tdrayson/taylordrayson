<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';
import Switch from '../Ui/Switch.vue';
import Icon from '../Ui/Icon.vue';
import SubjectAutocomplete from './SubjectAutocomplete.vue';

/**
 * The signed-in editing panel beside the lightbox image: alt text, caption,
 * tagged subjects with their positions, the camera credit, and the reviewed
 * toggle. Opens on any photograph at any time; review is a filter over
 * unreviewed photos, never a lock on editing.
 *
 * "Taken with" names the camera, so it holds at most one thing. Who pressed
 * the shutter is not recorded: it is always me.
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

// post() and delete() take options in different positions, so one shared
// call cannot serve both verbs.
function toggleReviewed(value) {
    if (value) {
        router.post(`/attachments/${props.photo.id}/review`, { kind: 'subjects' }, { preserveScroll: true });
    } else {
        router.delete(`/attachments/${props.photo.id}/review`, { data: { kind: 'subjects' }, preserveScroll: true });
    }
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

function openSubjectPicker() {
    pickerRole.value = 'subject';
    emit('start-placing');
}

function openCameraPicker() {
    pickerRole.value = 'camera';
}

function closePicker() {
    pickerRole.value = null;
    emit('cancel-placing');
}

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

const subjectPickerHint = computed(() => (
    props.pendingPosition ? 'Point set. Pick who it is.' : 'Click the photo to place a point, or pick someone for the centre.'
));
</script>

<template>
    <div class="flex w-full shrink-0 flex-col gap-4 overflow-y-auto rounded-2xl bg-neutral-0 p-4 sm:max-h-full sm:w-72 sm:self-start">
        <div class="flex flex-col gap-2">
            <label class="text-caption font-medium text-neutral-700" :for="`photo-alt-${photo.id}`">Alt text</label>
            <Input :id="`photo-alt-${photo.id}`" v-model="form.alt" placeholder="Describe the photo" />

            <label class="text-caption font-medium text-neutral-700" :for="`photo-caption-${photo.id}`">Caption</label>
            <Input :id="`photo-caption-${photo.id}`" v-model="form.caption" placeholder="Caption" />

        </div>

        <div class="flex flex-col gap-2">
            <p class="text-caption font-medium text-neutral-700">Tagged</p>

            <ul v-if="subjectTags.length" class="flex flex-wrap gap-1.5">
                <li v-for="tag in subjectTags" :key="tag.subjectId">
                    <span class="inline-flex items-center gap-1 rounded-md bg-accent-50 py-0.5 pl-2 pr-1 text-meta text-accent-700">
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

            <SubjectAutocomplete
                v-if="pickerRole === 'subject'"
                placeholder="Tag a person, pet, spot or thing"
                :hint="subjectPickerHint"
                @pick="place"
                @cancel="closePicker"
            />
            <Button v-else variant="secondary" size="sm" class="self-start" @click="openSubjectPicker">
                <Icon name="CrosshairIcon" class="size-4" />
                Tag someone
            </Button>
        </div>

        <div class="flex flex-col gap-2">
            <p class="text-caption font-medium text-neutral-700">Taken with</p>

            <ul v-if="cameraTags.length" class="flex flex-wrap gap-1.5">
                <li v-for="tag in cameraTags" :key="tag.subjectId">
                    <span class="inline-flex items-center gap-1 rounded-md bg-neutral-25 py-0.5 pl-2 pr-1 text-meta text-neutral-700">
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

            <SubjectAutocomplete
                v-if="pickerRole === 'camera'"
                placeholder="Which camera"
                kind="thing"
                @pick="place"
                @cancel="closePicker"
            />
            <Button v-else-if="! cameraTags.length" variant="secondary" size="sm" class="self-start" @click="openCameraPicker">
                <Icon name="Camera01Icon" class="size-4" />
                Add camera
            </Button>
        </div>

        <label class="flex items-center justify-between gap-4">
            <span class="text-caption font-medium text-neutral-700">Reviewed</span>
            <Switch :model-value="reviewed" @update:model-value="toggleReviewed" />
        </label>

        <!-- Saves the two text fields, so it closes the panel rather than
             sitting between them and the tagging below, which saves itself. -->
        <div class="sticky bottom-0 -mx-4 -mb-4 bg-neutral-0 px-4 pb-4 pt-3">
            <Button variant="primary" size="lg" class="w-full" :disabled="saving" @click="saveDetails">Save</Button>
        </div>
    </div>
</template>
