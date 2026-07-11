<script setup>
import ThemeCards from './ThemeCards.vue';
import SettingToggle from './SettingToggle.vue';
import Modal from '../Ui/Modal.vue';
import { useSettings } from '../../useSettings';
import { useFormat } from '../../composables/useFormat';

const { settingsOpen, closeSettings } = useSettings();
const { distanceUnit, setDistanceUnit, weightUnit, setWeightUnit } = useFormat();

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
    <Modal :open="settingsOpen" title="Settings" @update:open="onOpenChange">
        <div class="space-y-6">
            <section class="space-y-3">
                <h3 class="text-label uppercase tracking-wide text-neutral-500">Appearance</h3>
                <div class="space-y-2">
                    <span class="text-body text-neutral-900">Theme</span>
                    <ThemeCards />
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="text-label uppercase tracking-wide text-neutral-500">Formatting</h3>
                <div class="space-y-3">
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
        </div>
    </Modal>
</template>
