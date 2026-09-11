<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import Modal from '../Ui/Modal.vue';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';
import Icon from '../Ui/Icon.vue';
import Checkbox from '../Ui/Checkbox.vue';
import StyledSelect from '../Search/StyledSelect.vue';
import DynamicTagDateField from './DynamicTagDateField.vue';
import { titleCase } from '../../lib/format.js';
import { isFourDigitYear } from '../../lib/editor/period.js';
import { useDynamicTags } from '../../composables/useDynamicTags';

/** `from`/`to` are a date bound, not free text, wherever a tag declares them. */
function isDateOption(name) {
    return name === 'from' || name === 'to';
}

/**
 * The options form for one dynamic tag, generated entirely from its declared
 * schema: a select per option with choices, a date field for `from`/`to`, a
 * text input for anything else. A 37th tag needs nothing added here, only a
 * schema on the server.
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
    // Where the tag is placed, so the preview shows the href for a link or
    // image source rather than always the display text.
    placement: { type: String, default: 'inline' },
});

const emit = defineEmits(['apply', 'update:open']);

const { previewFor, homeTimezone } = useDynamicTags();

const PERIOD_YEAR = '__year__';
const PERIOD_RANGE = '__range__';

// `period` and its `from`/`to` range are both rendered outside the generic
// loop: together they are the one option the model lets an author reach three
// ways (a named preset, a bare year, or an explicit range), and the range
// wins over the other two whenever it has a bound of its own (Period::from()).
const periodOption = computed(() => props.tag.options.find((option) => option.name === 'period') ?? null);
const rangeOptions = computed(() => props.tag.options.filter((option) => isDateOption(option.name)));
const hasDateRange = computed(() => rangeOptions.value.length > 0);
const fromLabel = computed(() => rangeOptions.value.find((option) => option.name === 'from')?.label ?? 'From');
const toLabel = computed(() => rangeOptions.value.find((option) => option.name === 'to')?.label ?? 'To');
const otherOptions = computed(() => props.tag.options.filter((option) => option.name !== 'period' && ! isDateOption(option.name)));

const values = reactive({});
const periodChoice = ref('');
const periodYear = ref('');
const rangeFrom = ref('');
const rangeTo = ref('');

/** Rebuilds the form from the tag's current options, freshest each time the popup opens. */
function resetForm() {
    for (const option of otherOptions.value) {
        values[option.name] = props.options[option.name] ?? '';
    }

    const from = props.options.from ?? '';
    const to = props.options.to ?? '';

    // Mirrors Period::from(): an explicit bound wins over period, and a lone
    // from or to is a deliberate open-ended range, not an incomplete one.
    if (hasDateRange.value && (from !== '' || to !== '')) {
        periodChoice.value = PERIOD_RANGE;
        rangeFrom.value = from;
        rangeTo.value = to;
        periodYear.value = '';

        return;
    }

    rangeFrom.value = '';
    rangeTo.value = '';

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
// An empty range reads as nothing chosen, so leaving both bounds blank stays blocked
// rather than silently applying as an all-time period.
const rangeInvalid = computed(() => periodChoice.value === PERIOD_RANGE && rangeFrom.value === '' && rangeTo.value === '');
const periodInvalid = computed(() => (periodChoice.value === PERIOD_YEAR && ! yearValid.value) || rangeInvalid.value);

/** The options object this form currently represents, empty values omitted. */
const resolvedOptions = computed(() => {
    const result = {};

    for (const option of otherOptions.value) {
        const value = (values[option.name] ?? '').trim();

        if (value !== '') {
            result[option.name] = value;
        }
    }

    if (periodChoice.value === PERIOD_RANGE) {
        if (rangeFrom.value !== '') {
            result.from = rangeFrom.value;
        }

        if (rangeTo.value !== '') {
            result.to = rangeTo.value;
        }
    } else if (periodOption.value) {
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

const previewText = computed(() => previewFor(props.tag.name, debounced.value, props.placement) ?? props.tag.name);

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
                <label v-if="option.boolean" class="flex items-center gap-2 text-body text-neutral-900">
                    <Checkbox
                        :model-value="values[option.name] === 'on'"
                        @update:model-value="values[option.name] = $event ? 'on' : ''"
                    />
                    {{ option.label }}
                </label>

                <template v-else>
                    <label :for="`dt-${option.name}`" class="mb-1 block text-label uppercase text-neutral-500">{{ option.label }}</label>

                    <StyledSelect
                        v-if="option.choices.length"
                        :id="`dt-${option.name}`"
                        v-model="values[option.name]"
                        :options="option.choices.map((choice) => ({ value: choice, label: titleCase(choice) }))"
                        :placeholder="`Any ${option.label.toLowerCase()}`"
                    />

                    <Input v-else :id="`dt-${option.name}`" v-model="values[option.name]" :placeholder="option.label" />
                </template>
            </div>

            <div v-if="periodOption">
                <label for="dt-period" class="mb-1 block text-label uppercase text-neutral-500">{{ periodOption.label }}</label>

                <StyledSelect
                    id="dt-period"
                    v-model="periodChoice"
                    :options="[
                        ...periodOption.choices.map((choice) => ({ value: choice, label: titleCase(choice) })),
                        { value: PERIOD_YEAR, label: 'Custom year' },
                        ...(hasDateRange ? [{ value: PERIOD_RANGE, label: 'Custom range' }] : []),
                    ]"
                />

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

                <template v-else-if="periodChoice === PERIOD_RANGE">
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <DynamicTagDateField v-model="rangeFrom" :label="fromLabel" />
                        <DynamicTagDateField v-model="rangeTo" :label="toLabel" />
                    </div>
                    <p v-if="rangeInvalid" class="mt-1 text-caption text-red-600">Enter a from or to date.</p>
                    <p v-else-if="homeTimezone" class="mt-1 text-caption text-neutral-500">{{ homeTimezone }} local time</p>
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
