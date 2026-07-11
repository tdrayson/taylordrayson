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
import FlagList from '../../Components/Ui/FlagList.vue';
import StoryAirline from '../../Components/Story/StoryAirline.vue';
import Abbr from '../../Components/Ui/Abbr.vue';
import FlightsMap from '../../Components/Maps/FlightsMap.vue';
import { PALETTE, baseOptions } from '../../lib/chart.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    story: { type: Object, default: () => ({}) },
});

setLayoutProps({
    breadcrumb: [
        { label: 'Data stories', href: '/stories' },
        { label: 'Flights' },
    ],
});

const s = computed(() => props.story);

// --- formatting helpers ----------------------------------------------------

/**
 * Format a number with thousands separators (86834 becomes "86,834").
 * @param {number} value The number to format.
 * @returns {string}
 */
const n = (value) => Math.round(value).toLocaleString('en-GB');

const dateline = computed(() => [
    `${s.value.kpis.fromLabel} to ${s.value.kpis.toLabel}`,
    `Last updated ${s.value.kpis.updated}`,
]);

const heroKpis = computed(() => [
    { value: n(s.value.kpis.flights), label: 'Flights' },
    { value: n(s.value.kpis.miles), label: 'Miles flown' },
    { value: `${s.value.kpis.days}`, label: 'Days in the air' },
    { value: n(s.value.kpis.countries), label: 'Countries' },
]);

// --- derived narrative figures ---------------------------------------------

const byYear = computed(() => s.value.byYear);

/** The busiest single year, picked out in the per-year chart and the prose. */
const peakYear = computed(() => byYear.value.reduce((most, year) => (year.flights > most.flights ? year : most)));

/** The biggest single gap with no flights at all (the lost-record stretch). */
const biggestGap = computed(() => s.value.gaps[0]);

// --- chart configs ---------------------------------------------------------

/**
 * Flights per year as bars: grey for context, the busiest year (the surge)
 * picked out in the data colour so the lumpy rhythm reads at a glance.
 * @returns {object} A Chart.js bar dataset config.
 */
const flightsChart = computed(() => ({
    labels: byYear.value.map((year) => year.year),
    datasets: [{
        data: byYear.value.map((year) => year.flights),
        backgroundColor: byYear.value.map((year) => (year.flights === peakYear.value.flights ? PALETTE.flight : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const flightsOptions = baseOptions({
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} flights` } } },
    scales: { x: { ticks: { maxRotation: 0, autoSkip: true } } },
});

/** The most-used airports, with the hub far out in front. */
const maxAirport = computed(() => Math.max(...s.value.airports.map((airport) => airport.flights)));

/**
 * The most-used airports as a horizontal bar: Gatwick in the data colour, the
 * rest grey, so its dominance is obvious.
 * @returns {object} A Chart.js horizontal bar dataset config.
 */
const airportsChart = computed(() => ({
    labels: s.value.airports.map((airport) => airport.code),
    datasets: [{
        data: s.value.airports.map((airport) => airport.flights),
        backgroundColor: s.value.airports.map((airport) => (airport.flights === maxAirport.value ? PALETTE.flight : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const airportsOptions = baseOptions({
    indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw} flights` } } },
});

/** The most-flown aircraft, with the everyday workhorse in front. */
const maxAircraft = computed(() => Math.max(...s.value.fleet.top.map((plane) => plane.count)));

/**
 * The most-flown aircraft as a horizontal bar: the workhorse in the data
 * colour, the rest grey.
 * @returns {object} A Chart.js horizontal bar dataset config.
 */
const fleetChart = computed(() => ({
    labels: s.value.fleet.top.map((plane) => plane.name),
    datasets: [{
        data: s.value.fleet.top.map((plane) => plane.count),
        backgroundColor: s.value.fleet.top.map((plane) => (plane.count === maxAircraft.value ? PALETTE.flight : PALETTE.faint)),
        borderRadius: 3,
    }],
}));

const fleetOptions = baseOptions({
    indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: (ctx) => `flown ${ctx.raw} times` } } },
});

/**
 * The window/aisle/middle split as a horizontal bar: window (the clear
 * favourite) in the data colour, the rest grey.
 * @returns {object} A Chart.js horizontal bar dataset config.
 */
