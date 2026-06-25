<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Home01Icon, Calendar03Icon, ChartColumnIcon, MapsLocation01Icon, UserIcon,
    FireIcon, Moon02Icon, WorkoutRunIcon, Location01Icon, ArrowLeft01Icon, ArrowRight01Icon, SparklesIcon,
} from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Components/Ui/Icon.vue';
import Button from '../Components/Ui/Button.vue';
import Pill from '../Components/Ui/Pill.vue';
import Input from '../Components/Ui/Input.vue';
import Textarea from '../Components/Ui/Textarea.vue';
import Checkbox from '../Components/Ui/Checkbox.vue';
import Switch from '../Components/Ui/Switch.vue';
import Card from '../Components/Ui/Card.vue';
import Alert from '../Components/Ui/Alert.vue';
import Accordion from '../Components/Ui/Accordion.vue';
import Tooltip from '../Components/Ui/Tooltip.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import DetailList from '../Components/Ui/DetailList.vue';
import ExternalLink from '../Components/Ui/ExternalLink.vue';
import InlineBadge from '../Components/Ui/InlineBadge.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import BarList from '../Components/Stats/BarList.vue';
import Breadcrumb from '../Components/Layout/Breadcrumb.vue';

defineOptions({ inheritAttrs: false });

// ---- live demo state -------------------------------------------------------
const text = ref('');
const note = ref('');
const checked = ref(true);
const toggled = ref(true);
const page = ref(2);

// ---- styles data -----------------------------------------------------------
const surfaces = [
    { name: 'canvas', hex: '#ffffff', role: 'Default page floor.', border: true },
    { name: 'surface', hex: '#f7f7f7', role: 'Hover, tracks, cells.' },
];
const ink = [
    { name: 'ink', hex: '#222222', role: 'Headings, primary body.' },
    { name: 'ink-2', hex: '#3f3f3f', role: 'Meta, secondary text.' },
    { name: 'ink-3', hex: '#6a6a6a', role: 'Labels, sub-text.' },
];
const accent = [
    { name: 'accent', hex: '#3858e9', role: 'The single brand voltage.' },
    { name: 'accent-active', hex: '#213fd4', role: 'Pressed state.' },
    { name: 'accent-soft', hex: '#c7d1ff', role: 'Disabled / tint.' },
];
const lines = [
    { name: 'line', hex: '#dddddd', role: '1px dividers.' },
    { name: 'line-2', hex: '#ebebeb', role: 'Lighter dividers.' },
];
const heat = [
    { name: 'heat-1', hex: '#e3e8ff' }, { name: 'heat-2', hex: '#9fb1ff' },
    { name: 'heat-3', hex: '#6c84f2' }, { name: 'heat-4', hex: '#3858e9' },
];
const dataTypes = [
    { name: 'activity', hsl: '152 55% 40%' }, { name: 'sleep', hsl: '245 50% 58%' },
    { name: 'food', hsl: '25 90% 52%' }, { name: 'media', hsl: '340 60% 52%' },
    { name: 'event', hsl: '270 55% 52%' }, { name: 'appearance', hsl: '320 55% 50%' },
    { name: 'podcast', hsl: '210 65% 48%' }, { name: 'flight', hsl: '200 75% 50%' },
    { name: 'checkin', hsl: '170 55% 40%' }, { name: 'fuel', hsl: '45 85% 48%' },
    { name: 'project', hsl: '185 60% 42%' }, { name: 'article', hsl: '215 18% 48%' },
    { name: 'note', hsl: '36 40% 50%' },
];
const typeScale = [
    { cls: 'text-display-xl', display: true, spec: 'display-xl · clamp 58–84 / 800', sample: '2026' },
    { cls: 'text-display', display: true, spec: 'display · 44 / 800', sample: 'Sunday 21 June' },
    { cls: 'text-stat', display: true, spec: 'stat · 31 / 800', sample: '11,240' },
    { cls: 'text-name', display: true, spec: 'name · 24 / 800', sample: 'Taylor Drayson' },
    { cls: 'text-item-title', display: true, spec: 'item-title · 23 / 700', sample: 'Morning Run' },
    { cls: 'text-section', display: true, spec: 'section · 18 / 700', sample: 'Movement' },
    { cls: 'text-body', display: false, spec: 'body · 15 / 400 · Inter', sample: 'I build stuff and track everything.' },
    { cls: 'text-nav', display: false, spec: 'nav · 14.5 / 500', sample: 'Timeline · Calendar · Stats' },
    { cls: 'text-meta', display: false, spec: 'meta · 14 / 400', sample: '22:45 – 06:30 · 86% efficiency' },
    { cls: 'text-caption', display: false, spec: 'caption · 13 / 400', sample: 'Cottage rentals · Mar 12 – 18' },
    { cls: 'text-label', display: false, upper: true, spec: 'label · 11 / 600 / .07em', sample: 'Calories' },
    { cls: 'text-eyebrow', display: false, upper: true, spec: 'eyebrow · 10.5 / 700 / .1em', sample: 'Streak' },
];
const radii = [
    { cls: 'rounded-sm', label: 'sm · 8px' }, { cls: 'rounded-md', label: 'md · 11px' },
    { cls: 'rounded-lg', label: 'lg · 14px' }, { cls: 'rounded-full', label: 'full' },
];
const spacingScale = [
    { cls: 'w-1', label: '1 · 4px' }, { cls: 'w-2', label: '2 · 8px' }, { cls: 'w-3', label: '3 · 12px' },
    { cls: 'w-4', label: '4 · 16px' }, { cls: 'w-6', label: '6 · 24px' }, { cls: 'w-8', label: '8 · 32px' },
    { cls: 'w-12', label: '12 · 48px' }, { cls: 'w-16', label: '16 · 64px' },
];
const iconSet = [Home01Icon, Calendar03Icon, ChartColumnIcon, MapsLocation01Icon, UserIcon, WorkoutRunIcon, Moon02Icon, Location01Icon, FireIcon, SparklesIcon];

