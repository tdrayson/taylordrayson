<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StoryHero from '../../Components/Story/StoryHero.vue';
import StoryChapter from '../../Components/Story/StoryChapter.vue';
import StoryStats from '../../Components/Story/StoryStats.vue';
import StoryNote from '../../Components/Story/StoryNote.vue';
import StoryQuote from '../../Components/Story/StoryQuote.vue';
import StoryChart from '../../Components/Story/StoryChart.vue';
import StoryAuthor from '../../Components/Story/StoryAuthor.vue';
import StoryFurtherReading from '../../Components/Story/StoryFurtherReading.vue';
import StoryToc from '../../Components/Story/StoryToc.vue';
import StoryDate from '../../Components/Story/StoryDate.vue';
import { PetrolPumpIcon } from '@hugeicons-pro/core-stroke-rounded';
import { PALETTE, baseOptions } from '../../lib/storyChart.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    story: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [
        { label: 'Data stories', href: '/stories' },
        { label: 'Fuel' },
    ],
});

const s = computed(() => props.story);

// --- formatting helpers ----------------------------------------------------

/**
 * Format a number with thousands separators (44968 becomes "44,968").
 * @param {number} value The number to format.
 * @returns {string}
 */
const n = (value) => Math.round(value).toLocaleString('en-GB');

/**
 * Format a whole-pound amount (5586 becomes "£5,586").
 * @param {number} value The amount in pounds.
 * @returns {string}
 */
const gbp = (value) => `£${n(value)}`;

/**
 * Format a precise pounds-and-pence amount (10.18 becomes "£10.18").
 * @param {number} value The amount in pounds.
 * @returns {string}
 */
const money = (value) => `£${Number(value).toFixed(2)}`;

/**
 * Format a price per litre to two decimals (1.07 becomes "£1.07").
 * @param {number} value The price per litre.
 * @returns {string}
 */
const price = (value) => `£${Number(value).toFixed(2)}`;

/**
 * Turn a YYYY-MM-DD date into a short "Mon YYYY" axis label.
 * @param {string} date An ISO date string.
 * @returns {string}
 */
const monthLabel = (date) => new Date(date).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });

const dateline = computed(() => [
    `${s.value.kpis.fromLabel} to ${s.value.kpis.toLabel}`,
    `Last updated ${s.value.kpis.updated}`,
]);

/**
 * Complete calendar years only (drops the partial first and current year) so
 * per-year totals aren't skewed by half-years.
 * @returns {Array<object>} The per-year rows for full years.
 */
const fullYears = computed(() =>
    s.value.byYear.filter((year) => year.year > s.value.kpis.fromYear && year.year < s.value.kpis.toYear),
);

const heroKpis = computed(() => [
    { value: n(s.value.kpis.fills), label: 'Fill-ups' },
    { value: n(s.value.kpis.miles), label: 'Miles driven' },
    { value: gbp(s.value.kpis.spend), label: 'Total spent' },
    { value: `${s.value.kpis.avgMpg}`, label: 'Average MPG' },
]);

// --- chart configs ---------------------------------------------------------

/**
 * Price per litre across every fill, as a line over time (the rollercoaster).
 * @returns {object} A Chart.js line dataset config.
 */
const priceChart = computed(() => ({
    labels: s.value.price.series.map((point) => monthLabel(point.date)),
    datasets: [{
        data: s.value.price.series.map((point) => point.price),
        borderColor: PALETTE.fuel,
        backgroundColor: PALETTE.fuelSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 0,
        borderWidth: 2,
    }],
}));

const priceOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `£${ctx.raw.toFixed(3)} / litre` } } },
    scales: {
        x: { ticks: { maxTicksLimit: 8, maxRotation: 0, autoSkip: true } },
        y: { ticks: { callback: (v) => `£${v.toFixed(2)}` } },
    },
});

/**
 * Miles per year as bars: grey for context, with the lowest year (the pandemic
 * dip) picked out in the data colour so the point of interest is unambiguous.
 * @returns {object} A Chart.js bar dataset config.
 */
