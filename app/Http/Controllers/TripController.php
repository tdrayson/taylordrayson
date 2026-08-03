<?php

namespace App\Http\Controllers;

use App\Actions\BuildTimelineFeed;
use App\Models\Trip;
use App\Queries\TripEntries;
use App\Support\OgMeta;
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
            ->with('tags')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Trip $trip): array => [
                'title' => $trip->title,
                'href' => $trip->url(),
                'days' => $trip->days(),
                'start' => $trip->starts_at->format('j M Y'),
                'end' => $trip->ends_at->format('j M Y'),
                'year' => $trip->starts_at->format('Y'),
                'tags' => $trip->tags->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])->all(),
            ])
            ->all();

        return Inertia::render('Trips', [
            'og' => OgMeta::trips(),
            'trips' => $trips,
        ]);
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