// ---- component demo data ---------------------------------------------------
const buttonVariants = ['primary', 'secondary', 'ghost', 'chip', 'destructive', 'link'];
const stats = [
    { value: '7h 12m', label: 'Slept' },
    { value: '5.42', unit: 'km', label: 'Run' },
    { value: '11,240', label: 'Steps' },
    { value: '2,140', label: 'Calories' },
];
const bars = [
    { label: 'Run', width: 58, value: '58%' },
    { label: 'Walk', width: 16, value: '16%', accent: true },
    { label: 'Padel', width: 14, value: '14%' },
    { label: 'Tennis', width: 12, value: '12%' },
];
const detailRows = [
    { label: 'Distance', value: '5.42 km' },
    { label: 'Duration', value: '39 min' },
    { label: 'Avg pace', value: '7:12 / km' },
    { label: 'Surface', value: 'Road' },
];
const crumbs = [{ label: '2026', href: '#' }, { label: 'June', href: '#' }, { label: '22', href: '#' }, { label: 'Morning Run' }];

// ---- patterns data ---------------------------------------------------------
const navItems = [
    { icon: Home01Icon, label: 'Timeline', active: false },
    { icon: Calendar03Icon, label: 'Calendar', active: true },
    { icon: ChartColumnIcon, label: 'Stats', active: false },
    { icon: MapsLocation01Icon, label: 'Map', active: false },
    { icon: UserIcon, label: 'About', active: false },
];
const feed = [
    { icon: Moon02Icon, type: 'Sleep', date: '06:30', title: 'Slept 7h 12m', meta: '22:45 – 06:30 · 86% efficiency' },
    { icon: WorkoutRunIcon, type: 'Activity · Run', date: '07:30', title: 'Morning Run', meta: '5.42 km · 39 min · new PB', pb: true },
    { icon: Location01Icon, type: 'Place', date: '09:30', title: 'Example Coffee Co.', meta: 'Coffee Shop · 12th visit' },
];
const places = [
    { r: '01', n: 'David Lloyd', s: 'tennis · Croydon', v: '86' },
    { r: '02', n: 'Aerodrome Hotel', s: 'BNI · Croydon', v: '44' },
    { r: '03', n: 'Cineworld', s: 'cinema · Croydon', v: '38' },
];
const heatCells = Array.from({ length: 119 }, (_, i) => {
    const v = (Math.sin((i + 1) * 43.13) * 4313.13) % 1;
    const a = v < 0 ? v + 1 : v;
    return a < 0.18 ? 0 : a < 0.42 ? 1 : a < 0.68 ? 2 : a < 0.88 ? 3 : 4;
});
function heatColor(level) {
    return ['var(--color-neutral-25)', 'var(--color-heat-1)', 'var(--color-heat-2)', 'var(--color-heat-3)', 'var(--color-heat-4)'][level];
}

