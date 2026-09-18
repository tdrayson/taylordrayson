<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import 'plyr/dist/plyr.css';
import Icon from '../Components/Ui/Icon.vue';
import Eyebrow from '../Components/Ui/Eyebrow.vue';
import Heading from '../Components/Ui/Heading.vue';
import Button from '../Components/Ui/Button.vue';
import Pill from '../Components/Ui/Pill.vue';
import Input from '../Components/Ui/Input.vue';
import Textarea from '../Components/Ui/Textarea.vue';
import Checkbox from '../Components/Ui/Checkbox.vue';
import Switch from '../Components/Ui/Switch.vue';
import DetailList from '../Components/Ui/DetailList.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import ExternalLink from '../Components/Ui/ExternalLink.vue';
import SocialLinks from '../Components/Profile/SocialLinks.vue';
import BlockContent from '../Components/Ui/BlockContent.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import { timelineTypes } from '../entryTypes.js';
import YearJump from '../Components/Timeline/YearJump.vue';
import MonthStrip from '../Components/Timeline/MonthStrip.vue';
import Select from '../Components/Ui/Select.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    og: { type: Object, default: () => ({}) },
    // A sample document exercising every link treatment, with the maps the
    // chips resolve against, exactly as an entry page supplies them.
    smartLinks: { type: Array, default: () => [] },
    linkPreviews: { type: Object, default: () => ({}) },
    linkFavicons: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [{ label: 'Design system' }],
});

const note = ref('');
const checked = ref(true);
const toggled = ref(true);
const page = ref(2);

// Static stand-ins for the date-anchored pagination. Real figures come from the
// controller; these are shaped like them so wiring it up is a prop swap.
const jumpYears = [2026, 2025, 2024, 2023, 2022, 2021, 2020, 2019, 2018]
    .map((year) => ({ year, href: `/${year}` }));
const selectYear = ref(2025);
const yearOptions = jumpYears.map(({ year }) => ({ value: year, label: String(year) }));

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
// A dense year, then one with gaps, to show both states of the strip.
const denseMonths = MONTH_NAMES.map((label, i) => ({
    month: i + 1, label, href: `/2025/${String(i + 1).padStart(2, '0')}`, total: 300 + i * 7,
}));
const sparseMonths = MONTH_NAMES.map((label, i) => ({
    month: i + 1, label, href: `/2012/${String(i + 1).padStart(2, '0')}`,
    total: [0, 4, 0, 0, 2, 6, 0, 0, 3, 0, 4, 0][i],
}));

// One Portable Text callout per variant, so the set is reviewable in one place.
const callouts = ['note', 'tip', 'important', 'warning', 'caution'].map((variant, i) => ({
    _type: 'callout',
    _key: `ds-callout-${variant}`,
    variant,
    markDefs: [],
    children: [
        { _type: 'span', _key: `ds-callout-${variant}-a`, text: 'Set ', marks: [] },
        { _type: 'span', _key: `ds-callout-${variant}-b`, text: "'pro' => true", marks: ['code'] },
        { _type: 'span', _key: `ds-callout-${variant}-c`, text: ` in config to use every ${variant} feature while in development, like unlimited users and permissions. (${i + 1}/5)`, marks: [] },
    ],
}));

const neutral = [0, 25, 50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
const accent = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];

// The accent tokens from the type catalogue, so a new data type shows up here
// on its own. Swatches read the custom property rather than a copied hue, which
// is what makes them correct in dark mode too.
const dataTypes = Object.values(timelineTypes).map((type) => type.accent);

// The scale is plain Tailwind now, so each row names what to reach for: the
// component where the role repeats, the utilities where it doesn't.
const typeScale = [
    { use: '<Heading size="display-xl">', cls: 'font-display text-6xl font-extrabold tracking-tight sm:text-7xl', sample: '2026' },
    { use: '<Heading size="display">', cls: 'font-display text-5xl font-extrabold tracking-tight', sample: 'Sunday 21 June' },
    { use: '<Stat>', cls: 'font-display text-3xl font-extrabold leading-none tracking-tight tabular-nums', sample: '11,240' },
    { use: '<Heading size="title">', cls: 'font-display text-2xl font-extrabold leading-tight tracking-tight', sample: 'Morning Run' },
    { use: '<Heading size="section">', cls: 'font-display text-lg font-bold leading-tight tracking-tight', sample: 'Movement' },
    { use: 'text-base', cls: 'text-base', sample: 'I build stuff and track everything.' },
    { use: 'text-sm font-medium', cls: 'text-sm font-medium', sample: 'Timeline, Calendar, Stats' },
    { use: 'text-sm', cls: 'text-sm', sample: '86% sleep efficiency' },
    { use: 'text-xs', cls: 'text-xs', sample: 'Croydon, 12th visit' },
    { use: '<Eyebrow>', cls: 'text-2xs font-semibold uppercase tracking-wider', sample: 'Streak' },
];

