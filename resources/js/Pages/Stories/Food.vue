<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StoryHero from '../../Components/Story/StoryHero.vue';
import StoryChapter from '../../Components/Story/StoryChapter.vue';
import StatCards from '../../Components/Ui/StatCards.vue';
import Note from '../../Components/Ui/Note.vue';
import Blockquote from '../../Components/Ui/Blockquote.vue';
import Chart from '../../Components/Ui/Chart.vue';
import StoryAuthor from '../../Components/Story/StoryAuthor.vue';
import StoryFurtherReading from '../../Components/Story/StoryFurtherReading.vue';
import TableOfContents from '../../Components/Ui/TableOfContents.vue';
import DateLink from '../../Components/Ui/DateLink.vue';
import { PALETTE, baseOptions, tooltip } from '../../lib/chart.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    story: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [
        { label: 'Data stories', href: '/stories' },
        { label: 'Food' },
    ],
});

const s = computed(() => props.story);


/**
 * Format a number with thousands separators (5467 becomes "5,467").
 * @param {number} value The number to format.
 * @returns {string}
 */
const n = (value) => Math.round(value).toLocaleString('en-GB');

/**
 * Capitalise the first letter of a word (breakfast becomes Breakfast).
 * @param {string} word The word to capitalise.
 * @returns {string}
 */
const cap = (word) => word.charAt(0).toUpperCase() + word.slice(1);

/**
 * Turn a YYYY-MM-DD date into a short "D Mon" axis label.
 * @param {string} date An ISO date string.
 * @returns {string}
 */
const shortDate = (date) => new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

const dateline = computed(() => [
    `${s.value.kpis.fromLabel} to ${s.value.kpis.toLabel}`,
    `Last updated ${s.value.kpis.updated}`,
]);

const heroKpis = computed(() => [
    { value: n(s.value.kpis.days), label: 'Days tracked' },
    { value: n(s.value.kpis.items), label: 'Items logged' },
    { value: `${(s.value.kpis.totalKcal / 1e6).toFixed(2)}M`, label: 'Calories' },
    { value: n(s.value.kpis.avgPerDay), label: 'Average a day' },
]);


const byYear = computed(() => s.value.byYear);

/** The year with the most low (flare) days, picked out in the chart and prose. */
const flareYear = computed(() => byYear.value.reduce((worst, year) => (year.lowDays > worst.lowDays ? year : worst)));

/** The leanest-protein year (the pre-gym trough) and the most recent year. */
const proteinLow = computed(() => byYear.value.reduce((low, year) => (year.avgProtein < low.avgProtein ? year : low)));
const proteinNow = computed(() => byYear.value[byYear.value.length - 1]);

/** The biggest meal of the day (dinner), highlighted in its chart. */
const dinner = computed(() => s.value.meals.find((meal) => meal.meal === 'dinner'));
const maxMealPct = computed(() => Math.max(...s.value.meals.map((meal) => meal.pct)));


/**
 * All-time macro split as a doughnut: carbs (the lead) in the data colour, the
 * rest in greys so the point of interest is unambiguous.
 * @returns {object} A Chart.js doughnut dataset config.
 */
const macrosChart = computed(() => ({
    labels: ['Carbs', 'Fat', 'Protein'],
    datasets: [{
        data: [s.value.macros.carbs.pct, s.value.macros.fat.pct, s.value.macros.protein.pct],
        backgroundColor: [PALETTE.food, PALETTE.mid, PALETTE.faint],
        borderWidth: 0,
        hoverOffset: 4,
    }],
}));

const macrosOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '64%',
    plugins: {
        legend: { position: 'bottom', labels: { color: PALETTE.mid, boxWidth: 10, boxHeight: 10, padding: 18 } },
        tooltip: { ...tooltip, callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw}%` } },
    },
};

/**
 * Low (flare) days per year as bars: grey for context, the worst year (the
 * run-up to surgery) in the data colour.
 * @returns {object} A Chart.js bar dataset config.
 */
const flareChart = computed(() => ({
    labels: byYear.value.map((year) => year.year),
    datasets: [{
        data: byYear.value.map((year) => year.lowDays),
        backgroundColor: byYear.value.map((year) => (year.lowDays === flareYear.value.lowDays ? PALETTE.food : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const flareOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} days under 1,200 kcal` } } },
});

/**
 * Daily intake across the surgery window (Dec 2022 to Mar 2023): the hospital
 * crash and the steroid rebound, as one continuous line.
 * @returns {object} A Chart.js line dataset config.
 */
