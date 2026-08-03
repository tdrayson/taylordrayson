<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Trip;
use App\Queries\TripEntries;
use App\Support\OgMeta;
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
                'start' => $this->datePartsFor($trip->starts_at),
                'end' => $this->datePartsFor($trip->ends_at),
                'year' => $trip->starts_at->format('Y'),
                'spansYears' => $trip->starts_at->format('Y') !== $trip->ends_at->format('Y'),
            ])
            ->all();

        return Inertia::render('Trips', [
            'og' => OgMeta::trips(),
            'trips' => $trips,
        ]);
    }

    /**
     * Split a date into the parts the index card's date strip renders, so the
     * client never does date maths of its own.
     *
     * @return array{day: string, month: string, year: string}
     */
    private function datePartsFor(CarbonInterface $date): array
    {
        return [
            'day' => $date->format('j'),
            'month' => $date->format('M'),
            'year' => $date->format('Y'),
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
            'start' => $trip->starts_at->format('j M Y'),
            'end' => $trip->ends_at->format('j M Y'),
            'tags' => $trip->tags->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
            'groups' => $this->feed->groupByDay(($this->entries)($trip)),
        ]);
    }
}