const buttonVariants = ['primary', 'secondary', 'ghost', 'chip', 'destructive', 'link'];

const detailRows = [
    { label: 'Distance', value: '3.4 mi' },
    { label: 'Duration', value: '39 min' },
    { label: 'Avg pace', value: '11:35 / mi' },
];

const videoEl = ref(null);
let videoPlayer = null;

onMounted(async () => {
    if (! videoEl.value) {
        return;
    }

    // Plyr reads `document` as it loads, so importing it at the top of the
    // file would crash this page's server render.
    const { default: Plyr } = await import('plyr');

    videoPlayer = new Plyr(videoEl.value);
});

onBeforeUnmount(() => {
    videoPlayer?.destroy();
});

function swatchInk(step) {
    return step >= 500 ? 'text-neutral-0' : 'text-neutral-900';
}
</script>

<template>
    <AppHead :og="og" />

    <article class="ds space-y-16 pb-12">
        <header>
            <Heading as="h1" size="display">Design system</Heading>
            <p class="mt-3 max-w-prose text-base text-neutral-500">
                A flat, borderless lifelog. A uniform neutral scale, a single
                <span class="font-semibold text-accent-500">Blueberry</span> accent, Bricolage Grotesque for display
                and Inter for body. The reference below is the living source of truth.
            </p>
        </header>

        <!-- Colour -->
        <section class="space-y-5">
            <Eyebrow as="h2" class="text-neutral-900">Colour</Eyebrow>

            <div>
                <Eyebrow class="text-neutral-500">Neutral</Eyebrow>
                <div class="mt-2 flex overflow-hidden rounded-lg border border-neutral-100">
                    <div
                        v-for="n in neutral"
                        :key="n"
                        class="flex h-16 flex-1 items-end p-1.5"
                        :style="{ background: `var(--color-neutral-${n})` }"
                    >
                        <span class="text-xs tabular-nums" :class="swatchInk(n)">{{ n }}</span>
                    </div>
                </div>
            </div>

            <div>
                <Eyebrow class="text-neutral-500">Accent</Eyebrow>
                <div class="mt-2 flex overflow-hidden rounded-lg border border-neutral-100">
                    <div
                        v-for="a in accent"
                        :key="a"
                        class="flex h-16 flex-1 items-end p-1.5"
                        :style="{ background: `var(--color-accent-${a})` }"
                    >
                        <span class="text-xs tabular-nums" :class="a >= 400 ? 'text-neutral-0' : 'text-neutral-900'">{{ a }}</span>
                    </div>
                </div>
            </div>

            <div>
                <Eyebrow class="text-neutral-500">Data types</Eyebrow>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    <span v-for="accent in dataTypes" :key="accent" class="flex items-center gap-2 text-xs capitalize">
                        <span class="size-3 rounded-sm" :style="{ background: `var(--color-${accent})` }" />{{ accent }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Typography -->
        <section class="space-y-6">
            <Eyebrow as="h2" class="text-neutral-900">Typography</Eyebrow>

            <div class="flex gap-10">
                <div>
                    <div class="font-display text-3xl font-extrabold tracking-tight">Aa</div>
                    <Eyebrow class="mt-1 text-neutral-500">Bricolage, display</Eyebrow>
                </div>
                <div>
                    <div class="text-3xl font-extrabold tracking-tight">Aa</div>
                    <Eyebrow class="mt-1 text-neutral-500">Inter, body</Eyebrow>
                </div>
            </div>

            <div class="space-y-4">
                <div v-for="t in typeScale" :key="t.use" class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:gap-6">
                    <code class="w-56 shrink-0 text-xs text-neutral-500 sm:pt-1.5">{{ t.use }}</code>
                    <div :class="t.cls">{{ t.sample }}</div>
                </div>
            </div>

            <div class="max-w-prose space-y-3 text-base text-neutral-700">
                <p>
                    Body copy is Inter at 16px with generous leading, tuned for reading a day at a glance. Display
                    weights run loud and tightly tracked so a glance separates the headline from the data.
                </p>
                <ul class="list-disc space-y-1 pl-5">
                    <li>Lists stay calm and close-set.</li>
                    <li>Numbers like <span class="font-semibold tabular-nums">11,240</span> use tabular figures.</li>
                    <li>Emphasis is <span class="font-semibold text-accent-500">a single voltage</span>, used sparingly.</li>
                </ul>
                <blockquote class="border-l-2 border-accent-200 pl-4 italic text-neutral-500">
                    Track everything, decorate nothing. The data is the ornament.
                </blockquote>
            </div>
        </section>

        <!-- Buttons -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Buttons</Eyebrow>
            <div class="flex flex-wrap items-center gap-3">
                <Button v-for="v in buttonVariants" :key="v" :variant="v">{{ v }}</Button>
            </div>
        </section>

        <!-- Callouts (Portable Text nodes) -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Callouts</Eyebrow>
            <BlockContent :document="callouts" />
        </section>

        <!-- Pills -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Pills</Eyebrow>
            <div class="flex flex-wrap items-center gap-2">
                <Pill label="Default" />
                <Pill label="Accent" variant="accent" />
                <Pill label="Outline" variant="outline" />
            </div>
        </section>

        <!-- Form -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Form</Eyebrow>
            <div class="max-w-sm space-y-3">
                <Input placeholder="Search entries…" />
                <Textarea v-model="note" :rows="2" placeholder="A quick note…" />
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-sm"><Checkbox v-model="checked" /> Checkbox</label>
                    <label class="flex items-center gap-2 text-sm"><Switch v-model="toggled" /> Switch</label>
                </div>

                <Eyebrow as="p" class="pt-3 text-neutral-500">Select, boxed and bare</Eyebrow>
                <Select v-model="selectYear" :options="yearOptions" />
                <p class="text-sm text-neutral-500">
                    Sits inline in a sentence, jump to
                    <Select v-model="selectYear" variant="bare" :options="yearOptions" />
                </p>

                <Eyebrow as="p" class="pt-3 text-neutral-500">Select, invalid</Eyebrow>
                <Select v-model="selectYear" :options="yearOptions" invalid />

                <Eyebrow as="p" class="pt-3 text-neutral-500">Select, small</Eyebrow>
                <Select v-model="selectYear" :options="yearOptions" size="sm" />

                <Eyebrow as="p" class="pt-3 text-neutral-500">Read-only</Eyebrow>
                <Input model-value="Settled after the first save" readonly />
                <Textarea model-value="Written once, never edited again." :rows="2" readonly />
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-sm"><Checkbox model-value readonly /> Checkbox</label>
                    <label class="flex items-center gap-2 text-sm"><Switch model-value readonly /> Switch</label>
                </div>
                <Select :model-value="selectYear" :options="yearOptions" readonly />
            </div>
        </section>

        <!-- Links -->
        <section class="space-y-4">
            <Eyebrow as="h2" class="text-neutral-900">Links</Eyebrow>
            <div>
                <Eyebrow class="mb-1.5 text-neutral-500">In-app</Eyebrow>
                <Link href="/" class="text-sm font-semibold text-neutral-700 transition-colors hover:text-accent-500">Back to timeline</Link>
            </div>
            <div>
                <Eyebrow class="mb-1.5 text-neutral-500">External</Eyebrow>
                <ExternalLink href="https://thisweekwith.co.uk" label="This Week With" />
            </div>
            <div>
                <Eyebrow class="mb-1.5 text-neutral-500">Social</Eyebrow>
                <SocialLinks />
            </div>
            <div>
                <Eyebrow class="mb-1.5 text-neutral-500">In body content</Eyebrow>
                <p class="max-w-prose text-base text-neutral-700">
                    Links in prose use the editor style:
                    <a href="#" class="text-accent-500 underline underline-offset-2 transition-colors hover:text-accent-700">an inline link</a>.
                </p>
            </div>
            <div>
                <Eyebrow class="mb-1.5 text-neutral-500">Smart links</Eyebrow>
                <!-- The real renderer over a real document, so this section
                     cannot drift from what an entry actually shows. -->
                <BlockContent
                    class="max-w-prose"
                    :document="smartLinks"
                    :link-previews="linkPreviews"
                    :link-favicons="linkFavicons"
                />
            </div>
        </section>

        <!-- Detail list -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Detail list</Eyebrow>
            <div class="max-w-md">
                <DetailList :rows="detailRows" />
            </div>
        </section>

        <!-- Pagination -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Pagination</Eyebrow>
            <div class="max-w-md">
                <Pagination :current-page="page" :last-page="5" @navigate="page = $event" />
            </div>

            <Eyebrow as="p" class="pt-6 text-neutral-500">Timeline, date anchored</Eyebrow>
            <div class="max-w-xl">
                <Pagination :current-page="2" :last-page="99" prev-label="Newer" next-label="Older" prev-url="/?before=2026-09-03" next-url="/?before=2026-08-24">
                    <template #label>24 Aug &ndash; 2 Sep 2026</template>
                </Pagination>
                <YearJump :years="jumpYears" :current="2026" />
            </div>

            <Eyebrow as="p" class="pt-6 text-neutral-500">Year archive, month strip</Eyebrow>
            <div class="max-w-xl space-y-4">
                <div>
                    <p class="mb-2 text-xs text-neutral-500">Full year, June current</p>
                    <MonthStrip :year="2025" :months="denseMonths" :current="6" />
                </div>
                <div>
                    <p class="mb-2 text-xs text-neutral-500">Sparse year, empty months not linked</p>
                    <MonthStrip :year="2012" :months="sparseMonths" />
                </div>
            </div>

            <Eyebrow as="p" class="pt-6 text-neutral-500">Month archive, date anchored</Eyebrow>
            <div class="max-w-xl">
                <Pagination :current-page="2" :last-page="12" prev-label="Newer" next-label="Older" prev-url="/2018/05" next-url="/2018/05">
                    <template #label>4 &ndash; 9 May 2018</template>
                </Pagination>
            </div>
        </section>

        <!-- Figure -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Figure</Eyebrow>
            <figure class="max-w-sm">
                <img src="/headshot-taylor.jpg" alt="Taylor Drayson" class="w-full rounded-lg" />
                <figcaption class="mt-2 text-xs text-neutral-500">Taylor Drayson, Croydon.</figcaption>
            </figure>
        </section>

        <!-- Video -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Video</Eyebrow>
            <Eyebrow as="p" class="text-neutral-500">Plyr, themed to the accent. Sample source.</Eyebrow>
            <div class="max-w-xl overflow-hidden rounded-lg bg-black">
                <div ref="videoEl" data-plyr-provider="youtube" data-plyr-embed-id="bTqVqk7FSmY"></div>
            </div>
        </section>

        <!-- Audio -->
        <section class="space-y-3">
            <Eyebrow as="h2" class="text-neutral-900">Audio</Eyebrow>
            <Eyebrow as="p" class="text-neutral-500">The player controls from our custom audio bar.</Eyebrow>
            <div class="flex max-w-md items-center gap-4">
                <img src="/logos/this-week-with.jpg" alt="" class="size-11 shrink-0 rounded-md object-cover">
                <Button variant="primary" size="icon" pill class="size-10 shrink-0" aria-label="Play">
                    <Icon name="PlayIcon" class="size-5" />
                </Button>
                <div class="min-w-0 flex-1">
                    <div class="block truncate text-sm font-semibold text-neutral-900">This Week With, Episode 12</div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-2xs font-semibold text-neutral-500 tabular-nums">1:24</span>
                        <div class="relative h-1.5 flex-1 rounded-full bg-neutral-100">
                            <div class="absolute inset-y-0 left-0 w-3/8 rounded-full bg-accent-500" />
                        </div>
                        <span class="text-2xs font-semibold text-neutral-500 tabular-nums">42:10</span>
                    </div>
                </div>
                <Icon name="Cancel01Icon" class="size-5 shrink-0 text-neutral-500" />
            </div>
        </section>
    </article>
</template>