const surgeryChart = computed(() => ({
    labels: s.value.surgery.series.map((point) => shortDate(point.date)),
    datasets: [{
        data: s.value.surgery.series.map((point) => point.kcal),
        borderColor: PALETTE.food,
        backgroundColor: PALETTE.foodSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 0,
        borderWidth: 2,
    }],
}));

const surgeryOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${n(ctx.raw)} kcal` } } },
    scales: {
        x: { ticks: { maxTicksLimit: 6, maxRotation: 0, autoSkip: true } },
        y: { ticks: { callback: (v) => n(v) } },
    },
});

/**
 * Average protein per day by year, the one thing I've tried to change.
 * @returns {object} A Chart.js line dataset config.
 */
const proteinChart = computed(() => ({
    labels: byYear.value.map((year) => year.year),
    datasets: [{
        data: byYear.value.map((year) => year.avgProtein),
        borderColor: PALETTE.food,
        backgroundColor: PALETTE.foodSoft,
        fill: true,
        tension: 0.3,
        pointRadius: 3,
        pointBackgroundColor: PALETTE.food,
        borderWidth: 2,
    }],
}));

const proteinOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} g protein a day` } } },
    scales: { y: { ticks: { callback: (v) => `${v} g` } } },
});

/**
 * Cans of Coke logged per year as bars: grey for context, the 2021 peak in the
 * data colour, so the decline reads at a glance.
 * @returns {object} A Chart.js bar dataset config.
 */
const fizzyChart = computed(() => ({
    labels: s.value.fizzy.series.map((point) => point.year),
    datasets: [{
        data: s.value.fizzy.series.map((point) => point.count),
        backgroundColor: s.value.fizzy.series.map((point) => (point.year === s.value.fizzy.peak.year ? PALETTE.food : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const fizzyOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} logged` } } },
});

/**
 * Share of calories by meal of the day, as a horizontal bar: dinner (the
 * biggest plate) in the data colour, the rest grey.
 * @returns {object} A Chart.js horizontal bar dataset config.
 */
const mealsChart = computed(() => ({
    labels: s.value.meals.map((meal) => cap(meal.meal)),
    datasets: [{
        data: s.value.meals.map((meal) => meal.pct),
        backgroundColor: s.value.meals.map((meal) => (meal.pct === maxMealPct.value ? PALETTE.food : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const mealsOptions = baseOptions({
    indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw}% of calories` } } },
    scales: { x: { ticks: { callback: (v) => `${v}%` } } },
});

/**
 * My most-logged foods of all time, as a horizontal bar in the data colour.
 * @returns {object} A Chart.js horizontal bar dataset config.
 */
const topFoodsChart = computed(() => ({
    labels: s.value.topFoods.map((food) => food.name.split(',')[0]),
    datasets: [{
        data: s.value.topFoods.map((food) => food.count),
        backgroundColor: PALETTE.food,
        borderRadius: 3,
    }],
}));

const topFoodsOptions = baseOptions({
    indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: (ctx) => `logged ${ctx.raw} times` } } },
});
</script>

