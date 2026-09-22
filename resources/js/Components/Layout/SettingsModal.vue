<script setup>
import ThemeCards from './ThemeCards.vue';
import SettingToggle from './SettingToggle.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Modal from '../Ui/Modal.vue';
import Switch from '../Ui/Switch.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import InfoTip from '../Ui/InfoTip.vue';
import { useSettings } from '../../useSettings';
import { useFormat } from '../../composables/useFormat';
import { useNumeronym } from '../../composables/useNumeronym';

const { settingsOpen, closeSettings } = useSettings();
const { distanceUnit, setDistanceUnit, weightUnit, setWeightUnit } = useFormat();
const { numeronymMode } = useNumeronym();

// Segmented options for the formatting toggles.
const distanceOptions = [
    { value: 'mi', label: 'mi' },
    { value: 'km', label: 'km' },
];
const weightOptions = [
    { value: 'kg', label: 'kg' },
    { value: 'lbs', label: 'lbs' },
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
                </div>
            </section>

            <section class="space-y-2">
                <Eyebrow as="h3" class="tracking-wide text-neutral-500">Just for fun</Eyebrow>
                <div class="rounded-lg border border-neutral-50 px-4 py-2.5">
                    <div class="flex items-center justify-between gap-4">
                        <span class="flex items-center gap-1.5">
                            <span id="numeronym-mode-label" class="text-base text-neutral-900">Numeronym mode</span>
                            <InfoTip label="About numeronym mode">
                                <p>Shortens every word to its first and last letters with a count between, so accessibility reads a11y.</p>
                                <ExternalLink href="https://en.wikipedia.org/wiki/Numeronym" label="What's a numeronym?" />
                            </InfoTip>
                        </span>
                        <Switch v-model="numeronymMode" aria-labelledby="numeronym-mode-label" />
                    </div>
                </div>
            </section>
        </div>
    </Modal>
</template>
