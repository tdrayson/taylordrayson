<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import 'plyr/dist/plyr.css';
import Icon from '../Components/Ui/Icon.vue';
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
import CountGroup from '../Components/Ui/CountGroup.vue';
import CountSegment from '../Components/Ui/CountSegment.vue';

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
// One sample row of counts for the CountGroup demo.
const countSample = [
    { key: 'reactions', icon: 'ThumbsUpIcon', count: 26 },
    { key: 'replies', icon: 'Comment01Icon', count: 19 },
    { key: 'reposts', icon: 'ArrowReloadHorizontalIcon', count: 7 },
];

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

const typeScale = [
    { cls: 'text-display-xl', display: true, label: 'display-xl', sample: '2026' },
    { cls: 'text-display', display: true, label: 'display', sample: 'Sunday 21 June' },
    { cls: 'text-stat', display: true, label: 'stat', sample: '11,240' },
    { cls: 'text-name', display: true, label: 'name', sample: 'Taylor Drayson' },
    { cls: 'text-item-title', display: true, label: 'item-title', sample: 'Morning Run' },
    { cls: 'text-section', display: true, label: 'section', sample: 'Movement' },
    { cls: 'text-body', display: false, label: 'body', sample: 'I build stuff and track everything.' },
    { cls: 'text-nav', display: false, label: 'nav', sample: 'Timeline, Calendar, Stats' },
    { cls: 'text-meta', display: false, label: 'meta', sample: '86% sleep efficiency' },
    { cls: 'text-caption', display: false, label: 'caption', sample: 'Croydon, 12th visit' },
    { cls: 'text-label', display: false, upper: true, label: 'label', sample: 'Calories' },
    { cls: 'text-eyebrow', display: false, upper: true, label: 'eyebrow', sample: 'Streak' },
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
            <h1 class="font-display text-display">Design system</h1>
            <p class="mt-3 max-w-prose text-body text-neutral-500">
                A flat, borderless lifelog. A uniform neutral scale, a single
                <span class="font-semibold text-accent-500">Blueberry</span> accent, Bricolage Grotesque for display
                and Inter for body. The reference below is the living source of truth.
            </p>
        </header>

        <!-- Colour -->
        <section class="space-y-5">
            <h2 class="text-eyebrow uppercase text-neutral-900">Colour</h2>

            <div>
                <div class="text-label uppercase text-neutral-500">Neutral</div>
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
                <div class="text-label uppercase text-neutral-500">Accent</div>
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
                <div class="text-label uppercase text-neutral-500">Data types</div>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
                    <span v-for="accent in dataTypes" :key="accent" class="flex items-center gap-2 text-caption capitalize">
                        <span class="size-3 rounded-sm" :style="{ background: `var(--color-${accent})` }" />{{ accent }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Typography -->
        <section class="space-y-6">
            <h2 class="text-eyebrow uppercase text-neutral-900">Typography</h2>

            <div class="flex gap-10">
                <div>
                    <div class="font-display text-stat">Aa</div>
                    <div class="mt-1 text-label uppercase text-neutral-500">Bricolage, display</div>
                </div>
                <div>
                    <div class="text-stat">Aa</div>
                    <div class="mt-1 text-label uppercase text-neutral-500">Inter, body</div>
                </div>
            </div>

            <div class="space-y-4">
                <div v-for="t in typeScale" :key="t.cls" class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:gap-6">
                    <div class="w-24 shrink-0 text-label uppercase text-neutral-500 sm:pt-1">{{ t.label }}</div>
                    <div :class="[t.cls, t.display ? 'font-display' : '', t.upper ? 'uppercase' : '']">{{ t.sample }}</div>
                </div>
            </div>

            <div class="max-w-prose space-y-3 text-body text-neutral-700">
                <p>
                    Body copy is Inter at 15px with generous leading, tuned for reading a day at a glance. Display
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

        <!-- Count groups -->
        <section class="space-y-4">
            <h2 class="ds-label">Count groups</h2>
            <div v-for="variant in ['plain', 'bare']" :key="variant" class="flex flex-wrap items-center gap-3">
                <CountGroup v-for="size in ['md', 'sm']" :key="size" :variant="variant" :size="size">
                    <CountSegment v-for="item in countSample" :key="item.key" :class="size === 'sm' ? 'text-caption' : 'text-meta'">
                        <Icon :name="item.icon" :class="size === 'sm' ? 'size-3.5' : 'size-4'" />
                        <span class="tnum">{{ item.count }}</span>
                    </CountSegment>
                </CountGroup>
            </div>
        </section>

        <!-- Buttons -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Buttons</h2>
            <div class="flex flex-wrap items-center gap-3">
                <Button v-for="v in buttonVariants" :key="v" :variant="v">{{ v }}</Button>
            </div>
        </section>

        <!-- Callouts (Portable Text nodes) -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Callouts</h2>
            <BlockContent :document="callouts" />
        </section>

        <!-- Pills -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Pills</h2>
            <div class="flex flex-wrap items-center gap-2">
                <Pill label="Default" />
                <Pill label="Accent" variant="accent" />
                <Pill label="Outline" variant="outline" />
            </div>
        </section>

        <!-- Form -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Form</h2>
            <div class="max-w-sm space-y-3">
                <Input placeholder="Search entries…" />
                <Textarea v-model="note" :rows="2" placeholder="A quick note…" />
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-meta"><Checkbox v-model="checked" /> Checkbox</label>
                    <label class="flex items-center gap-2 text-meta"><Switch v-model="toggled" /> Switch</label>
                </div>

                <p class="pt-3 text-label uppercase text-neutral-500">Select, boxed and bare</p>
                <Select v-model="selectYear" :options="yearOptions" />
                <p class="text-meta text-neutral-500">
                    Sits inline in a sentence, jump to
                    <Select v-model="selectYear" variant="bare" :options="yearOptions" />
                </p>

                <p class="pt-3 text-label uppercase text-neutral-500">Select, invalid</p>
                <Select v-model="selectYear" :options="yearOptions" invalid />

                <p class="pt-3 text-label uppercase text-neutral-500">Select, small</p>
                <Select v-model="selectYear" :options="yearOptions" size="sm" />

                <p class="pt-3 text-label uppercase text-neutral-500">Read-only</p>
                <Input model-value="Settled after the first save" readonly />
                <Textarea model-value="Written once, never edited again." :rows="2" readonly />
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-meta"><Checkbox model-value readonly /> Checkbox</label>
                    <label class="flex items-center gap-2 text-meta"><Switch model-value readonly /> Switch</label>
                </div>
                <Select :model-value="selectYear" :options="yearOptions" readonly />
            </div>
        </section>

        <!-- Links -->
        <section class="space-y-4">
            <h2 class="text-eyebrow uppercase text-neutral-900">Links</h2>
            <div>
                <div class="mb-1.5 text-label uppercase text-neutral-500">In-app</div>
                <Link href="/" class="text-meta font-semibold text-neutral-700 transition-colors hover:text-accent-500">Back to timeline</Link>
            </div>
            <div>
                <div class="mb-1.5 text-label uppercase text-neutral-500">External</div>
                <ExternalLink href="https://thisweekwith.co.uk" label="This Week With" />
            </div>
            <div>
                <div class="mb-1.5 text-label uppercase text-neutral-500">Social</div>
                <SocialLinks />
            </div>
            <div>
                <div class="mb-1.5 text-label uppercase text-neutral-500">In body content</div>
                <p class="max-w-prose text-body text-neutral-700">
                    Links in prose use the editor style:
                    <a href="#" class="text-accent-500 underline underline-offset-2 transition-colors hover:text-accent-700">an inline link</a>.
                </p>
            </div>
            <div>
                <div class="mb-1.5 text-label uppercase text-neutral-500">Smart links</div>
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
            <h2 class="text-eyebrow uppercase text-neutral-900">Detail list</h2>
            <div class="max-w-md">
                <DetailList :rows="detailRows" />
            </div>
        </section>

        <!-- Pagination -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Pagination</h2>
            <div class="max-w-md">
                <Pagination :current-page="page" :last-page="5" @navigate="page = $event" />
            </div>

            <p class="pt-6 text-label uppercase text-neutral-500">Timeline, date anchored</p>
            <div class="max-w-xl">
                <Pagination :current-page="2" :last-page="99" prev-label="Newer" next-label="Older" prev-url="/?before=2026-09-03" next-url="/?before=2026-08-24">
                    <template #label>24 Aug &ndash; 2 Sep 2026</template>
                </Pagination>
                <YearJump :years="jumpYears" :current="2026" />
            </div>

            <p class="pt-6 text-label uppercase text-neutral-500">Year archive, month strip</p>
            <div class="max-w-xl space-y-4">
                <div>
                    <p class="mb-2 text-caption text-neutral-500">Full year, June current</p>
                    <MonthStrip :year="2025" :months="denseMonths" :current="6" />
                </div>
                <div>
                    <p class="mb-2 text-caption text-neutral-500">Sparse year, empty months not linked</p>
                    <MonthStrip :year="2012" :months="sparseMonths" />
                </div>
            </div>

            <p class="pt-6 text-label uppercase text-neutral-500">Month archive, date anchored</p>
            <div class="max-w-xl">
                <Pagination :current-page="2" :last-page="12" prev-label="Newer" next-label="Older" prev-url="/2018/05" next-url="/2018/05">
                    <template #label>4 &ndash; 9 May 2018</template>
                </Pagination>
            </div>
        </section>

        <!-- Figure -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Figure</h2>
            <figure class="max-w-sm">
                <img src="/headshot-taylor.jpg" alt="Taylor Drayson" class="w-full rounded-lg" />
                <figcaption class="mt-2 text-caption text-neutral-500">Taylor Drayson, Croydon.</figcaption>
            </figure>
        </section>

        <!-- Video -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Video</h2>
            <p class="text-label uppercase text-neutral-500">Plyr, themed to the accent. Sample source.</p>
            <div class="max-w-xl overflow-hidden rounded-lg bg-black">
                <div ref="videoEl" data-plyr-provider="youtube" data-plyr-embed-id="bTqVqk7FSmY"></div>
            </div>
        </section>

        <!-- Audio -->
        <section class="space-y-3">
            <h2 class="text-eyebrow uppercase text-neutral-900">Audio</h2>
            <p class="text-label uppercase text-neutral-500">The player controls from our custom audio bar.</p>
            <div class="flex max-w-md items-center gap-4">
                <img src="/logos/this-week-with.jpg" alt="" class="size-11 shrink-0 rounded-md object-cover">
                <Button variant="primary" size="icon" pill class="size-10 shrink-0" aria-label="Play">
                    <Icon name="PlayIcon" class="size-5" />
                </Button>
                <div class="min-w-0 flex-1">
                    <div class="block truncate text-meta font-semibold text-neutral-900">This Week With, Episode 12</div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-label text-neutral-500 tabular-nums">1:24</span>
                        <div class="relative h-1.5 flex-1 rounded-full bg-neutral-100">
                            <div class="absolute inset-y-0 left-0 w-3/8 rounded-full bg-accent-500" />
                        </div>
                        <span class="text-label text-neutral-500 tabular-nums">42:10</span>
                    </div>
                </div>
                <Icon name="Cancel01Icon" class="size-5 shrink-0 text-neutral-500" />
            </div>
        </section>
    </article>
</template>