const toc = [
    { group: 'Styles', items: [['colour', 'Colour'], ['typography', 'Typography'], ['spacing', 'Spacing & radius'], ['elevation', 'Elevation'], ['icons', 'Icons']] },
    { group: 'Components', items: [['button', 'Button'], ['tag', 'Tag'], ['input', 'Text input'], ['textarea', 'Textarea'], ['checkbox', 'Checkbox'], ['switch', 'Switch'], ['card', 'Card'], ['alert', 'Alert'], ['accordion', 'Accordion'], ['tooltip', 'Tooltip'], ['pagination', 'Pagination'], ['summary', 'Summary list'], ['link', 'Link'], ['badge', 'Inline badge'], ['statgrid', 'Stat grid'], ['barlist', 'Bar list']] },
    { group: 'Patterns', items: [['nav', 'Sidebar nav'], ['breadcrumb', 'Breadcrumb'], ['feed', 'Timeline feed'], ['places', 'Ranked list'], ['heatmap', 'Year heatmap']] },
];
</script>

<template>
    <Head title="Design system" />

    <div class="mx-auto max-w-6xl px-6 py-12 sm:px-10 lg:flex lg:gap-12 lg:py-16">
        <!-- sticky nav -->
        <aside class="hidden shrink-0 lg:block lg:w-52">
            <div class="sticky top-16">
                <div class="text-eyebrow uppercase text-neutral-500">Design system</div>
                <nav class="mt-4 space-y-5">
                    <div v-for="block in toc" :key="block.group">
                        <div class="text-label uppercase text-neutral-500">{{ block.group }}</div>
                        <ul class="mt-2 space-y-1">
                            <li v-for="[id, label] in block.items" :key="id">
                                <a :href="`#${id}`" class="block text-meta text-neutral-700 transition-colors hover:text-accent-500">{{ label }}</a>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="mb-14">
                <h1 class="font-display text-display">Design system</h1>
                <p class="mt-4 max-w-2xl text-body text-neutral-700">
                    A flat, borderless lifelog. Pure white canvas, near-black ink, and a single voltage of
                    <span class="font-semibold text-accent-500">Blueberry (#3858e9)</span>. Bricolage Grotesque carries the
                    display weights; Inter runs the body. Every example below renders the real component.
                </p>
            </header>

            <!-- ============ STYLES ============ -->
            <h2 class="ds-h2">Styles</h2>

            <section id="colour" class="ds-section">
                <h3 class="ds-h3">Colour</h3>
                <p class="ds-lead">Canvas, ink, hairlines and one accent. Data types each carry a fixed hue used only for their icon and eyebrow.</p>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div v-for="c in [...surfaces, ...ink, ...accent, ...lines]" :key="c.name" class="overflow-hidden rounded-lg shadow-card">
                        <div class="h-16" :class="c.border ? 'border-b border-neutral-100' : ''" :style="{ background: c.hex }" />
                        <div class="p-3">
                            <div class="text-caption font-semibold">{{ c.name }}</div>
                            <div class="text-xs text-neutral-500 tnum">{{ c.hex }}</div>
                            <div class="mt-1 text-xs text-neutral-500">{{ c.role }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex items-center gap-2 text-xs text-neutral-500">
                    Quieter
                    <i class="inline-block size-4 rounded" style="background: var(--color-neutral-25)" />
                    <i v-for="c in heat" :key="c.name" class="inline-block size-4 rounded" :style="{ background: c.hex }" />
                    Busier · heatmap ramp
                </div>
                <div class="mt-6 grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                    <div v-for="t in dataTypes" :key="t.name" class="flex items-center gap-3 py-1">
                        <span class="size-4 flex-none rounded" :style="{ background: `hsl(${t.hsl})` }" />
                        <span class="text-caption capitalize">{{ t.name }}</span>
                    </div>
                </div>
            </section>

            <section id="typography" class="ds-section">
                <h3 class="ds-h3">Typography</h3>
                <p class="ds-lead">Each row renders its own <code class="text-accent-500">text-*</code> token, the living type scale. Numbers use tabular figures everywhere.</p>
                <div class="mt-5 flex flex-col">
                    <div v-for="t in typeScale" :key="t.cls" class="flex flex-col gap-1 border-b border-neutral-50 py-4 sm:flex-row sm:items-baseline sm:gap-8">
                        <div class="w-52 flex-none">
                            <div class="text-caption font-semibold">{{ t.cls }}</div>
                            <div class="text-xs text-neutral-500">{{ t.spec }}</div>
                        </div>
                        <div :class="[t.cls, t.display ? 'font-display' : '', t.upper ? 'uppercase' : '']">{{ t.sample }}</div>
                    </div>
                </div>
            </section>

            <section id="spacing" class="ds-section">
                <h3 class="ds-h3">Spacing &amp; radius</h3>
                <div class="mt-5 flex flex-col gap-2">
                    <div v-for="s in spacingScale" :key="s.cls" class="flex items-center gap-3">
                        <div class="h-3 bg-accent-500" :class="s.cls" />
                        <span class="text-xs text-neutral-500 tnum">{{ s.label }}</span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-4">
                    <div v-for="r in radii" :key="r.cls" class="flex size-20 items-center justify-center bg-neutral-25 text-center text-xs text-neutral-500" :class="r.cls">{{ r.label }}</div>
                </div>
            </section>

            <section id="elevation" class="ds-section">
                <h3 class="ds-h3">Elevation</h3>
                <p class="ds-lead">One tier. Depth comes from 14px corner clipping, not borders. <code class="text-accent-500">shadow-card</code> is the only float.</p>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-neutral-0 p-5"><div class="text-caption font-semibold">Flat</div><p class="mt-1 text-xs text-neutral-500">95% of surfaces.</p></div>
                    <div class="rounded-lg bg-neutral-0 p-5 shadow-card"><div class="text-caption font-semibold">shadow-card</div><p class="mt-1 text-xs text-neutral-500">Hover-float on cards.</p></div>
                </div>
            </section>

            <section id="icons" class="ds-section">
                <h3 class="ds-h3">Icons</h3>
                <p class="ds-lead">Hugeicons Pro, stroke-rounded, always via the <code class="text-accent-500">Icon</code> wrapper.</p>
                <div class="ds-eg mt-5 flex flex-wrap gap-5 text-neutral-700">
                    <Icon v-for="(ic, i) in iconSet" :key="i" :icon="ic" class="size-6" />
                </div>
            </section>

            <!-- ============ COMPONENTS ============ -->
            <h2 class="ds-h2 mt-20">Components</h2>

            <section id="button" class="ds-section">
                <h3 class="ds-h3">Button</h3>
                <p class="ds-lead">One component, six variants × four sizes. Use <b>primary</b> for the main action on a view, <b>chip</b> for inline media toggles, <b>ghost</b> for low-emphasis actions. A <code class="text-accent-500">href</code> renders an Inertia link.</p>
                <div class="ds-eg mt-5 space-y-6">
                    <div>
                        <div class="mb-2.5 text-label uppercase text-neutral-500">Variants</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button v-for="v in buttonVariants" :key="v" :variant="v">{{ v }}</Button>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2.5 text-label uppercase text-neutral-500">Sizes</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button variant="primary" size="sm">Small</Button>
                            <Button variant="primary" size="md">Medium</Button>
                            <Button variant="primary" size="lg">Large</Button>
                            <Button variant="primary" size="icon"><Icon :icon="SparklesIcon" class="size-4" /></Button>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2.5 text-label uppercase text-neutral-500">Modifiers &amp; states</div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button variant="primary" pill>Pill (rounded-full)</Button>
                            <Button variant="primary" disabled>Disabled</Button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="tag" class="ds-section">
                <h3 class="ds-h3">Tag</h3>
                <p class="ds-lead">Compact status / metadata label (our <code class="text-accent-500">Pill</code>). Use sparingly, one or two per row.</p>
                <div class="ds-eg mt-5 flex flex-wrap items-center gap-2">
                    <Pill label="Default" />
                    <Pill label="Active" variant="accent" />
                    <Pill label="Outline" variant="outline" />
                </div>
            </section>

            <section id="input" class="ds-section">
                <h3 class="ds-h3">Text input</h3>
                <p class="ds-lead">Single-neutral-100 entry. Pair with a visible label. Use the <code class="text-accent-500">invalid</code> state for validation errors.</p>
                <div class="ds-eg mt-5 max-w-sm space-y-3">
                    <Input v-model="text" placeholder="Search entries…" />
                    <Input model-value="not-an-email" invalid />
                    <Input model-value="Locked" disabled />
                </div>
            </section>

            <section id="textarea" class="ds-section">
                <h3 class="ds-h3">Textarea</h3>
                <p class="ds-lead">Multi-neutral-100 entry for short free text. For rich content use the Editor.js block editor instead.</p>
                <div class="ds-eg mt-5 max-w-sm">
                    <Textarea v-model="note" placeholder="A quick note…" :rows="3" />
                </div>
            </section>

            <section id="checkbox" class="ds-section">
                <h3 class="ds-h3">Checkbox</h3>
                <p class="ds-lead">A binary choice in a set. Always sits beside a clickable label.</p>
                <div class="ds-eg mt-5 space-y-3">
                    <label class="flex cursor-pointer items-center gap-3 text-meta"><Checkbox v-model="checked" /> Include drafts</label>
                    <label class="flex items-center gap-3 text-meta text-neutral-500"><Checkbox :model-value="false" disabled /> Unavailable</label>
                </div>
            </section>

            <section id="switch" class="ds-section">
                <h3 class="ds-h3">Switch</h3>
                <p class="ds-lead">Toggles a setting on or off with an immediate effect (no save button).</p>
                <div class="ds-eg mt-5 flex items-center gap-3 text-meta">
                    <Switch v-model="toggled" /> {{ toggled ? 'Public' : 'Private' }}
                </div>
            </section>

            <section id="card" class="ds-section">
                <h3 class="ds-h3">Card</h3>
                <p class="ds-lead">A grouped surface. <b>flat</b> for inline panels, <b>elevated</b> for hover-float, <b>outline</b> when on a tinted background.</p>
                <div class="ds-eg mt-5 grid gap-4 sm:grid-cols-3">
                    <Card variant="flat"><div class="text-caption font-semibold">Flat</div><p class="mt-1 text-xs text-neutral-500">Surface fill.</p></Card>
                    <Card variant="elevated"><div class="text-caption font-semibold">Elevated</div><p class="mt-1 text-xs text-neutral-500">shadow-card.</p></Card>
                    <Card variant="outline"><div class="text-caption font-semibold">Outline</div><p class="mt-1 text-xs text-neutral-500">Hairline border.</p></Card>
                </div>
            </section>

            <section id="alert" class="ds-section">
                <h3 class="ds-h3">Alert</h3>
                <p class="ds-lead">Inline feedback. Reserve <b>danger</b> for errors and <b>warning</b> for things that need attention before continuing.</p>
                <div class="ds-eg mt-5 space-y-3">
                    <Alert variant="info" title="Heads up">Imports run on a queue, conversions may take a moment.</Alert>
                    <Alert variant="success">Saved. Your changes are live.</Alert>
                    <Alert variant="warning">This appearance has no cover yet.</Alert>
                    <Alert variant="danger" title="Something went wrong">We couldn't reach YouTube.</Alert>
                </div>
            </section>

            <section id="accordion" class="ds-section">
                <h3 class="ds-h3">Accordion</h3>
                <p class="ds-lead">Hides secondary detail behind a toggle. Good for show notes and long transcripts.</p>
                <div class="ds-eg mt-5 max-w-lg">
                    <Accordion title="Show notes">
                        <p class="text-body text-neutral-700">Collapsible content lives here, the panel expands and collapses in place.</p>
                    </Accordion>
                </div>
            </section>

            <section id="tooltip" class="ds-section">
                <h3 class="ds-h3">Tooltip</h3>
                <p class="ds-lead">A short, on-hover hint. Never put essential information here alone.</p>
                <div class="ds-eg mt-5">
                    <Tooltip label="Tabular figures keep numbers aligned">
                        <span class="font-display text-stat tnum">11,240</span>
                    </Tooltip>
                </div>
            </section>

            <section id="pagination" class="ds-section">
                <h3 class="ds-h3">Pagination</h3>
                <p class="ds-lead">Moves through paged feeds. Works with URLs or a <code class="text-accent-500">navigate</code> event.</p>
                <div class="ds-eg mt-5">
                    <Pagination :current-page="page" :last-page="5" @navigate="page = $event" />
                </div>
            </section>

            <section id="summary" class="ds-section">
                <h3 class="ds-h3">Summary list</h3>
                <p class="ds-lead">Key/value rows for entry detail facts (our <code class="text-accent-500">DetailList</code>). Blank values are dropped.</p>
                <div class="ds-eg mt-5 max-w-md">
                    <DetailList :rows="detailRows" />
                </div>
            </section>

            <section id="link" class="ds-section">
                <h3 class="ds-h3">Link</h3>
                <p class="ds-lead">External links carry an arrow and open in a new tab; in-app links use the accent on hover.</p>
                <div class="ds-eg mt-5 flex flex-wrap items-center gap-6">
                    <ExternalLink href="https://thisweekwith.co.uk" label="View show" />
                    <Button href="#" variant="link">In-app link</Button>
                </div>
            </section>

            <section id="badge" class="ds-section">
                <h3 class="ds-h3">Inline badge</h3>
                <p class="ds-lead">A small avatar/logo that sits on the text baseline inside running copy.</p>
                <div class="ds-eg mt-5 text-body text-neutral-700">
                    Built with <InlineBadge :icon="SparklesIcon" /> and a square <InlineBadge :icon="WorkoutRunIcon" circle /> circle variant.
                </div>
            </section>

            <section id="statgrid" class="ds-section">
                <h3 class="ds-h3">Stat grid</h3>
                <p class="ds-lead">A row of headline numbers. <code class="text-accent-500">size="lg"</code> for hero stats on year/month views.</p>
                <div class="ds-eg mt-5 space-y-6">
                    <StatGrid :stats="stats" />
                    <StatGrid :stats="stats" size="lg" />
                </div>
            </section>

            <section id="barlist" class="ds-section">
                <h3 class="ds-h3">Bar list</h3>
                <p class="ds-lead">Compares parts of a whole, e.g. a share of sessions. The accent bar highlights one row.</p>
                <div class="ds-eg mt-5 max-w-md">
                    <BarList :bars="bars" />
                </div>
            </section>

            <!-- ============ PATTERNS ============ -->
            <h2 class="ds-h2 mt-20">Patterns</h2>

            <section id="nav" class="ds-section">
                <h3 class="ds-h3">Sidebar nav</h3>
                <p class="ds-lead">Primary navigation with the active item filled and its icon in accent.</p>
                <nav class="ds-eg mt-5 max-w-60">
                    <a v-for="n in navItems" :key="n.label" href="#" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium" :class="n.active ? 'bg-neutral-25 font-semibold text-neutral-900' : 'text-neutral-700 hover:bg-neutral-25'">
                        <Icon :icon="n.icon" class="size-5 flex-none" :class="n.active ? 'text-accent-500' : 'text-neutral-500'" />
                        {{ n.label }}
                    </a>
                </nav>
            </section>

            <section id="breadcrumb" class="ds-section">
                <h3 class="ds-h3">Breadcrumb</h3>
                <p class="ds-lead">Date-led trail back up the hierarchy. On mobile it leads with the ancestors and drops the current title.</p>
                <div class="ds-eg mt-5"><Breadcrumb :items="crumbs" /></div>
            </section>

            <section id="feed" class="ds-section">
                <h3 class="ds-h3">Timeline feed</h3>
                <p class="ds-lead">The core list. Each entry: type-coloured icon on the spine, eyebrow, title, and meta. Personal bests go accent.</p>
                <div class="ds-eg mt-5">
                    <div class="ds-feed flex flex-col gap-9 pl-14">
                        <div v-for="item in feed" :key="item.title" class="relative">
                            <span class="absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 text-neutral-700">
                                <Icon :icon="item.icon" class="size-5" />
                            </span>
                            <span class="float-right text-xs text-neutral-500 tnum">{{ item.date }}</span>
                            <div class="text-label uppercase text-neutral-500">{{ item.type }}</div>
                            <div class="mt-1 font-display text-item-title">{{ item.title }}</div>
                            <div class="mt-2 text-meta" :class="item.pb ? 'font-semibold text-accent-500' : 'text-neutral-700'">{{ item.meta }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="places" class="ds-section">
                <h3 class="ds-h3">Ranked list</h3>
                <p class="ds-lead">Numbered rows with a trailing figure, e.g. most-visited places or top tracks.</p>
                <div class="ds-eg mt-5 max-w-md">
                    <div class="flex flex-col">
                        <div v-for="p in places" :key="p.r" class="flex items-center gap-3 border-b border-neutral-50 py-3 last:border-0">
                            <span class="w-6 text-xs text-neutral-500 tnum">{{ p.r }}</span>
                            <div class="flex-1"><div class="text-meta font-semibold">{{ p.n }}</div><div class="text-xs text-neutral-500">{{ p.s }}</div></div>
                            <span class="font-display text-base font-bold tnum">{{ p.v }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="heatmap" class="ds-section">
                <h3 class="ds-h3">Year heatmap</h3>
                <p class="ds-lead">Density grid for a year of activity, using the four-step heat ramp.</p>
                <div class="ds-eg mt-5">
                    <div class="grid grid-flow-col grid-rows-7 auto-cols-min gap-0.5">
                        <span v-for="(lvl, i) in heatCells" :key="i" class="size-3 rounded" :style="{ background: heatColor(lvl) }" />
                    </div>
                </div>
            </section>

            <footer class="mt-20 border-t border-neutral-50 pt-5 text-xs text-neutral-500">
                Living design reference · Inter + Bricolage Grotesque · single Blueberry accent · every component example renders the real component.
            </footer>
        </main>
    </div>
</template>

<style scoped>
.ds-h2 {
    font-family: var(--font-display);
    font-size: 1.875rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--color-neutral-900);
}

.ds-section {
    scroll-margin-top: 2rem;
    padding-top: 2.5rem;
}

.ds-h3 {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 700;
}

.ds-lead {
    margin-top: 0.5rem;
    max-width: 42rem;
    color: var(--color-neutral-500);
    font-size: var(--text-meta);
    line-height: 1.5;
}

.ds-eg {
    border: 1px solid var(--color-neutral-50);
    border-radius: 14px;
    padding: 1.5rem;
}

.ds-feed {
    position: relative;
}

.ds-feed::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 14px;
    bottom: 10px;
    width: 2px;
    background: var(--color-neutral-50);
    border-radius: 2px;
}
</style>
