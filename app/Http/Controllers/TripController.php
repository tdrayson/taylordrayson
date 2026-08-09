<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Trip;
use App\Queries\TripEntries;
use App\Support\LocalTime;
use App\Support\OgMeta;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function __construct(
        private readonly BuildTimelineFeed $feed,
        private readonly TripEntries $entries,
    ) {}

    /**
     * The trip index: every trip with its date range, day count and tags.
     */
    public function index(): Response
    {
        $trips = Trip::query()
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Trip $trip): array => [
                'title' => $trip->title,
                'href' => $trip->url(),
                'days' => $trip->days(),
                'start' => $this->datePartsFor($trip->starts_at, $trip->timezone),
                'end' => $this->datePartsFor($trip->ends_at, $trip->timezone),
                'year' => $trip->starts_at->format('Y'),
            ])
            ->all();

        return Inertia::render('Trips', [
            'og' => OgMeta::trips(),
            'trips' => $trips,
        ]);
    }

    /**
     * One end of a trip window, formatted server-side in the trip's own zone so
     * the client never does date maths: the parts the index strip renders, the
     * spelled-out label the trip page shows, and the machine-readable instant.
     *
     * @return array{day: string, month: string, year: string, time: string, label: string, full: string, iso: string, offset: string}
     */
    private function datePartsFor(CarbonInterface $date, ?string $timezone): array
    {
        $local = LocalTime::for($date, $timezone);
        $zoned = CarbonImmutable::parse($date->format('Y-m-d H:i:s'), $timezone ?: (string) config('app.home_timezone'));

        return [
            'day' => $zoned->format('j'),
            'month' => $zoned->format('M'),
            'year' => $zoned->format('Y'),
            'time' => $local['time'],
            'label' => $zoned->format('j M Y, g:ia'),
            // LocalTime's own weekday-led form, for the timestamp tooltip every
            // timeline card shows on hover.
            'full' => $local['label'],
            'iso' => $local['iso'],
            'offset' => $local['offset'],
        ];
    }

    /**
     * A single trip: every timeline entry inside its window, chronological,
     * rendered with the same cards the timeline itself uses.
     */
    public function show(string $slug): Response
    {
        $trip = Trip::query()->where('slug', $slug)->with('tags')->first();

        abort_if($trip === null, 404);

        return Inertia::render('Trip', [
            'og' => OgMeta::trip($trip->title),
            'title' => $trip->title,
            'days' => $trip->days(),
            'start' => $this->datePartsFor($trip->starts_at, $trip->timezone),
            'end' => $this->datePartsFor($trip->ends_at, $trip->timezone),
            'tags' => $trip->tags->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
            'groups' => $this->feed->groupByDay(($this->entries)($trip)),
        ]);
    }
}