<template>
    <AppHead :og="og" />

    <StoryHero :meta="dateline" :kpis="heroKpis" icon="UtensilsIcon" accent="var(--color-food)">
        <template #title>The most-logged thing in my diet is coffee</template>
        <template #lead>
            Here's a weird one. I've written down pretty much every single thing I've eaten since {{ s.kpis.fromLabel }}.
            Not for a diet: I started to work out what was setting off my Crohn's, and to nag myself into eating more.
            {{ n(s.kpis.days) }} days later I still haven't missed one, mostly because I'd hate to break the streak, and
            honestly because I find the data more interesting than the food.
        </template>
    </StoryHero>

    <div class="relative mt-14 max-w-media">
        <StoryChapter number="01" kicker="The streak">
        <template #title>{{ n(story.streak.days) }} days, not one missed.</template>
        <p>
            <strong>{{ n(story.streak.days) }} days</strong> in a row, and I've logged every single one since
            {{ story.streak.start }}. It didn't start as some big obsession. I'd just been diagnosed with Crohn's and was
            trying to work out what set off a flare, and since I've never really been bothered about eating, the app
            constantly asking what I'd had for breakfast was also a decent way to make myself actually eat something.
        </p>
        <p>
            I got 100 days in, didn't want to lose the streak, and, well, here we are. Every full year since is a
            complete 365.
        </p>
        <StatCards :stats="[
            { value: n(story.streak.days), label: 'Day logging streak', tone: 'food' },
            { value: n(story.kpis.items), label: 'Items logged' },
            { value: `${(story.kpis.totalKcal / 1e6).toFixed(2)}M`, label: 'Calories in total' },
        ]" />
    </StoryChapter>

    <StoryChapter number="02" kicker="An average day">
        <template #title>About {{ n(story.kpis.avgPerDay) }} calories a day.</template>
        <p>
            Across the lot I average <strong>{{ n(story.kpis.avgPerDay) }} kcal a day</strong>, with a median of
            {{ n(story.distribution.median) }}, so it's pretty consistent really, spread over about
            {{ story.kpis.avgItems }} separate entries a day. The lightest day was a grim
            <strong>{{ n(story.distribution.low.kcal) }} kcal</strong>
            (<DateLink :date="story.distribution.low.date" :label="story.distribution.low.when" />); the biggest was a
            <strong>{{ n(story.distribution.high.kcal) }} kcal</strong> day
            (<DateLink :date="story.distribution.high.date" :label="story.distribution.high.when" />). There's a story
            behind both, and it's the same one.
        </p>
        <StatCards :stats="[
            { value: n(story.kpis.avgPerDay), label: 'Average day', tone: 'food' },
            { value: n(story.distribution.median), label: 'Median day' },
            { value: n(story.distribution.low.kcal), label: `Lightest, ${story.distribution.low.when}` },
            { value: n(story.distribution.high.kcal), label: `Heaviest, ${story.distribution.high.when}` },
        ]" />
    </StoryChapter>

    <StoryChapter number="03" kicker="The macro split">
        <template #title>Mostly carbs, if I'm honest.</template>
        <p>
            Split by where the calories actually come from, I'm a carbs man: <strong>{{ story.macros.carbs.pct }}% carbs</strong>,
            <strong>{{ story.macros.fat.pct }}% fat</strong>, <strong>{{ story.macros.protein.pct }}% protein</strong>
            across the whole lot. Not exactly a bodybuilder's ratio, but it has been slowly shifting, as you'll see.
        </p>
        <Chart
            type="doughnut"
            label="Where my calories come from"
            summary="A calorie-weighted macro split, led by carbohydrate"
            :data="macrosChart"
            :options="macrosOptions"
            :height="240"
        />
        <Note label="FYI">
            Not every food in here has full macro data attached, so the protein, carbs and fat totals are a slight
            undercount. The calorie figures themselves are solid.
        </Note>
    </StoryChapter>

    <StoryChapter number="04" kicker="Being ill">
        <template #title>You can see the bad years in the data.</template>
        <p>
            The numbers go quiet when I'm unwell. I was diagnosed with Crohn's back in 2017, and the years before my
            surgery were rough: <strong>{{ flareYear.year }} alone had {{ flareYear.lowDays }} days under 1,200 kcal</strong>,
            a lot of them barely-eating days while I was flaring. You can see the bad patches in the data, often before I
            really knew what was going on myself, which is, funnily enough, exactly why I started logging in the first place.
        </p>
        <Chart
            type="bar"
            label="Days under 1,200 kcal, per year"
            summary="Low-intake days peak in 2022, the run-up to surgery"
            :data="flareChart"
            :options="flareOptions"
            :height="220"
        />
    </StoryChapter>

    <StoryChapter number="05" kicker="Surgery">
        <template #title>The clearest dot on the whole chart.</template>
        <p>
            On <strong><DateLink date="2023-01-26" label="26 January 2023" /></strong> I had ileostomy surgery, and you
            can spot it in the data without checking a calendar. I more or less stopped eating in hospital while I
            recovered (that {{ n(story.distribution.low.kcal) }}-kcal day is in there), then went onto steroids, which
            give you a massive appetite, and it all swung the other way. My lowest 30-day stretch averaged
            <strong>{{ n(story.surgery.crash.avg) }} kcal a day</strong>, immediately followed by my highest ever at
            <strong>{{ n(story.surgery.rebound.avg) }}</strong>.
        </p>
        <Chart
            type="line"
            label="Calories a day, December 2022 to March 2023"
            summary="The hospital crash followed by the steroid rebound"
            :data="surgeryChart"
            :options="surgeryOptions"
            :height="220"
        />
        <Blockquote cite="The bit the chart can't show." tone="food">
            I haven't had a proper flare since. The surgery worked, and the line settling back to normal afterwards is
            the happiest thing in this whole dataset.
        </Blockquote>
    </StoryChapter>

    <StoryChapter number="06" kicker="The gym">
        <template #title>The one thing I've tried to change.</template>
        <p>
            Once I was better I joined the gym (David Lloyd, since you ask) and started actually caring about protein. It
            had dropped to <strong>{{ proteinLow.avgProtein }} g a day in {{ proteinLow.year }}</strong>, the worst of the
            illness; it's up around <strong>{{ proteinNow.avgProtein }} g</strong> now, and the carbs have quietly dropped
            back to make room. It's pretty much the only thing about how I eat that I've actively tried to change.
        </p>
        <Chart
            type="line"
            label="Average protein a day, by year"
            summary="Protein climbs steadily from the 2022 trough"
            :data="proteinChart"
            :options="proteinOptions"
            :height="200"
        />
    </StoryChapter>

    <StoryChapter number="07" kicker="Giving up fizzy">
        <template #title>I used to drink a worrying amount of fizz.</template>
        <p>
            Coke was the main offender, but it was Pepsi, Fanta, Tango, the lot. Add them all up and at the
            <strong>{{ story.fizzy.peak.year }} peak I logged {{ n(story.fizzy.peak.count) }}</strong> fizzy drinks in a
            year; this year, after deciding to knock the fizzy stuff on the head, I'm down to
            <strong>{{ n(story.fizzy.latest.count) }}</strong>. Just a bit of willpower, showing up in the numbers.
        </p>
        <Chart
            type="bar"
            label="Fizzy drinks logged, per year"
            summary="The fizzy-drink habit peaks in 2021, then drops off a cliff"
            :data="fizzyChart"
            :options="fizzyOptions"
            :height="220"
        />
    </StoryChapter>

    <StoryChapter number="08" kicker="Meals">
        <template #title>Dinner does most of the heavy lifting.</template>
        <p>
            Of all the calories I log, <strong>dinner takes {{ dinner.pct }}%</strong>, comfortably the biggest plate of
            the day, with lunch close behind and breakfast the afterthought it has always been for me. (There are no clock
            times in the data, just which meal I tagged each thing as.)
        </p>
        <Chart
            type="bar"
            label="Share of calories by meal"
            summary="Dinner is the largest share, breakfast the smallest"
            :data="mealsChart"
            :options="mealsOptions"
            :height="200"
        />
    </StoryChapter>

    <StoryChapter number="09" kicker="Signature foods">
        <template #title>A creature of habit, clearly.</template>
        <p>
            Group it all up sensibly, every cappuccino and latte counted as coffee, every Can Of Coke as Coke, and it's a
            bit grim, honestly. <strong>Coffee is comfortably the most-logged thing in here</strong>, with Coke close
            behind, then orange juice and milk. The top of my whole food diary is, basically, just drinks. I run on
            caffeine, sugar and habit, and the chart isn't going to let me pretend otherwise.
        </p>
        <Chart
            type="bar"
            label="My most-logged foods, grouped"
            summary="Coffee, Coke, orange juice and milk lead the list"
            :data="topFoodsChart"
            :options="topFoodsOptions"
            :height="240"
        />
    </StoryChapter>

    <StoryChapter number="10" kicker="Still logging">
        <template #title>Eating to survive, logging for fun.</template>
        <p>
            So why am I still at it, {{ n(story.streak.days) }} days deep? Partly the streak. Mostly because I genuinely
            find the data more interesting than the food. The honest truth is I eat to survive rather than for pleasure,
            food is an afterthought, which is a bit ridiculous for someone who's logged every single calorie of it.
        </p>
        <p>
            I'm healthier now than I've ever been, {{ proteinNow.year }} is shaping up as my biggest-eating year yet, and
            the only thing keeping the odd day near-empty is that I've moved into my own flat, can't really cook, and
            forget to eat. I'll keep logging regardless, the habit isn't going anywhere, and I refresh this story every
            now and then, so the date up top is how current these numbers are.
        </p>
    </StoryChapter>

        <StoryFurtherReading :links="[
            { label: 'Browse all my food data', href: '/food' },
            { label: 'More data stories', href: '/stories' },
        ]" />

        <p class="mt-10 text-meta text-neutral-400">Last updated {{ story.kpis.updated }}.</p>

        <StoryAuthor />

        <TableOfContents selector="[data-story-chapter]" label-attr="data-kicker" number-attr="data-number" />
    </div>
</template>
