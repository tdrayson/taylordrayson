<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Icon from '../Ui/Icon.vue';
import SubjectPicker from './SubjectPicker.vue';

/**
 * Grouped subject lines for an entry footer, one per phrase ("With Clare,
 * Bear", "At The Harrow"), each subject linked with a small avatar. A phrase
 * with nothing on it renders no line at all, and a Thing produces no phrase
 * at all: SubjectKind::phrase() returns null for one deliberately, since an
 * entry can't tell a car driven from one merely parked in the background.
 */
const props = defineProps({
    // list<{phrase, subjects: list<{id, name, url, image}>}>
    lines: { type: Array, default: () => [] },
    // list<{id, name}>, this entry's own direct tags, for the picker to edit.
    direct: { type: Array, default: () => [] },
    type: { type: String, required: true },
    entryId: { type: [Number, String], required: true },
    signedIn: { type: Boolean, default: false },
});

const editing = ref(false);
</script>

<template>
    <div v-if="lines.length || signedIn" class="flex flex-wrap items-start gap-x-4 gap-y-1.5">
        <p v-for="line in lines" :key="line.phrase" class="text-caption text-neutral-500">
            {{ line.phrase }}
            <template v-for="(subject, index) in line.subjects" :key="subject.id">
                <Link
                    :href="subject.url"
                    class="inline-flex items-center gap-1 align-middle font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                >
                    <span class="size-4 shrink-0 overflow-hidden rounded-full bg-neutral-25">
                        <img v-if="subject.image" :src="subject.image" alt="" class="size-full object-cover">
                    </span>
                    {{ subject.name }}
                </Link><span v-if="index < line.subjects.length - 1">, </span>
            </template>
        </p>

        <Button
            v-if="signedIn && ! editing"
            variant="ghost"
            size="icon"
            pill
            aria-label="Tag a subject"
            @click="editing = true"
        >
            <Icon name="PlusSignIcon" class="size-4" />
        </Button>

        <SubjectPicker
            v-if="editing"
            :model-value="direct"
            :type="type"
            :entry-id="entryId"
            @close="editing = false"
        />
    </div>
</template>
