<script setup>
import { computed } from 'vue';
import ThemeCards from './ThemeCards.vue';
import SettingToggle from './SettingToggle.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Modal from '../Ui/Modal.vue';
import Switch from '../Ui/Switch.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import InfoTip from '../Ui/InfoTip.vue';
import Icon from '../Ui/Icon.vue';
import Select from '../Ui/Select.vue';
import { useSettings } from '../../useSettings';
import { useFormat } from '../../composables/useFormat';
import { TEXT_MODES, useTextMode } from '../../composables/useTextMode';
import { useDateFormat } from '../../composables/useDateFormat';

const { settingsOpen, closeSettings, customised, resetSettings } = useSettings();
const { distanceUnit, setDistanceUnit, weightUnit, setWeightUnit, temperatureUnit, setTemperatureUnit, sillyUnits, setSillyUnits } = useFormat();
const { textMode, setTextMode } = useTextMode();
const { timeFormat, setTimeFormat, dateFormat, setDateFormat, dateOptions } = useDateFormat();

const sillyUnitsOn = computed({
    get: () => sillyUnits.value === 'on',
    set: (on) => setSillyUnits(on ? 'on' : 'off'),
});

// What the selected text mode does, for its InfoTip.
const TEXT_MODE_ABOUT = {
    off: 'Rewrites every word on the site for a laugh. Pick a mode to try one.',
    numeronym: 'Shortens every word to its first and last letters with a count between, so accessibility reads a11y.',
    pirate: 'Talks like a pirate. Hello becomes ahoy, and you becomes ye.',
    reversed: 'Writes every word backwards. Good luck reading it.',
    emoji: 'Swaps the words it knows for emoji, so coffee becomes ☕.',
    'pig-latin': 'Moves the start of each word to the end and adds ay, so pig becomes igpay.',
};

// Segmented options for the formatting toggles.
const distanceOptions = [
    { value: 'mi', label: 'mi' },
    { value: 'km', label: 'km' },
];
const weightOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'lbs', label: 'lbs' },
];
const temperatureOptions = [
    { value: 'c', label: '°C' },
    { value: 'f', label: '°F' },
];
const timeOptions = [
    { value: '12h', label: '12h' },
    { value: '24h', label: '24h' },
];

// Modal emits update:open(false) on backdrop click/Esc/close button; funnel
// that through to the settings store rather than owning open-state locally.
function onOpenChange(open) {
    if (!open) {
        closeSettings();
    }
}
</script>

<template>
    <Modal :open="settingsOpen" title="Settings" close-label="Close settings" @update:open="onOpenChange">
        <div class="cursor-default space-y-6 select-none">
            <ThemeCards />

            <section class="space-y-2">
                <Eyebrow as="h3" class="tracking-wide text-neutral-500">Units & formats</Eyebrow>
                <div class="divide-y divide-neutral-50 rounded-lg border border-neutral-50 px-4 *:py-2.5">
                    <SettingToggle
                        :model-value="distanceUnit"
                        :options="distanceOptions"
                        label="Distance"
                        aria-label="Distance unit"
                        @update:model-value="setDistanceUnit"
                    />
                    <SettingToggle
                        :model-value="weightUnit"
                        :options="weightOptions"
                        label="Weight"
                        aria-label="Weight unit"
                        @update:model-value="setWeightUnit"
                    />
                    <SettingToggle
                        :model-value="temperatureUnit"
                        :options="temperatureOptions"
                        label="Temperature"
                        aria-label="Temperature unit"
                        @update:model-value="setTemperatureUnit"
                    />
                    <SettingToggle
                        :model-value="timeFormat"
                        :options="timeOptions"
                        label="Time"
                        aria-label="Time format"
                        @update:model-value="setTimeFormat"
                    />
                    <div class="flex items-center justify-between gap-4">
                        <span id="date-format-label" class="text-base text-neutral-900">Date</span>
                        <Select
                            :model-value="dateFormat"
                            :options="dateOptions"
                            size="sm"
                            aria-labelledby="date-format-label"
                            @update:model-value="setDateFormat"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-2">
                <Eyebrow as="h3" class="tracking-wide text-neutral-500">Just for fun</Eyebrow>
                <div class="divide-y divide-neutral-50 rounded-lg border border-neutral-50 px-4 *:py-2.5">
                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-1.5">
                            <label for="text-mode" class="text-base text-neutral-900">Text mode</label>
                            <InfoTip label="About text mode">
                                <p>{{ TEXT_MODE_ABOUT[textMode] }}</p>
                                <ExternalLink v-if="textMode === 'numeronym'" href="https://en.wikipedia.org/wiki/Numeronym" label="What's a numeronym?" />
                                <ExternalLink v-else-if="textMode === 'pig-latin'" href="https://en.wikipedia.org/wiki/Pig_Latin" label="What's Pig Latin?" />
                            </InfoTip>
                        </span>
                        <Select id="text-mode" size="sm" :model-value="textMode" :options="TEXT_MODES" @update:model-value="setTextMode" />
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-1.5">
                            <span id="silly-units-label" class="text-base text-neutral-900">Silly units</span>
                            <InfoTip label="About silly units">
                                <p>Measures distances in buses and marathons, and weights in bananas and corgis. Hover one for the real number.</p>
                            </InfoTip>
                        </span>
                        <Switch v-model="sillyUnitsOn" aria-labelledby="silly-units-label" />
                    </div>
                </div>
            </section>
        </div>

        <div class="reset-row" :class="{ 'is-shown': customised }" :inert="! customised">
            <div class="-m-1 flex min-h-0 justify-end overflow-hidden p-1">
                <button
                    type="button"
                    class="mt-6 flex items-center gap-1.5 rounded-sm text-sm font-medium text-neutral-500 transition-colors hover:text-accent-500"
                    @click="resetSettings"
                >
                    <Icon name="ArrowReloadHorizontalIcon" class="size-4" />
                    Reset to defaults
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
/* Grid rows animate between 0fr and 1fr, which height:auto cannot. */
.reset-row {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transition: grid-template-rows 200ms ease-out, opacity 200ms ease-out;
}

.reset-row.is-shown {
    grid-template-rows: 1fr;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .reset-row {
        transition: none;
    }
}
</style>
