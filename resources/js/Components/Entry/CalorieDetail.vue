<script setup>
import { computed } from 'vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import { number, titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const totals = computed(() => props.entry.totals ?? {});
const meals = computed(() => props.entry.meals ?? []);

const MACROS = [
    { key: 'protein', label: 'Protein', kcalPerGram: 4, color: 'var(--color-macro-protein)' },
    { key: 'carbs', label: 'Carbs', kcalPerGram: 4, color: 'var(--color-macro-carbs)' },
    { key: 'fat', label: 'Fat', kcalPerGram: 9, color: 'var(--color-macro-fat)' },
];

// Macro split by calorie contribution (protein/carbs 4 kcal/g, fat 9 kcal/g).
const macros = computed(() => {
    const entries = MACROS.map((macro) => ({
        ...macro,
        grams: totals.value[macro.key] || 0,
        energy: (totals.value[macro.key] || 0) * macro.kcalPerGram,
    })).filter((macro) => macro.grams > 0);

    const energyTotal = entries.reduce((sum, macro) => sum + macro.energy, 0) || 1;

    return entries.map((macro) => ({ ...macro, percent: (macro.energy / energyTotal) * 100 }));
});

// Full per-nutrient breakdown. Calories and macros are always present, so the
// table renders for every day; the micros below are only supplied by some
// sources (e.g. Lose It, not Rovi) and appear when they carry a value.
const nutrition = computed(() => {
    const t = totals.value;

    return [
        { label: 'Calories', value: t.calories ? `${number(t.calories)} kcal` : null },
        { label: 'Protein', value: t.protein ? `${number(t.protein, 1)} g` : null },
        { label: 'Carbs', value: t.carbs ? `${number(t.carbs, 1)} g` : null },
        { label: 'Fat', value: t.fat ? `${number(t.fat, 1)} g` : null },
        { label: 'Saturated fat', value: t.saturated_fat ? `${number(t.saturated_fat, 1)} g` : null },
        { label: 'Sugars', value: t.sugars ? `${number(t.sugars, 1)} g` : null },
        { label: 'Fibre', value: t.fibre ? `${number(t.fibre, 1)} g` : null },
        { label: 'Sodium', value: t.sodium ? `${number(t.sodium)} mg` : null },
    ].filter((row) => row.value);
});

function quantity(item) {
    if (!item.quantity) {
        return item.units || '';
    }

    return `${number(item.quantity, item.quantity % 1 ? 1 : 0)} ${item.units || ''}`.trim();
}
</script>

<template>
    <div class="space-y-10">
        <div v-if="entry.inProgress" class="flex items-center gap-2">
            <span class="relative flex size-2">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-food opacity-75" />
                <span class="relative inline-flex size-2 rounded-full bg-food" />
            </span>
            <span class="text-label uppercase tracking-wide text-food">Still logging today</span>
        </div>

        <div v-if="macros.length">
            <div class="flex h-2.5 overflow-hidden rounded-full">
                <div v-for="macro in macros" :key="macro.key" :style="{ width: `${macro.percent}%`, background: macro.color }" />
            </div>
            <div class="mt-4 flex flex-wrap gap-x-10 gap-y-4">
                <div v-for="macro in macros" :key="macro.key" class="flex items-center gap-2.5">
                    <span class="size-2.5 rounded-full" :style="{ background: macro.color }" />
                    <div>
                        <div class="font-display text-stat leading-none tnum">{{ number(macro.grams) }}<span class="ml-0.5 text-base font-semibold text-neutral-500">g</span></div>
                        <div class="mt-1 text-label uppercase text-neutral-500">{{ macro.label }} · {{ Math.round(macro.percent) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="meals.length">
            <SectionHead title="Meals" />
            <div class="space-y-3">
                <div v-for="meal in meals" :key="meal.meal" class="overflow-hidden rounded-lg border border-neutral-50">
                    <div class="flex items-baseline justify-between bg-neutral-25 px-4 py-2.5">
                        <span class="font-display text-section">{{ titleCase(meal.meal) }}</span>
                        <span class="text-meta font-semibold text-neutral-700 tnum">{{ number(meal.calories) }} kcal</span>
                    </div>
                    <div class="divide-y divide-neutral-50">
                        <div v-for="(item, index) in meal.items" :key="index" class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <div class="min-w-0">
                                <div class="text-meta text-neutral-900">{{ item.name }}</div>
                                <div class="text-caption text-neutral-500">{{ quantity(item) }}</div>
                            </div>
                            <div class="shrink-0 text-meta font-semibold text-neutral-900 tnum">{{ number(item.calories) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="nutrition.length">
            <SectionHead title="Nutrition" />
            <DetailList :rows="nutrition" />
        </div>
    </div>
</template>
