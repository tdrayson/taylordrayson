<?php

namespace App\Actions\Og;

use App\Data\CardData;
use App\Data\SegmentData;
use App\Enums\TimelineType;
use App\Models\Concerns\Timelineable;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Presenters\CardPresenter;
use App\Support\OgPhrases;
use App\Support\StaticMap;
use App\Support\TypeColors;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Build the Open Graph card payload for a single timeline entry: type accent,
 * eyebrow, title, date, and a contextual image (route map, check-in marker,
 * or cover) where one fits.
 */
final class BuildEntryOgData
{
    public const ACCENT_DEFAULT = '3858e9';

    /**
     * Card type to the eyebrow label shown on its OG card.
     *
     * @var array<string, string>
     */
    public const TYPE_EYEBROWS = [
        'activity' => 'Activity',
        'flight' => 'Flight',
        'checkin' => 'Places',
        'media' => 'Media',
        'sleep' => 'Sleep',
        'calorie' => 'Food',
        'fuel' => 'Fuel',
        'note' => 'Note',
        'article' => 'Article',
        'project' => 'Project',
        'event' => 'Event',
        'appearance' => 'Appearance',
        'podcast' => 'This Week With',
    ];

    /**
     * Sleep stage bar colours, mirroring the --color-sleep-* tokens in
     * resources/css/app.css (hsl converted to hex for the server-rendered card).
     *
     * @var array<string, string>
     */
    private const SLEEP_STAGE_COLORS = [
        'awake' => '#ea8686',
        'rem' => '#5494d4',
        'light' => '#9fbfdf',
        'deep' => '#5247c2',
    ];

    /**
     * Build the card view data for a single entry from its real data: type accent,
     * eyebrow, title, date, and a contextual image. Returns null when the entry has
     * no timelineable model.
     *
     * $cutout is resolved lazily (only once the entry is known to render a card),
     * matching the original controller's behaviour of skipping the data-uri read
     * entirely for entries with no timelineable model.
     *
     * @return array<string, mixed>|null
     */
    public function __invoke(TimelineEntry $entry, Closure $cutout): ?array
    {
        $model = $entry->timelineable;

        if (! $model instanceof Timelineable) {
            return null;
        }

        if ($model instanceof Flight) {
            $model->load('origin', 'destination');
        }

        $card = CardPresenter::for($model);
        $accent = TypeColors::hex($card->accent, self::ACCENT_DEFAULT);
        [$layout, $image] = $this->entryImage($model, $card, $accent);

        // Seed the wording with the id and last-updated stamp, so editing an
        // entry rerolls its phrase (matching when the card itself regenerates).
        $seed = $entry->id.'|'.self::entryTimestamp($entry);

        return [
            'layout' => $layout,
            'accent' => $accent,
            'eyebrow' => self::TYPE_EYEBROWS[$card->type->value] ?? null,
            'title' => $this->entryTitle($model, $card, $seed),
            'date' => $entry->occurred_at->format('D j M Y'),
            'subtitle' => null,
            'image' => $image,
            'stages' => $card->type === TimelineType::Sleep ? $this->sleepStages($card->meta->segments ?? []) : null,
            'cutout' => $cutout(),
        ];
    }

    /**
     * The entry's last-updated timestamp (the timelineable model's, falling back
     * to the entry's), used to key the cache, seed the wording, and bust the
     * card's URL when the entry it describes changes.
     */
    public static function entryTimestamp(TimelineEntry $entry): int
    {
        return $entry->timelineable?->updated_at?->timestamp ?? $entry->updated_at?->timestamp ?? 0;
    }

    /**
     * Build the sleep stage bar segments (label, colour, width percent) from the
     * card's per-stage seconds. Returns an empty array when there is no data.
     *
     * Public: also used to build the sample sleep card for the OG gallery.
     *
     * @param  list<SegmentData>  $segments
     * @return array<int, array{label: string, color: string, percent: float}>
     */
    public function sleepStages(array $segments): array
    {
        $total = array_sum(array_column($segments, 'seconds'));

        if ($total <= 0) {
            return [];
        }

        return array_map(fn (SegmentData $segment): array => [
            'label' => $segment->label,
            'color' => self::SLEEP_STAGE_COLORS[$segment->stage] ?? '#'.self::ACCENT_DEFAULT,
            'percent' => round($segment->seconds / $total * 100, 2),
        ], $segments);
    }

    /**
     * The card headline for an entry. Stat entries (sleep, food, fuel, podcast)
     * get a personable, varied phrase built from their real numbers; everything
     * else keeps its real title.
     */
    private function entryTitle(Model $model, CardData $card, string $seed): string
    {
        $phrase = match ($card->type) {
            TimelineType::Sleep => OgPhrases::pick('sleep', ['duration' => str_replace(' sleep', '', $card->title)], $seed),
            TimelineType::Calorie => OgPhrases::pick('food', ['kcal' => trim(str_replace('kcal', '', $card->title))], $seed),
            TimelineType::Fuel => OgPhrases::pick('fuel', ['cost' => number_format((float) $model->cost, 2), 'litres' => $model->litres], $seed),
            TimelineType::Podcast => $model->season_number && $model->episode_number
                ? OgPhrases::pick('podcast', ['season' => $model->season_number, 'episode' => $model->episode_number], $seed)
                : null,
            default => null,
        };

        return Str::limit($phrase ?? trim($card->title), 160, '');
    }

    /**
     * Choose the card layout and background image for an entry: a route map for
     * activities/flights, a marker for check-ins, otherwise the type's own
     * gradient (text layout).
     *
     * Only generated imagery goes behind the text. A cover used to, blurred, but
     * a poster or an article header reduced to a smear reads as a rendering
     * fault rather than a backdrop; the type colour says more and says it
     * cleanly.
     *
     * @return array{0: string, 1: ?string} The [layout, image URL] pair.
     */
    private function entryImage(Model $model, CardData $card, string $accent): array
    {
        $type = $card->type;

        if ($type === TimelineType::Activity) {
            if ($url = StaticMap::route($card->meta->polyline, $accent)) {
                return ['media', $url];
            }
        }

        if ($type === TimelineType::Flight) {
            $route = $card->meta->route;
            $url = StaticMap::arc(
                $this->floatOrNull($route?->origin->lng),
                $this->floatOrNull($route?->origin->lat),
                $this->floatOrNull($route?->destination->lng),
                $this->floatOrNull($route?->destination->lat),
                $accent,
            );

            if ($url) {
                return ['media', $url];
            }
        }

        if ($type === TimelineType::Checkin) {
            $latitude = $this->floatOrNull($model->latitude);
            $longitude = $this->floatOrNull($model->longitude);

            if ($latitude !== null && $longitude !== null) {
                return ['media', StaticMap::marker($longitude, $latitude, $accent)];
            }
        }

        return ['text', null];
    }

    /**
     * Cast a value to a float, or null when it is not numeric.
     */
    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
