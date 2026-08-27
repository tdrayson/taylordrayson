<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import { unitTitle } from '../../lib/units.js';
import { useFormat } from '../../composables/useFormat';
import Duration from '../Timeline/Duration.vue';

const props = defineProps({
    stats: { type: Array, default: () => [] },
    size: { type: String, default: 'md' },
    class: { type: [String, Array, Object], default: '' },
});

const { distanceParts } = useFormat();

// Format BEFORE filtering: a stat carrying raw `distanceM` resolves its
// value/unit through the active unit setting here, others pass through with
// their static value/unit unchanged. Reading distanceUnit's setting inside
// this computed (via distanceParts) is what makes the zero-hide below
// reactive to the mi/km toggle rather than a one-off snapshot.
const formatted = computed(() => props.stats.map((stat) => {
    if (stat.distanceM !== null && stat.distanceM !== undefined) {
        const parts = distanceParts(stat.distanceM, stat.precision ?? 0);
        return { ...stat, value: parts.value, unit: parts.unit, isDistance: true };
    }
    return stat;
}));

// Drop blank stats so callers can pass a sparse list without gaps, and drop
// distance-origin stats whose FORMATTED value rounds to zero in the current
// unit (e.g. a 400m day total renders "0 mi", which is more misleading than
// just hiding the stat). Non-distance stats keep the original blank check.
const resolved = computed(() =>
    formatted.value.filter((stat) => {
        if (stat.isDistance) {
            // number() returns a locale string ("0", "0.0", "1,234.5"); strip
            // thousands separators before the numeric zero comparison.
            return Number(stat.value.replace(/,/g, '')) !== 0;
        }

        return stat.seconds != null || (stat.value !== null && stat.value !== undefined && stat.value !== '');
    }),
);

// Display size per token, so a stat in a narrow panel stays on the same
// scale as one on a stats page rather than being hand-sized.
const SIZES = {
    sm: { value: 'text-name', unit: 'text-sm', gap: 'gap-x-6 gap-y-5' },
    md: { value: 'text-stat', unit: 'text-base', gap: 'gap-x-12 gap-y-6' },
    lg: { value: 'text-stat-lg', unit: 'text-lg', gap: 'gap-x-14 gap-y-8' },
};

const scale = computed(() => SIZES[props.size] ?? SIZES.md);
</script>

<template>
    <dl :class="cn('flex flex-wrap', scale.gap, props.class)">
        <!-- dt must precede its dd per the dl content model, so flex-col-reverse
             puts the value on top while the DOM order stays term-first. `sub`
             rides inside the dt for the same reason: a second dd would be
             reversed to the top of the stack. -->
        <div v-for="(stat, index) in resolved" :key="index" class="flex flex-col-reverse justify-end">
            <dt class="mt-1.5 text-label uppercase text-neutral-500">
                {{ stat.label }}
                <span v-if="stat.sub" class="mt-1 block normal-case tracking-normal text-neutral-400">{{ stat.sub }}</span>
            </dt>
            <dd class="font-display font-extrabold leading-none tracking-tight tnum" :class="scale.value">
                <Duration v-if="stat.seconds != null" :seconds="stat.seconds" />
                <template v-else>
                    {{ stat.value }}<abbr v-if="stat.unit" :title="unitTitle(stat.unit)" class="ml-1 font-semibold text-neutral-500 no-underline" :class="scale.unit">{{ stat.unit }}</abbr>
                </template>
            </dd>
        </div>
    </dl>
</template>