const milesChart = computed(() => ({
    labels: fullYears.value.map((year) => year.year),
    datasets: [{
        data: fullYears.value.map((year) => year.miles),
        backgroundColor: fullYears.value.map((year) => (year.miles === minMilesYear.value ? PALETTE.fuel : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const minMilesYear = computed(() => Math.min(...fullYears.value.map((year) => year.miles)));

const milesOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw.toLocaleString()} miles` } } },
    scales: { y: { ticks: { callback: (v) => v.toLocaleString() } } },
});

/**
 * Average MPG by calendar month, showing the summer-high / winter-low curve.
 * @returns {object} A Chart.js line dataset config.
 */
const seasonalChart = computed(() => ({
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    datasets: [{
        data: s.value.mpgByMonth.map((month) => month.mpg),
        borderColor: PALETTE.fuel,
        backgroundColor: PALETTE.fuelSoft,
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: PALETTE.fuel,
        borderWidth: 2,
    }],
}));

const seasonalOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} mpg avg` } } },
    scales: { y: { suggestedMin: 45, suggestedMax: 58, ticks: { callback: (v) => `${v} mpg` } } },
});

const maxCost = computed(() => Math.max(...fullYears.value.map((year) => year.cost)));

/**
 * Total spend per year as bars: grey for context, the most expensive year in
 * the data colour.
 * @returns {object} A Chart.js bar dataset config.
 */
const spendChart = computed(() => ({
    labels: fullYears.value.map((year) => year.year),
    datasets: [{
        data: fullYears.value.map((year) => Math.round(year.cost)),
        backgroundColor: fullYears.value.map((year) => (year.cost === maxCost.value ? PALETTE.fuel : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const spendOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `£${ctx.raw.toLocaleString()}` } } },
    scales: { y: { ticks: { callback: (v) => `£${v}` } } },
});

/**
 * The real per-mile running cost over the years, as a line.
 * @returns {object} A Chart.js line dataset config.
 */
const ppmChart = computed(() => ({
    labels: fullYears.value.map((year) => year.year),
    datasets: [{
        data: fullYears.value.map((year) => year.pencePerMile),
        borderColor: PALETTE.fuel,
        backgroundColor: PALETTE.fuelSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: PALETTE.fuel,
        borderWidth: 2,
    }],
}));

const ppmOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw}p per mile` } } },
    scales: { y: { ticks: { callback: (v) => `${v}p` } } },
});

/**
 * Average MPG per full year, to show economy holding broadly flat.
 * @returns {object} A Chart.js line dataset config.
 */
const mpgYearChart = computed(() => ({
    labels: fullYears.value.map((year) => year.year),
    datasets: [{
        data: fullYears.value.map((year) => year.avgMpg),
        borderColor: PALETTE.fuel,
        backgroundColor: PALETTE.fuelSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: PALETTE.fuel,
        borderWidth: 2,
    }],
}));

const mpgYearOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} mpg` } } },
    scales: { y: { suggestedMin: 45, suggestedMax: 56, ticks: { callback: (v) => `${v} mpg` } } },
});

/**
 * Cumulative fuel-card saving over time, as a filled line that only ever climbs.
 * @returns {object} A Chart.js line dataset config.
 */
const savingsChart = computed(() => ({
    labels: s.value.fuelCard.cumulative.map((point) => monthLabel(point.date)),
    datasets: [{
        data: s.value.fuelCard.cumulative.map((point) => point.saved),
        borderColor: PALETTE.fuel,
        backgroundColor: PALETTE.fuelSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        pointBackgroundColor: PALETTE.fuel,
        borderWidth: 2,
    }],
}));

const savingsOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `£${ctx.raw.toFixed(2)} saved` } } },
    scales: { y: { ticks: { callback: (v) => `£${v}` } } },
});
</script>

