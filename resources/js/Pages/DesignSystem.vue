<script setup>
import { Head } from '@inertiajs/vue3';
import {
    Home01Icon,
    Calendar03Icon,
    ChartColumnIcon,
    MapsLocation01Icon,
    UserIcon,
    FireIcon,
    Moon02Icon,
    WorkoutRunIcon,
    Location01Icon,
    ArrowLeft01Icon,
    ArrowRight01Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Components/Icon.vue';

defineOptions({ inheritAttrs: false });

const surfaces = [
    { name: 'canvas', hex: '#ffffff', role: 'Default page floor — every view.', border: true },
    { name: 'surface', hex: '#f7f7f7', role: 'Sidebar hover, zoom track, calendar cells, bar tracks.' },
];

const ink = [
    { name: 'ink', hex: '#222222', role: 'Display headings, primary body, active nav.' },
    { name: 'ink-2', hex: '#3f3f3f', role: 'Item meta, secondary running text.' },
    { name: 'ink-3', hex: '#6a6a6a', role: 'Labels, sub-text, eyebrows, inactive nav.' },
];

const lines = [
    { name: 'line', hex: '#dddddd', role: 'Default 1px divider, breadcrumb separators.' },
    { name: 'line-2', hex: '#ebebeb', role: 'Lighter divider — feed spine, row borders.' },
];

const accent = [
    { name: 'accent', hex: '#3858e9', role: 'The single brand voltage — PBs, active icons, links, charts.' },
    { name: 'accent-active', hex: '#213fd4', role: 'Pressed state.' },
    { name: 'accent-soft', hex: '#c7d1ff', role: 'Disabled / tint.' },
];

const heat = [
    { name: 'heat-1', hex: '#e3e8ff' },
    { name: 'heat-2', hex: '#9fb1ff' },
    { name: 'heat-3', hex: '#6c84f2' },
    { name: 'heat-4', hex: '#3858e9' },
];

const dataTypes = [
    { name: 'activity', hsl: '152 55% 40%' },
    { name: 'sleep', hsl: '245 50% 58%' },
    { name: 'food', hsl: '25 90% 52%' },
    { name: 'media', hsl: '340 60% 52%' },
    { name: 'event', hsl: '270 55% 52%' },
    { name: 'appearance', hsl: '320 55% 50%' },
    { name: 'podcast', hsl: '210 65% 48%' },
    { name: 'flight', hsl: '200 75% 50%' },
    { name: 'checkin', hsl: '170 55% 40%' },
    { name: 'fuel', hsl: '45 85% 48%' },
    { name: 'project', hsl: '185 60% 42%' },
    { name: 'article', hsl: '215 18% 48%' },
    { name: 'note', hsl: '36 40% 50%' },
];

const typeScale = [
    { cls: 'text-display-xl', display: true, spec: 'display-xl · clamp 58–84 / 800', sample: '2026' },
    { cls: 'text-display', display: true, spec: 'display · 44 / 800 / -.035em', sample: 'Sunday 21 June' },
    { cls: 'text-stat', display: true, spec: 'stat · 31 / 800 / -.03em', sample: '11,240' },
    { cls: 'text-name', display: true, spec: 'name · 24 / 800 / -.03em', sample: 'Taylor Drayson' },
    { cls: 'text-item-title', display: true, spec: 'item-title · 23 / 700 / -.025em', sample: 'Morning Run' },
    { cls: 'text-section', display: true, spec: 'section · 18 / 700 / -.02em', sample: 'Movement' },
    { cls: 'text-body', display: false, spec: 'body · 15 / 400 · Inter', sample: 'I build stuff on the internet, track everything, and drink too much coffee.' },
    { cls: 'text-nav', display: false, spec: 'nav · 14.5 / 500', sample: 'Timeline · Calendar · Stats' },
    { cls: 'text-meta', display: false, spec: 'meta · 14 / 400', sample: '22:45 – 06:30 · 86% efficiency · 3 cycles' },
    { cls: 'text-caption', display: false, spec: 'caption · 13 / 400', sample: 'Cottage rentals · Mar 12 – 18' },
    { cls: 'text-label', display: false, upper: true, spec: 'label · 11 / 600 / .07em', sample: 'Calories' },
    { cls: 'text-eyebrow', display: false, upper: true, spec: 'eyebrow · 10.5 / 700 / .1em', sample: 'Streak' },
];

const radii = [
    { cls: 'rounded-sm', label: 'sm · 8px' },
    { cls: 'rounded-md', label: 'md · 11px' },
    { cls: 'rounded-lg', label: 'lg · 14px' },
    { cls: 'rounded-full', label: 'full' },
];

const spacingScale = [
    { cls: 'w-1', label: '1 · 4px' },
    { cls: 'w-2', label: '2 · 8px' },
    { cls: 'w-3', label: '3 · 12px' },
    { cls: 'w-4', label: '4 · 16px' },
    { cls: 'w-6', label: '6 · 24px' },
    { cls: 'w-8', label: '8 · 32px' },
    { cls: 'w-12', label: '12 · 48px' },
    { cls: 'w-16', label: '16 · 64px' },
];

const navItems = [
    { icon: Home01Icon, label: 'Timeline', active: false },
    { icon: Calendar03Icon, label: 'Calendar', active: true },
    { icon: ChartColumnIcon, label: 'Stats', active: false },
    { icon: MapsLocation01Icon, label: 'Map', active: false },
    { icon: UserIcon, label: 'About', active: false },
];

const numbers = [
    { v: '7h 12m', l: 'Slept' },
    { v: '5.42', u: 'km', l: 'Run' },
    { v: '11,240', l: 'Steps' },
    { v: '2,140', l: 'Calories' },
];

const mix = [
    { l: 'Run', w: 'w-7/12', acc: false, v: '58%' },
    { l: 'Walk', w: 'w-2/12', acc: true, v: '16%' },
    { l: 'Padel', w: 'w-1/12', acc: false, v: '11%' },
    { l: 'Tennis', w: 'w-1/12', acc: false, v: '9%' },
];

const places = [
    { r: '01', n: 'David Lloyd', s: 'tennis · Croydon', v: '86' },
    { r: '02', n: 'Aerodrome Hotel', s: 'BNI · Croydon', v: '44' },
    { r: '03', n: 'Cineworld', s: 'cinema · Croydon', v: '38' },
];

const posters = [
    { t: 'The Brutalist', r: '9 / 10', bg: 'linear-gradient(160deg,#5a3f28,#241408)' },
    { t: 'Conclave', r: '8 / 10', bg: 'linear-gradient(160deg,#22364f,#0e1a2c)' },
    { t: 'Anora', r: '7 / 10', bg: 'linear-gradient(160deg,#8a1f23,#430f12)' },
];

const feed = [
    { icon: Moon02Icon, type: 'Sleep', date: '06:30', title: 'Slept 7h 12m', meta: '22:45 – 06:30 · 86% efficiency' },
    { icon: WorkoutRunIcon, type: 'Activity · Run', date: '07:30', title: 'Morning Run', meta: '5.42 km · 39 min · new PB', pb: true },
    { icon: Location01Icon, type: 'Place', date: '09:30', title: 'Example Coffee Co.', meta: 'Coffee Shop · 12th visit' },
];

const heatCells = Array.from({ length: 119 }, (_, i) => {
    const v = (Math.sin((i + 1) * 43.13) * 4313.13) % 1;
    const a = v < 0 ? v + 1 : v;
    return a < 0.18 ? 0 : a < 0.42 ? 1 : a < 0.68 ? 2 : a < 0.88 ? 3 : 4;
});

function heatColor(level) {
    return ['var(--color-surface)', 'var(--color-heat-1)', 'var(--color-heat-2)', 'var(--color-heat-3)', 'var(--color-heat-4)'][level];
}
</script>

<template>
    <Head title="Design system" />

    <div class="mx-auto max-w-5xl px-6 py-16 sm:px-10">
        <!-- header -->
        <header class="mb-16">
            <div class="text-eyebrow uppercase text-ink-3">Design system</div>
            <h1 class="mt-2 font-display text-display-xl">Taylor Drayson</h1>
            <p class="mt-4 max-w-xl text-ink-2">
                A flat, borderless lifelog. Pure white canvas, near-black ink, and a single voltage of
                <span class="font-semibold text-accent">Blueberry (#3858e9)</span>. Bricolage Grotesque carries the
                display weights; Inter runs the body. Depth comes from one shadow tier and 14px corner clipping — never borders.
            </p>
        </header>

        <!-- 01 COLOURS -->
        <section class="border-t border-line-2 pt-12">
            <div class="text-eyebrow uppercase text-ink-3">01 — Colour</div>
            <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight">Canvas, ink &amp; one accent</h2>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Surfaces</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div v-for="c in surfaces" :key="c.name" class="overflow-hidden rounded-lg shadow-card">
                    <div class="h-20" :class="c.border ? 'border-b border-line' : ''" :style="{ background: c.hex }" />
                    <div class="p-3">
                        <div class="text-caption font-semibold">{{ c.name }}</div>
                        <div class="text-xs text-ink-3 tnum">{{ c.hex }}</div>
                        <div class="mt-1 text-xs text-ink-3">{{ c.role }}</div>
                    </div>
                </div>
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Ink</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div v-for="c in ink" :key="c.name" class="overflow-hidden rounded-lg shadow-card">
                    <div class="h-20" :style="{ background: c.hex }" />
                    <div class="p-3">
                        <div class="text-caption font-semibold">{{ c.name }}</div>
                        <div class="text-xs text-ink-3 tnum">{{ c.hex }}</div>
                        <div class="mt-1 text-xs text-ink-3">{{ c.role }}</div>
                    </div>
                </div>
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Accent &amp; hairlines</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div v-for="c in [...accent, ...lines]" :key="c.name" class="overflow-hidden rounded-lg shadow-card">
                    <div class="h-20" :style="{ background: c.hex }" />
                    <div class="p-3">
                        <div class="text-caption font-semibold">{{ c.name }}</div>
                        <div class="text-xs text-ink-3 tnum">{{ c.hex }}</div>
                        <div v-if="c.role" class="mt-1 text-xs text-ink-3">{{ c.role }}</div>
                    </div>
                </div>
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Heatmap ramp</h3>
            <div class="flex items-center gap-2 text-xs text-ink-3">
                Quieter
                <i class="inline-block size-4 rounded" style="background: var(--color-surface)" />
                <i v-for="c in heat" :key="c.name" class="inline-block size-4 rounded" :style="{ background: c.hex }" />
                Busier
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">13 data-type accents</h3>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                <div v-for="t in dataTypes" :key="t.name" class="flex items-center gap-3 py-1">
                    <span class="size-4 flex-none rounded" :style="{ background: `hsl(${t.hsl})` }" />
                    <span class="text-caption capitalize">{{ t.name }}</span>
                    <span class="ml-auto text-xs text-ink-3 tnum">{{ t.hsl }}</span>
                </div>
            </div>
        </section>

        <!-- 02 TYPOGRAPHY -->
        <section class="mt-16 border-t border-line-2 pt-12">
            <div class="text-eyebrow uppercase text-ink-3">02 — Typography</div>
            <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight">Bricolage display · Inter body</h2>
            <p class="mt-3 max-w-xl text-ink-3">Each sample renders its own <code class="text-accent">text-*</code> token — the living source of the type scale. Numbers use tabular figures everywhere.</p>

            <div class="mt-8 flex flex-col">
                <div v-for="t in typeScale" :key="t.cls" class="flex flex-col gap-2 border-b border-line-2 py-5 sm:flex-row sm:items-baseline sm:gap-8">
                    <div class="w-56 flex-none">
                        <div class="text-caption font-semibold">{{ t.cls }}</div>
                        <div class="text-xs text-ink-3">{{ t.spec }}</div>
                    </div>
                    <div :class="[t.cls, t.display ? 'font-display' : '', t.upper ? 'uppercase' : '']">{{ t.sample }}</div>
                </div>
            </div>
        </section>

        <!-- 03 COMPONENTS -->
        <section class="mt-16 border-t border-line-2 pt-12">
            <div class="text-eyebrow uppercase text-ink-3">03 — Components</div>
            <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight">The app shell, in parts</h2>

            <div class="mt-8 grid gap-10 sm:grid-cols-2">
                <!-- sidebar nav -->
                <div>
                    <div class="mb-3 text-caption font-semibold text-ink-2">Sidebar nav</div>
                    <nav class="max-w-60">
                        <a
                            v-for="n in navItems"
                            :key="n.label"
                            href="#"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                            :class="n.active ? 'bg-surface font-semibold text-ink' : 'text-ink-2 hover:bg-surface'"
                        >
                            <Icon :icon="n.icon" class="size-5 flex-none" :class="n.active ? 'text-accent' : 'text-ink-3'" />
                            {{ n.label }}
                        </a>
                    </nav>
                    <div class="mt-6 max-w-60">
                        <div class="text-eyebrow uppercase text-ink-3">Streak</div>
                        <div class="mt-1 flex items-baseline gap-2">
                            <Icon :icon="FireIcon" class="size-4 self-center text-accent" />
                            <span class="font-display text-name tnum">2,145</span>
                            <span class="text-caption text-ink-3">days logged</span>
                        </div>
                    </div>
                </div>

                <!-- zoom + view header -->
                <div>
                    <div class="mb-3 text-caption font-semibold text-ink-2">Zoom switcher</div>
                    <div class="inline-flex gap-0.5 rounded-md bg-surface p-1">
                        <button class="rounded-sm bg-canvas px-4 py-1.5 text-sm font-semibold text-ink shadow-sm">Day</button>
                        <button class="rounded-sm px-4 py-1.5 text-sm font-semibold text-ink-3">Month</button>
                        <button class="rounded-sm px-4 py-1.5 text-sm font-semibold text-ink-3">Year</button>
                    </div>

                    <div class="mt-6 flex items-end justify-between gap-4">
                        <div>
                            <div class="font-display text-display">June 2026</div>
                            <div class="mt-2 text-meta text-ink-3">186 entries this month</div>
                        </div>
                        <div class="flex gap-4 text-caption font-semibold text-ink-3">
                            <a href="#" class="inline-flex items-center gap-1 hover:text-accent"><Icon :icon="ArrowLeft01Icon" class="size-4" /> May</a>
                            <a href="#" class="inline-flex items-center gap-1 hover:text-accent">July <Icon :icon="ArrowRight01Icon" class="size-4" /></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- number strip -->
            <div class="mt-10">
                <div class="mb-3 text-caption font-semibold text-ink-2">Number strip</div>
                <div class="flex flex-wrap gap-x-12 gap-y-6">
                    <div v-for="n in numbers" :key="n.l">
                        <div class="font-display text-stat tnum">
                            {{ n.v }}<span v-if="n.u" class="ml-1 text-body font-semibold text-ink-3">{{ n.u }}</span>
                        </div>
                        <div class="mt-1.5 text-label uppercase text-ink-3">{{ n.l }}</div>
                    </div>
                </div>
            </div>

            <!-- timeline feed -->
            <div class="mt-10">
                <div class="mb-3 text-caption font-semibold text-ink-2">Timeline feed</div>
                <div class="ds-feed flex flex-col gap-9 pl-14">
                    <div v-for="item in feed" :key="item.title" class="relative">
                        <span class="absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-surface text-ink-2">
                            <Icon :icon="item.icon" class="size-5" />
                        </span>
                        <span class="float-right text-xs text-ink-3 tnum">{{ item.date }}</span>
                        <div class="text-label uppercase text-ink-3">{{ item.type }}</div>
                        <div class="mt-1 font-display text-item-title">{{ item.title }}</div>
                        <div class="mt-2 text-meta text-ink-2">
                            <span :class="item.pb ? 'font-semibold text-accent' : ''">{{ item.meta }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- bars + places -->
            <div class="mt-10 grid gap-10 sm:grid-cols-2">
                <div>
                    <div class="mb-3 flex items-baseline justify-between text-caption font-semibold text-ink-2">Activity mix <span class="text-xs font-normal text-ink-3">share of sessions</span></div>
                    <div class="flex flex-col gap-3">
                        <div v-for="b in mix" :key="b.l" class="flex items-center gap-3">
                            <span class="w-14 text-xs text-ink-2">{{ b.l }}</span>
                            <div class="h-3 flex-1 overflow-hidden rounded bg-surface">
                                <div class="h-full rounded" :class="[b.acc ? 'bg-accent' : 'bg-ink', b.w]" />
                            </div>
                            <span class="w-10 text-right text-xs text-ink-2 tnum">{{ b.v }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="mb-3 text-caption font-semibold text-ink-2">Most visited</div>
                    <div class="flex flex-col">
                        <div v-for="p in places" :key="p.r" class="flex items-center gap-3 border-b border-line-2 py-3 last:border-0">
                            <span class="w-6 text-xs text-ink-3 tnum">{{ p.r }}</span>
                            <div class="flex-1">
                                <div class="text-meta font-semibold">{{ p.n }}</div>
                                <div class="text-xs text-ink-3">{{ p.s }}</div>
                            </div>
                            <span class="font-display text-base font-bold tnum">{{ p.v }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- heatmap + posters -->
            <div class="mt-10 grid gap-10 sm:grid-cols-2">
                <div>
                    <div class="mb-3 text-caption font-semibold text-ink-2">Year heatmap</div>
                    <div class="grid grid-flow-col grid-rows-7 auto-cols-min gap-0.5">
                        <span v-for="(lvl, i) in heatCells" :key="i" class="size-3 rounded" :style="{ background: heatColor(lvl) }" />
                    </div>
                </div>
                <div>
                    <div class="mb-3 text-caption font-semibold text-ink-2">Film posters</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div v-for="p in posters" :key="p.t" class="relative flex h-40 items-end overflow-hidden rounded-lg" :style="{ background: p.bg }">
                            <div class="absolute inset-0" style="background: linear-gradient(transparent 42%, rgba(0,0,0,.58))" />
                            <div class="relative z-10 p-3 text-white">
                                <div class="text-caption font-semibold">{{ p.t }}</div>
                                <div class="mt-0.5 text-xs text-white/85 tnum">{{ p.r }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 04 ELEVATION + RADIUS + SPACING -->
        <section class="mt-16 border-t border-line-2 pt-12">
            <div class="text-eyebrow uppercase text-ink-3">04 — Form</div>
            <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight">Elevation, radius &amp; spacing</h2>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Elevation — one tier</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-canvas p-5">
                    <div class="text-caption font-semibold">Flat</div>
                    <p class="mt-1 text-xs text-ink-3">95% of surfaces — depth from clipping, not shadow.</p>
                </div>
                <div class="rounded-lg bg-canvas p-5 shadow-card">
                    <div class="text-caption font-semibold">shadow-card</div>
                    <p class="mt-1 text-xs text-ink-3">The single hover-float tier on cards &amp; calendar cells.</p>
                </div>
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Radius</h3>
            <div class="flex flex-wrap gap-4">
                <div v-for="r in radii" :key="r.cls" class="flex size-20 items-center justify-center bg-surface text-center text-xs text-ink-3" :class="r.cls">{{ r.label }}</div>
            </div>

            <h3 class="mt-8 mb-3 text-caption font-semibold text-ink-2">Spacing</h3>
            <div class="flex flex-col gap-2">
                <div v-for="s in spacingScale" :key="s.cls" class="flex items-center gap-3">
                    <div class="h-3 bg-accent" :class="s.cls" />
                    <span class="text-xs text-ink-3 tnum">{{ s.label }}</span>
                </div>
            </div>
        </section>

        <footer class="mt-16 border-t border-line-2 pt-5 text-xs text-ink-3">
            Living design reference · Inter + Bricolage Grotesque · single Blueberry accent · generated from personal-site-views.html
        </footer>
    </div>
</template>

<style scoped>
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
    background: var(--color-line-2);
    border-radius: 2px;
}
</style>
