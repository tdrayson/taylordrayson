<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import Modal from '../Ui/Modal.vue';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';
import Icon from '../Ui/Icon.vue';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';
import { titleCase } from '../../lib/format.js';
import { isFourDigitYear } from '../../lib/editor/period.js';
import { useDynamicTags } from '../../composables/useDynamicTags';

/**
 * The options form for one dynamic tag, generated entirely from its declared
 * schema: a select per option with choices, a text input for one without. A
 * 37th tag needs nothing added here, only a schema on the server.
 *
 * Used both to fill in a freshly picked tag's options before it is inserted,
 * and to edit an existing chip's; the caller decides what "apply" means.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    // { name, label, options: [{ name, label, choices, default }] }
    tag: { type: Object, required: true },
    // The option values to start the form from.
    options: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['apply', 'update:open']);

const { previewFor } = useDynamicTags();

const PERIOD_YEAR = '__year__';

// The `period` option is rendered separately from the generic loop: it is the
// one option that is both a closed set of presets and an open-ended bare year.
const periodOption = computed(() => props.tag.options.find((option) => option.name === 'period') ?? null);
const otherOptions = computed(() => props.tag.options.filter((option) => option.name !== 'period'));

const values = reactive({});
const periodChoice = ref('');
const periodYear = ref('');

/** Rebuilds the form from the tag's current options, freshest each time the popup opens. */
function resetForm() {
    for (const option of otherOptions.value) {
        values[option.name] = props.options[option.name] ?? '';
    }

    const period = props.options.period ?? periodOption.value?.default ?? '';

    if (period !== '' && isFourDigitYear(period) && ! periodOption.value?.choices.includes(period)) {
        periodChoice.value = PERIOD_YEAR;
        periodYear.value = period;
    } else {
        periodChoice.value = period;
        periodYear.value = '';
    }
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
}, { immediate: true });

const yearValid = computed(() => isFourDigitYear(periodYear.value));
const periodInvalid = computed(() => periodChoice.value === PERIOD_YEAR && ! yearValid.value);

/** The options object this form currently represents, empty values omitted. */
const resolvedOptions = computed(() => {
    const result = {};

    for (const option of otherOptions.value) {
        const value = (values[option.name] ?? '').trim();

        if (value !== '') {
            result[option.name] = value;
        }
    }

    if (periodOption.value) {
        if (periodChoice.value === PERIOD_YEAR) {
            if (yearValid.value) {
                result.period = periodYear.value;
            }
        } else if (periodChoice.value !== '') {
            result.period = periodChoice.value;
        }
    }

    return result;
});

// Debounced so typing "2024" into the year field, or a from/to date, does not
// fire a preview request per keystroke. The select-based fields settle
// instantly anyway, since a change event only fires once a choice is made.
const debounced = ref({});
let debounceTimer = null;

watch(resolvedOptions, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { debounced.value = { ...value }; }, 300);
}, { immediate: true, deep: true });

onBeforeUnmount(() => clearTimeout(debounceTimer));

const previewText = computed(() => previewFor(props.tag.name, debounced.value) ?? props.tag.name);

function apply() {
    if (periodInvalid.value) {
        return;
    }

    emit('apply', resolvedOptions.value);
    emit('update:open', false);
}
</script>

<template>
    <Modal
        :open="open"
        :title="tag.label"
        :close-label="`Close ${tag.label} options`"
        @update:open="$emit('update:open', $event)"
    >
        <form class="space-y-4" @submit.prevent="apply">
            <div v-for="option in otherOptions" :key="option.name">
                <label :for="`dt-${option.name}`" class="mb-1 block text-label uppercase text-neutral-500">{{ option.label }}</label>

                <select
                    v-if="option.choices.length"
                    :id="`dt-${option.name}`"
                    v-model="values[option.name]"
                    :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']"
                >
                    <option value="">Any {{ option.label.toLowerCase() }}</option>
                    <option v-for="choice in option.choices" :key="choice" :value="choice">{{ titleCase(choice) }}</option>
                </select>

                <Input v-else :id="`dt-${option.name}`" v-model="values[option.name]" :placeholder="option.label" />
            </div>

            <div v-if="periodOption">
                <label for="dt-period" class="mb-1 block text-label uppercase text-neutral-500">{{ periodOption.label }}</label>

                <select id="dt-period" v-model="periodChoice" :class="[CONTROL, CONTROL_BORDER, 'text-neutral-900']">
                    <option v-for="choice in periodOption.choices" :key="choice" :value="choice">{{ titleCase(choice) }}</option>
                    <option :value="PERIOD_YEAR">Custom year</option>
                </select>

                <template v-if="periodChoice === PERIOD_YEAR">
                    <Input
                        v-model="periodYear"
                        type="text"
                        inputmode="numeric"
                        maxlength="4"
                        placeholder="YYYY"
                        aria-label="Year"
                        :invalid="periodYear !== '' && ! yearValid"
                        class="mt-2"
                    />
                    <p v-if="periodYear !== '' && ! yearValid" class="mt-1 text-caption text-red-600">
                        Enter a four-digit year.
                    </p>
                </template>
            </div>

            <div>
                <p class="mb-1 text-label uppercase text-neutral-500">Preview</p>
                <span class="inline-flex items-center gap-1 rounded bg-neutral-25 px-1 py-0.5 align-baseline font-medium text-neutral-900">
                    <Icon name="ChartColumnIcon" class="size-3.5 shrink-0" />{{ previewText }}
                </span>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="secondary" @click="$emit('update:open', false)">Cancel</Button>
                <Button type="submit" variant="primary" :disabled="periodInvalid">Apply</Button>
            </div>
        </form>
    </Modal>
</template>