<template>
    <AppHead :og="og" />

    <StoryHero :meta="dateline" :kpis="heroKpis" :icon="PetrolPumpIcon">
        <template #title>The pandemic and a war, in my fuel receipts</template>
        <template #lead>
            Right, so I've got a slightly odd habit. Since {{ story.car.since }} I've written down every single time I've
            filled up the same {{ story.car.name }}. That's {{ n(story.kpis.fills) }} fill-ups, {{ n(story.kpis.miles) }}
            miles and {{ n(story.kpis.litres) }} litres later, and it turns out there's a proper little story buried in all
            those receipts. Here's what I found.
        </template>
    </StoryHero>

    <!-- The article stays left-aligned with the header, capped at the media
         width. On xl+ the contents list is placed in the right gutter off this
         column's edge and sticks via CSS (see StoryToc); below xl it's a pill. -->
    <div class="relative mt-14 max-w-media">
        <StoryChapter number="01" kicker="The car">
        <template #title>The same {{ story.car.name }}, all the way through.</template>
        <p>
            {{ story.car.plate }} has been my daily ride since {{ story.car.since }}, and pretty much the day I got it I
            started scribbling down every fill. The litres, the price, what it cost, the odometer reading, all of it. A bit
            obsessive, I know, but it does mean none of the numbers here are guesswork.
        </p>
        <p>
            Add it all up and we're talking <strong>{{ n(story.kpis.litres) }} litres</strong> of petrol,
            <strong>{{ gbp(story.kpis.spend) }}</strong> spent and <strong>{{ n(story.kpis.miles) }} miles</strong>
            covered, which works out at about {{ story.kpis.avgMpg }} miles to the gallon.
        </p>
        <StoryStats :stats="[
            { value: n(story.kpis.fills), label: 'Fill-ups logged', tone: 'fuel' },
            { value: n(story.kpis.miles), label: 'Miles driven' },
            { value: n(story.kpis.litres), label: 'Litres bought' },
            { value: gbp(story.kpis.spend), label: 'Total spent' },
        ]" />
        <StoryNote label="For scale">
            Just for fun: those <strong>{{ n(story.kpis.miles) }} miles</strong> are very nearly
            <strong>{{ story.kpis.aroundEarth }} laps of the planet</strong> (it's 24,901 miles the whole way round).
        </StoryNote>
    </StoryChapter>

    <StoryChapter number="02" kicker="The price of petrol">
        <template #title>From {{ price(story.price.low.value) }} to {{ price(story.price.high.value) }} a litre.</template>
        <p>
            The price per litre has been on a proper rollercoaster. The cheapest I ever paid was
            <strong>{{ price(story.price.low.value) }} in <StoryDate :date="story.price.low.date" :label="story.price.low.when" month /></strong>,
            right in the depths of the pandemic when nobody was going anywhere. The dearest was
            <strong>{{ price(story.price.high.value) }} in <StoryDate :date="story.price.high.date" :label="story.price.high.when" month /></strong>,
            when the energy crisis after Russia invaded Ukraine sent everything haywire. That's a <strong>{{ story.price.swingPct }}% jump</strong> between the two, for the same car at the
            same sort of garage.
        </p>
        <StoryChart
            type="line"
            label="Price per litre, every fill"
            :summary="`Price per litre over time, from ${price(story.price.low.value)} to ${price(story.price.high.value)}`"
            :data="priceChart"
            :options="priceOptions"
        />
        <StoryStats :stats="[
            { value: price(story.price.low.value), label: `Cheapest, ${story.price.low.when}` },
            { value: price(story.price.high.value), label: `Dearest, ${story.price.high.when}`, tone: 'fuel' },
            { value: `${story.price.swingPct}%`, label: 'Peak-to-trough swing' },
            { value: price(story.kpis.avgPrice), label: 'All-time average' },
        ]" />
        <StoryNote label="Note">
            Things have calmed right down since mid-2023. Nowhere near the madness of 2022, but they've never really
            dropped back to those lovely pandemic prices either.
        </StoryNote>
    </StoryChapter>

    <StoryChapter number="03" kicker="How far I drove">
        <template #title>Around 6,000 miles a year, with two big exceptions.</template>
        <p>
            Most years I just potter along at around 6,000 miles, which is actually a fair bit under the national average.
            The big oddball is <strong>2020</strong>, when it dropped to barely half that. And you can see exactly why in
            the data: two big stretches where the car basically didn't move.
        </p>
        <StoryChart
            type="bar"
            label="Miles driven per full year"
            summary="Miles driven each year, with 2020 far below the others"
            :data="milesChart"
            :options="milesOptions"
            :height="220"
        />
        <StoryStats :stats="[
            { value: `${story.intervals.longest} days`, label: 'Longest gap between fills', tone: 'fuel' },
            { value: `${story.intervals.typical} days`, label: 'Typical gap' },
            { value: `${story.intervals.shortest} days`, label: 'Shortest gap' },
        ]" />
        <StoryNote label="Lockdowns">
            You can spot both lockdowns a mile off: <strong>{{ story.gaps[0].days }} days</strong> without a single fill
            ({{ story.gaps[0].from }} to {{ story.gaps[0].to }}) for the first one, and
            <strong>{{ story.gaps[1].days }} days</strong> ({{ story.gaps[1].from }} to {{ story.gaps[1].to }}) for the
            third. Either side of those, I'm back to filling up like clockwork.
        </StoryNote>
    </StoryChapter>

    <StoryChapter number="04" kicker="Seasonal economy">
        <template #title>Summer adds nearly {{ story.seasonal.diff }} miles per gallon.</template>
        <p>
            Here's a fun one you only really spot after a few years: the car drinks more in winter. Summer fills average
            <strong>{{ story.seasonal.summer }} mpg</strong>, but winter ones drop to
            <strong>{{ story.seasonal.winter }} mpg</strong>. Some of it is the boring physics, cold dense air, soft tyres,
            the engine taking ages to warm up on short hops. But honestly the biggest culprit is probably me, blasting the
            heating the second it gets cold, which the engine has to graft a bit harder to provide. Either way, it shows up
            every single year.
        </p>
        <StoryChart
            type="line"
            label="Average MPG by calendar month"
            summary="Fuel economy peaks in summer and dips in winter"
            :data="seasonalChart"
            :options="seasonalOptions"
            :height="200"
        />
        <StoryStats :stats="[
            { value: `${story.seasonal.summer}`, label: 'Summer MPG (June to August)', tone: 'fuel' },
            { value: `${story.seasonal.winter}`, label: 'Winter MPG (December to February)' },
            { value: `+${story.seasonal.diff}`, label: 'Summer gain (mpg)' },
            { value: `${story.kpis.avgMpg}`, label: 'All-time average' },
        ]" />
    </StoryChapter>

    <StoryChapter number="05" kicker="Fuel economy">
        <template #title>{{ story.kpis.avgMpg }} miles per gallon, year after year.</template>
        <p>
            Over all those fills, the {{ story.car.name }} averages <strong>{{ story.kpis.avgMpg }} mpg</strong>. For a
            little petrol car that's pretty much bang on, just a touch under the official number, which is totally normal
            once you're out in the real world. The bit I like is how steady it stays year after year, even with the clock
            now well past <strong>{{ n(story.kpis.odometer) }} miles</strong>.
        </p>
        <StoryChart
            type="line"
            label="Average MPG per full year"
            summary="Average fuel economy is broadly flat across the years"
            :data="mpgYearChart"
            :options="mpgYearOptions"
            :height="200"
        />
        <StoryNote label="Method">
            Quick word on the maths: each mpg is just the miles since the last fill divided by the litres I put in,
            converted at 4.546 litres to the gallon. I always fill right to the top, so litres in equals litres burnt.
            Nothing's estimated, it's the genuine figure.
        </StoryNote>
    </StoryChapter>

    <StoryChapter number="06" kicker="What it cost">
        <template #title>2022 was the most expensive year. 2020 the cheapest.</template>
        <p>
            What I spend in a year is way more about the price of petrol than how much I actually drive.
            <strong>2022</strong> was the priciest by a country mile, and not because I drove more, the mileage was bang
            average, but because the price per litre went mad. <strong>2020</strong> was the cheapest, for the least
            impressive reason going: I hardly went anywhere, and fuel was dirt cheap anyway.
        </p>
        <StoryChart
            type="bar"
            label="Total fuel spend per full year"
            summary="Yearly fuel spend, highest in 2022"
            :data="spendChart"
            :options="spendOptions"
            :height="220"
        />
        <StoryQuote cite="Price, miles and economy almost never line up.">
            2020 was cheap because I barely drove and petrol had crashed, not because of any clever driving. 2022 was dear
            purely because of the price.
        </StoryQuote>
    </StoryChapter>

    <StoryChapter number="07" kicker="The real cost">
        <template #title>About {{ story.pencePerMile.latest.value }}p a mile to keep moving.</template>
        <p>
            Pence per mile is probably my favourite number, because it squishes the price and the economy into one: what
            it genuinely costs me to drive a single mile. Back in <strong>{{ story.pencePerMile.baseline.year }}</strong>,
            before all the chaos, it was about <strong>{{ story.pencePerMile.baseline.value }}p</strong>. Then it shot up,
            and even now, with petrol much cheaper again, it's stuck around
            <strong>{{ story.pencePerMile.latest.value }}p</strong>, still
            <strong>{{ story.pencePerMile.changePct }}% higher</strong> than it was. The annoying bit is that cheaper fuel
            keeps getting cancelled out by the car going a tiny bit thirstier every year.
        </p>
        <StoryChart
            type="line"
            label="Pence per mile, per full year"
            summary="The real per-mile cost over time"
            :data="ppmChart"
            :options="ppmOptions"
            :height="200"
        />
    </StoryChapter>

    <StoryChapter v-if="story.fuelCard.fills" number="08" kicker="The fuel card">
        <template #title>A small, steady saving since {{ story.fuelCard.since }}.</template>
        <p>
            In {{ story.fuelCard.since }} I started paying with a fuel card, which just quietly knocks a bit off the pump
            price. I've been logging both prices every time I use it, so I know exactly what it's saved me:
            <strong>{{ gbp(story.fuelCard.saved) }}</strong> so far, slowly stacking up in the background over
            {{ n(story.fuelCard.fills) }} fills. Never much on the day, but it's basically free money for doing nothing.
        </p>
        <StoryChart
            type="line"
            label="Cumulative saving since the first card fill"
            summary="Total fuel-card saving, climbing steadily over time"
            :data="savingsChart"
            :options="savingsOptions"
            :height="180"
        />
        <p>
            The best single one was <strong>{{ money(story.fuelCard.best.amount) }}</strong> on
            <StoryDate :date="story.fuelCard.best.date" :label="story.fuelCard.best.when" />: a
            {{ money(story.fuelCard.best.pump) }} fill that came to just
            {{ money(story.fuelCard.best.card) }} on the card. That one was so big because I filled up at motorway
            services, where the pump price is daylight robbery, so the card's rate left a massive gap.
        </p>
        <StoryStats :stats="[
            { value: gbp(story.fuelCard.saved), label: 'Saved via the fuel card', tone: 'fuel' },
            { value: n(story.fuelCard.fills), label: 'Fills on the card' },
            { value: money(story.fuelCard.best.amount), label: `Best saving, ${story.fuelCard.best.when}` },
        ]" />
    </StoryChapter>

    <StoryChapter number="09" kicker="Still driving">
        <template #title>The record continues.</template>
        <p>
            So that's seven-ish years of it. The mileage barely budges, the economy wobbles about with the seasons, and
            the cost has been yanked around far more by the world than by anything I've done. You can pretty much read the
            pandemic and the energy crisis straight off the charts.
        </p>
        <p v-if="story.projection.milestone">
            If I keep going at my usual <strong>{{ n(story.projection.annual) }} miles a year</strong>, the odometer should
            tick past <strong>{{ n(story.projection.milestone) }} miles</strong> somewhere around {{ story.projection.eta }}.
        </p>
        <p>
            I'm in no rush to get rid of it. It's cheap to run, it's never let me down, and honestly the longer this goes
            the more interesting it gets. So I'll just keep doing what I always have: writing down every litre, every mile,
            every price. I come back and give this a refresh every so often, so the date up top tells you how recent these
            numbers are.
        </p>
    </StoryChapter>

        <StoryFurtherReading :links="[
            { label: 'Browse all my fuel data', href: '/fuel' },
            { label: 'More data stories', href: '/stories' },
        ]" />

        <p class="mt-10 text-meta text-neutral-400">Last updated {{ story.kpis.updated }}.</p>

        <StoryAuthor />

        <StoryToc />
    </div>
</template>