const seatsChart = computed(() => ({
    labels: ['Window', 'Aisle', 'Middle'],
    datasets: [{
        data: [s.value.seats.windowPct, s.value.seats.aislePct, s.value.seats.middlePct],
        backgroundColor: [PALETTE.flight, PALETTE.faint, PALETTE.faint],
        borderRadius: 3,
    }],
}));

const seatsOptions = baseOptions({
    indexAxis: 'y',
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw}% of seats` } } },
    scales: { x: { ticks: { callback: (v) => `${v}%` } } },
});
</script>

<template>
    <AppHead :og="og" />

    <StoryHero :meta="dateline" :kpis="heroKpis" icon="AirplaneTakeOff01Icon" accent="var(--color-flight)">
        <template #title>The year I flew somewhere new every month</template>
        <template #lead>
            This is every flight I can still dig up a record of, going back to {{ s.kpis.fromYear }}. It comes to
            {{ n(s.kpis.flights) }} flights and {{ n(s.kpis.miles) }} miles, which sounds like a lot until you realise the
            whole lot adds up to barely {{ s.kpis.days }} days actually off the ground. For ages it was a couple of
            flights a year, then it went a bit mad. Here's the shape of it.
        </template>
    </StoryHero>

    <div class="relative mt-14 max-w-media">
        <StoryChapter number="01" kicker="The shape of it">
        <template #title>A couple of flights a year, then it went mad.</template>
        <p>
            For most of these {{ s.kpis.toYear - s.kpis.fromYear }}-odd years I flew about twice a year, usually a holiday
            out and the same holiday back. Then in <strong>{{ peakYear.year }}</strong> I suddenly took
            <strong>{{ peakYear.flights }} flights</strong>, and the year before wasn't far off either. That isn't me
            getting rich, it's a daft little project I set myself, which I'll get to. The headline is just how lumpy it is:
            years of nothing much, then a great big spike.
        </p>
        <StatCards :stats="[
            { value: n(story.kpis.flights), label: 'Flights on record', tone: 'flight' },
            { value: n(story.kpis.miles), label: 'Miles flown' },
            { value: `${story.kpis.days}`, label: 'Days in the air' },
            { value: `${story.kpis.hours}`, label: 'Hours aloft' },
        ]" />
        <Note label="One caveat">
            The early years are thin because the records are, not because I sat still. Anything before about 2009 is
            mostly lost, so treat these totals as the flights I can still find, not every flight I've ever taken.
        </Note>
    </StoryChapter>

    <StoryChapter number="02" kicker="Flights a year">
        <template #title>The spike you can't miss.</template>
        <p>
            Plotted by year it's pretty stark. A flat little run of twos and threes, a gap where covid grounded everyone in
            2021, and then <strong>{{ peakYear.year }}</strong> sticking up like a sore thumb. The miles don't track it
            neatly either: a single long-haul out to the States does more for the mileage total than a whole summer of
            short European hops.
        </p>
        <Chart
            type="bar"
            label="Flights per year"
            summary="Flights are flat for years, then spike in 2022 and 2023"
            :data="flightsChart"
            :options="flightsOptions"
            :height="220"
        />
    </StoryChapter>

    <StoryChapter number="03" kicker="A new city a month">
        <template #title>My twelve months of coffee.</template>
        <p>
            So here's the daft project, the one I called my <strong>twelve months of coffee</strong>. For a year I flew off
            to a different European city more or less every month, on my
            own, to work from a coffee shop in a city I'd never been to. I'm self-employed, so I just took the laptop and
            went: Milan, Copenhagen, Berlin, Skopje, Prague, then Barcelona, Zurich, Lisbon, Budapest. Cheap flights,
            hostels, a few days each, then home and do it all again the next month.
        </p>
        <p>
            That one idea is basically the whole spike. It's why <strong>{{ peakYear.year }}</strong> has
            {{ peakYear.flights }} flights in it, and why nearly all of them are short out-and-back hops rather than
            anything glamorous.
        </p>
        <Blockquote cite="The cheapest way I've found to see Europe." tone="flight">
            One city a month, solo, on a budget, working as I went. Most of the flights in here are just me chasing that.
        </Blockquote>
    </StoryChapter>

    <StoryChapter number="04" kicker="Longest and shortest">
        <template #title>My longest and shortest flights, from the same trip.</template>
        <p>
            Funny one, this. My longest ever flight is
            <strong><Abbr :title="story.airportNames[story.extremes.longest.origin]">{{ story.extremes.longest.origin }}</Abbr> to <Abbr :title="story.airportNames[story.extremes.longest.destination]">{{ story.extremes.longest.destination }}</Abbr></strong>
            at <strong>{{ n(story.extremes.longest.miles) }} miles</strong>
            (<DateLink :date="story.extremes.longest.date" :label="story.extremes.longest.when" month />), and my shortest is
            <strong><Abbr :title="story.airportNames[story.extremes.shortest.origin]">{{ story.extremes.shortest.origin }}</Abbr> to <Abbr :title="story.airportNames[story.extremes.shortest.destination]">{{ story.extremes.shortest.destination }}</Abbr></strong>
            at just <strong>{{ n(story.extremes.shortest.miles) }} miles</strong>, a quick hop you'd barely call a flight. Both
            were on the same trip: a big loop around the US visiting friends, where I ended up flying everywhere because
            it's the only sensible way to get about over there.
        </p>
        <StatCards :stats="[
            { value: `${n(story.extremes.longest.miles)} mi`, label: `Longest, ${story.extremes.longest.route}`, tone: 'flight' },
            { value: `${n(story.extremes.shortest.miles)} mi`, label: `Shortest, ${story.extremes.shortest.route}` },
            { value: n(story.kpis.miles), label: 'Total miles flown' },
            { value: `${story.kpis.aroundEarth}x`, label: 'Times round the planet' },
        ]" />
        <Note label="The good seats">
            That US trip is also the only time I've turned left on a plane. A handful of those legs were in business or
            premium class, a proper one-off treat, and the only posh seats in the whole dataset.
        </Note>
    </StoryChapter>

    <StoryChapter number="05" kicker="The home airport">
        <template #title>Nearly everything goes through Gatwick.</template>
        <p>
            I live near <strong><Abbr :title="story.airportNames[story.hub.code]">{{ story.hub.code }}</Abbr></strong>, and
            it shows: it turns up in <strong>{{ story.hub.flights }} of my {{ story.hub.total }} flights</strong>, which is
            {{ story.hub.pct }}% of the lot. The next busiest airport only appears {{ story.hub.next }} times. Gatwick
            isn't anyone's idea of a glamorous hub, but it's a twenty-five-minute drive and it flies cheaply to half of
            Europe, which is exactly what the whole twelve months of coffee needed.
        </p>
        <Chart
            type="bar"
            label="Flights through each airport"
            summary="Gatwick dominates, with every other airport far behind"
            :data="airportsChart"
            :options="airportsOptions"
            :height="220"
        />
    </StoryChapter>

    <StoryChapter number="06" kicker="The reach">
        <template #title>One airport, but the map still fills up.</template>
        <p>
            For all that one airport does the heavy lifting, it's still got me to
            <strong>{{ story.kpis.airports }} airports</strong> across
            <strong>{{ story.kpis.countries }} countries</strong>, with {{ story.kpis.international }} of the
            {{ story.kpis.flights }} flights crossing a border. Here's everywhere I've actually touched down.
        </p>
        <FlightsMap :routes="story.routes" color="var(--color-flight)" :bleed="false" />
        <p>
            Those lines touch down in a fair spread of places, from quick hops across the Channel to the odd long-haul
            that snuck in. Here's the full set, one flag each:
        </p>
        <FlagList :codes="story.countries" />
    </StoryChapter>

    <StoryChapter number="07" kicker="Budget travel">
        <template #title>Cheap airlines are how I see Europe.</template>
        <p>
            None of this works without budget airlines.
            <StoryAirline name="easyJet" :icon="story.budget.easyjetIcon" :href="story.budget.easyjetHref" /> alone is
            <strong>{{ story.budget.easyjetPct }}% of my flights</strong> ({{ story.budget.easyjet }} of them), and once
            you add the other low-cost lot it's about half of everything I've flown. That's the whole method really:
            no-frills carriers are what made twelve months of coffee actually affordable, so I just put up with the orange
            seats and the 6am departures.
        </p>
        <StatCards :stats="[
            { value: `${story.budget.easyjetPct}%`, label: 'On easyJet', tone: 'flight' },
            { value: `${story.budget.budgetPct}%`, label: 'On budget airlines' },
            { value: n(story.kpis.airlines), label: 'Different airlines' },
        ]" />
    </StoryChapter>

    <StoryChapter number="08" kicker="The fleet">
        <template #title>A lot of little Airbuses, and one giant.</template>
        <p>
            All those short hops mean I've spent most of my flying life on the same handful of small planes:
            <strong>{{ story.fleet.top[0].name }}</strong> and its close cousins do the bulk of the work. But I've been on
            <strong>{{ story.fleet.types }} different aircraft types</strong> in total, and the fun ones are right at the
            bottom of the list.
        </p>
        <Chart
            type="bar"
            label="Most-flown aircraft types"
            summary="A few small Airbus types do most of the flying"
            :data="fleetChart"
            :options="fleetOptions"
            :height="220"
        />
        <p v-if="story.fleet.a380 || story.fleet.jumbo">
            The treats were the rare wide-bodies. I've flown the
            <strong>Airbus A380</strong> exactly once
            <template v-if="story.fleet.a380">
                (<DateLink :date="story.fleet.a380.date" :label="story.fleet.a380.when" month />, coming back from the
                States)</template>, the only double-decker in here, and a couple of old
            <strong>Boeing 747s</strong> on a family holiday to Jamaica back in 2009. After a steady diet of easyJet A319s,
            stepping onto a jumbo feels faintly ridiculous.
        </p>
    </StoryChapter>

    <StoryChapter number="09" kicker="Where I sit">
        <template #title>A window-seat person, clearly.</template>
        <p>
            Of the flights where I noted it down, I picked a <strong>window seat {{ story.seats.windowPct }}% of the
            time</strong>, with aisle and middle splitting the rest. I'll happily climb over a whole row of people just to
            get my head against the glass and watch the ground go by. Some habits really don't budge.
        </p>
        <Chart
            type="bar"
            label="Seat choice, where recorded"
            summary="Window seats are the clear favourite, ahead of aisle and middle"
            :data="seatsChart"
            :options="seatsOptions"
            :height="180"
        />
    </StoryChapter>

    <StoryChapter number="10" kicker="Work trips">
        <template #title>The only proper work flights are once a year.</template>
        <p>
            Almost all of this is me travelling for fun: only <strong>{{ story.purpose.business }} flights</strong> were
            actually for work. They're all the same thing, a WordPress conference called WordCamp Europe that lands in a
            new city every June. Porto, Athens, Turin, Basel, Krakow, one each year, always the first week of the month.
            Everything else, the twelve months of coffee included, was purely for me.
        </p>
        <StatCards :stats="[
            { value: n(story.purpose.personal), label: 'Personal flights', tone: 'flight' },
            { value: n(story.purpose.business), label: 'Work flights' },
            { value: `${story.purpose.businessPct}%`, label: 'Flown for work' },
        ]" />
    </StoryChapter>

    <StoryChapter number="11" kicker="The gaps">
        <template #title>Still flying, still keeping the log.</template>
        <p>
            The empty stretches tell their own story. There's the obvious one in 2021, when covid grounded everybody, and
            then the long quiet gap of about <strong>{{ biggestGap.years }} years</strong> ({{ biggestGap.from }} to
            {{ biggestGap.to }}), which is really just where the old records run out rather than a spell of staying put.
        </p>
        <p>
            My very first flight on record was {{ story.kpis.firstRoute }} back in {{ story.kpis.fromYear }}, and the most
            recent was only {{ story.kpis.updated }}. I'll keep adding to this as I go, and I refresh the story every now
            and then, so the date up top is how current these numbers are.
        </p>
    </StoryChapter>

        <StoryFurtherReading :links="[
            { label: 'Browse all my flights', href: '/flights' },
            { label: 'More data stories', href: '/stories' },
        ]" />

        <p class="mt-10 text-meta text-neutral-400">Last updated {{ story.kpis.updated }}.</p>

        <StoryAuthor />

        <TableOfContents selector="[data-story-chapter]" label-attr="data-kicker" number-attr="data-number" />
    </div>
</template>
