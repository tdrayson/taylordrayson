<?php

namespace App\Http\Controllers;

use App\Actions\BuildLinkPreviews;
use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Tag;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Queries\FuelEconomy;
use App\Support\LocalTime;
use App\Support\OgMeta;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntryController extends Controller
{
    public function __construct(private readonly FuelEconomy $fuelEconomy) {}

    public function show(int $year, int $month, int $day, string $slug): Response
    {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $entry = TimelineEntry::query()
            ->with('timelineable')
            ->whereDate('occurred_at', $date)
            ->where('url_slug', $slug)
            ->first();

        $model = $entry?->timelineable;
        $model?->setRelation('timelineEntry', $entry);

        // Unpublished articles have no timeline entry (TimelineEntryObserver
        // removes it), so an authenticated preview needs a direct lookup.
        if ($model === null && Auth::check()) {
            $model = Article::query()
                ->whereDate('occurred_at', $date)
                ->where('slug', $slug)
                ->first();
        }

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        if ($model instanceof Article && ! $model->published && ! Auth::check()) {
            throw new NotFoundHttpException;
        }

        if ($model instanceof Flight) {
            $model->load('airline', 'origin', 'destination');
        }

        if ($model instanceof Appearance || $model instanceof Activity || $model instanceof Note) {
            $model->load('media');
        }

        $card = CardPresenter::for($model);

        return Inertia::render('Entry', [
            'type' => $card->type->value,
            'accent' => $card->accent,
            // Notes are title-less by definition; their card title is just
            // truncated content, which the detail body already shows in full.
            'title' => $card->type === TimelineType::Note ? null : $card->title,
            ...$this->occurredFields($model->occurredAtForDisplay(), $model->timezone()),
            'og' => OgMeta::entry($entry, $card->title),
            'dayUrl' => sprintf('/%04d/%02d/%02d', $year, $month, $day),
            'entry' => $model instanceof Calorie
                ? $this->calorieDay($model)
                : $this->entryPayload($model),
            'polyline' => data_get($model, 'meta.polyline'),
            'source' => $this->source($model),
            'linkPreviews' => $model instanceof Article
                ? (new BuildLinkPreviews)($model->content)
                : [],
            // Stream series are large, so they're excluded from the main
            // entry payload and only sent once a profile chart is scrolled
            // into view and requests this deferred prop.
            'profile' => $model instanceof Activity
                ? Inertia::defer(fn (): array => [
                    'heart_rate' => $model->heart_rate,
                    'altitude' => $model->altitude,
                    'speed' => $model->speed,
                    'track' => $model->track,
                ])
                : null,
        ]);
    }

    /**
     * Local-time display fields for the entry header.
     *
     * @return array{occurredAt: string, occurredLabel: string, occurredOffset: string}
     */
    private function occurredFields(CarbonInterface $occurredAt, ?string $timezone): array
    {
        $local = LocalTime::for($occurredAt, $timezone);

        return [
            'occurredAt' => $local['iso'],
            'occurredLabel' => $local['label'],
            'occurredOffset' => $local['offset'],
        ];
    }

    /**
     * Serialise a timeline model for its detail page, dropping audit timestamps
     * and adding the resolved thumbnail URL for media appearances.
     *
     * @return array<string, mixed>
     */
    private function entryPayload(Model $model): array
    {
        // Tags are now a relation rather than a plain attribute; models using
        // HasTags need a {name, slug} shape so entry pages can link each chip
        // to its /tags/{slug} page, not the serialised Tag models toArray()
        // would otherwise produce.
        if (method_exists($model, 'tagNames')) {
            $model->loadMissing('tags');
        }

        $data = Arr::except($model->toArray(), ['created_at', 'updated_at', 'heart_rate', 'altitude', 'speed', 'track']);

        if (method_exists($model, 'tagNames')) {
            $data['tags'] = $model->tags
                ->map(fn (Tag $tag): array => ['name' => $tag->name, 'slug' => $tag->slug])
                ->all();
        }

        if ($model instanceof Appearance) {
            $data['thumbnail'] = $model->thumbnailUrl();
            $data['thumbnailSrcset'] = $model->thumbnailSrcset();
        }

        if ($model instanceof Article) {
            $data['cover'] = $model->coverPhoto();
        }

        if ($model instanceof Activity || $model instanceof Note || $model instanceof Event) {
            $data['photos'] = $model->galleryPhotos();
        }

        if ($model instanceof Media && $model->type === MediaType::Film) {
            $data['backdrop'] = $model->getFirstMediaUrl('backdrop') ?: null;
        }

        if ($model instanceof Event) {
            $address = $this->eventAddress($model);

            $data['location'] = $model->latitude !== null && $model->longitude !== null
                ? [
                    'lat' => (float) $model->latitude,
                    'lng' => (float) $model->longitude,
                    'address' => $address,
                    'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($address),
                ]
                : null;

            // Multi-day badge data ({label, days}), null for single-day events.
            // toArray() only serialises DB columns, so dateRange() (a computed
            // method, not an accessor) needs adding to the payload explicitly.
            $data['range'] = $model->dateRange();
        }

        if (! $model instanceof Event && $model->getAttribute('latitude') !== null && $model->getAttribute('longitude') !== null) {
            $address = trim(implode(', ', array_filter([
                $model->getAttribute('station_name') ?? $model->getAttribute('venue_name'),
                $model->getAttribute('address'),
                $model->getAttribute('postcode'),
                $model->getAttribute('city'),
            ])));

            $data['location'] = [
                'lat' => (float) $model->getAttribute('latitude'),
                'lng' => (float) $model->getAttribute('longitude'),
                'address' => $address,
                'mapsUrl' => 'https://www.google.com/maps/search/?api=1&query='.urlencode($address !== '' ? $address : $model->getAttribute('latitude').','.$model->getAttribute('longitude')),
            ];
        }

        if ($model instanceof Fuel) {
            $economy = ($this->fuelEconomy)($model);
            $data['miles_this_tank'] = $economy['miles'];
            $data['mpg'] = $economy['mpg'];
            $data['vehicle'] = $economy['vehicle'];
        }

        return $data;
    }

    /**
     * Best available address string for maps: the geocoded address stored in
     * meta, else the venue/city/country the event carries.
     */
    private function eventAddress(Event $event): string
    {
        return data_get($event->meta, 'address')
            ?: collect([$event->venue_name, $event->city, $event->country])->filter()->implode(', ');
    }

    /**
     * A food entry represents a whole day's eating, so aggregate every calorie
     * row for the date into day totals plus a per-meal breakdown.
     *
     * @return array{totals: array<string, float|int>, meals: array<int, array<string, mixed>>}
     */
    private function calorieDay(Calorie $model): array
    {
        $items = Calorie::query()
            ->whereDate('occurred_at', $model->occurred_at->toDateString())
            ->orderBy('occurred_at')
            ->get();

        $mealOrder = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snacks' => 3];

        return [
            // True while the day is still today, so the page can flag that more
            // food may yet be logged. Computed server-side to avoid client tz math.
            'inProgress' => $model->occurred_at->isToday(),
            'totals' => [
                'calories' => (int) $items->sum('calories'),
                'protein' => round((float) $items->sum('protein'), 1),
                'carbs' => round((float) $items->sum('carbs'), 1),
                'fat' => round((float) $items->sum('fat'), 1),
                'saturated_fat' => round((float) $items->sum('saturated_fat'), 1),
                'sugars' => round((float) $items->sum('sugars'), 1),
                'fibre' => round((float) $items->sum('fibre'), 1),
                'sodium' => (int) round((float) $items->sum('sodium')),
            ],
            'meals' => $items->groupBy('meal')
                ->map(fn ($group, string $meal): array => [
                    'meal' => $meal,
                    'calories' => (int) $group->sum('calories'),
                    'items' => $group->map(fn (Calorie $item): array => [
                        'name' => $item->name,
                        'calories' => (int) $item->calories,
                        'quantity' => (float) $item->quantity,
                        'units' => $item->units,
                    ])->values()->all(),
                ])
                ->sortBy(fn (array $meal): int => $mealOrder[$meal['meal']] ?? 99)
                ->values()
                ->all(),
        ];
    }

    /**
     * Where this entry's data came from, with a link back to the original when available.
     *
     * @return array{platform: string, url: ?string}|null
     */
    private function source(Model $model): ?array
    {
        $platform = $model->source ?? null;

        if (! $platform) {
            return null;
        }

        return [
            'platform' => $platform,
            'url' => $model->platform_url,
        ];
    }
}
