<?php

namespace App\Timeline;

use App\Enums\MediaType;
use App\Enums\TimelineType;
use App\Models\Activity;
use App\Models\Airline;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Calorie;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Podcast;
use App\Models\Project;
use App\Models\Sleep;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Drives the per-type archive pages and their taxonomy sub-routes. Keyed by the
 * card `type` string. Closures (not config) so each type owns its filter quirks.
 */
class TypeRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            TimelineType::Activity->value => self::type(Activity::class, 'activities', 'Activities', self::column('type', 'Type', fn (string $label): string => "{$label} activities")),
            TimelineType::Sleep->value => self::type(Sleep::class, 'sleep', 'Sleep'),
            TimelineType::Calorie->value => self::type(Calorie::class, 'food', 'Food'),
            TimelineType::Media->value => self::type(Media::class, 'media', 'Media', self::media()),
            TimelineType::Event->value => self::type(Event::class, 'events', 'Events', self::column('type', 'Type', fn (string $label): string => "{$label} events")),
            TimelineType::Appearance->value => self::type(Appearance::class, 'appearances', 'Appearances', self::column('type', 'Type', fn (string $label): string => "{$label} appearances")),
            TimelineType::Podcast->value => self::type(Podcast::class, 'this-week-with', 'This Week With', self::podcastSeason(), 'episode'),
            TimelineType::Flight->value => self::type(Flight::class, 'flights', 'Flights', self::airline()),
            TimelineType::Checkin->value => self::type(Checkin::class, 'places', 'Places', self::column('category', 'Category', fn (string $label): string => Str::plural($label))),
            TimelineType::Fuel->value => self::type(Fuel::class, 'fuel', 'Fuel', self::vehicle()),
            TimelineType::Project->value => self::type(Project::class, 'projects', 'Projects', self::tags(fn (string $label): string => "Projects tagged {$label}")),
            TimelineType::Article->value => self::type(Article::class, 'articles', 'Articles', self::tags(fn (string $label): string => "Articles tagged {$label}")),
            TimelineType::Note->value => self::type(Note::class, 'notes', 'Notes', self::tags(fn (string $label): string => "Notes tagged {$label}")),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    /**
     * @param  class-string  $model
     * @return array<string, mixed>
     */
    private static function type(string $model, string $slug, string $label, ?callable $taxonomyFactory = null, ?string $noun = null): array
    {
        return [
            'slug' => $slug,
            'model' => $model,
            'label' => $label,
            'noun' => $noun ?? Str::lower(Str::singular($label)),
            'taxonomy' => $taxonomyFactory ? $taxonomyFactory($model, $slug) : null,
        ];
    }

    /**
     * A taxonomy over a column, addressed in the URL by a slug (dashes) of the value.
     * Stored types are kebab-case (e.g. "weight-training"), so the slug is usually
     * the value itself; slugs are still matched back with `whereIn` so any legacy
     * encoding ("weight training", "weight_training") resolves to the same page.
     */
    private static function column(string $column, string $label, ?callable $title = null): callable
    {
        $distinct = fn (string $model): Collection => $model::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        // Value counts, most-used first, so the archive filter can lead with the
        // categories actually visited most and collapse the long tail into a
        // searchable "more" popover (e.g. Places has 200 categories).
        $counts = fn (string $model): Collection => $model::query()
            ->whereNotNull($column)
            ->selectRaw($column.' as value, count(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->pluck('total', 'value');

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => $column,
            'label' => $label,
            'title' => $title,
            'filter' => fn (Builder $query, string $value) => $query->whereIn($column, self::resolveSlugs($distinct($model), $value) ?: [$value]),
            'labelFor' => fn (string $value): string => Str::headline(self::resolveSlugs($distinct($model), $value)[0] ?? $value),
            'values' => fn (): Collection => $counts($model)
                ->map(fn (int $total, $value): array => ['value' => Str::slug((string) $value), 'label' => Str::headline((string) $value), 'count' => $total])
                ->unique('value')->values(),
        ];
    }

    /**
     * A taxonomy over the relational `tags` table, addressed by tag slug.
     * Scoped to tags attached to at least one record of the given model.
     * Articles are the only publish-gated type: a guest must never see (or
     * resolve) a tag that is attached only to unpublished articles, so the
     * taggables subquery is further restricted to published articles when
     * there's no authenticated viewer. Authed users (the owner) still see
     * draft-only tags, matching how they see draft articles elsewhere.
     */
    private static function tags(?callable $title = null): callable
    {
        $distinct = fn (string $model): Collection => Tag::query()
            ->whereIn('id', fn ($query) => $query->select('tag_id')
                ->from('taggables')
                ->where('taggable_type', $model)
                ->when($model === Article::class && ! Auth::check(), fn (QueryBuilder $query) => $query->whereExists(
                    fn (QueryBuilder $exists) => $exists->selectRaw('1')
                        ->from('articles')
                        ->whereColumn('articles.id', 'taggables.taggable_id')
                        ->where('articles.published', true)
                )))
            ->orderBy('name')
            ->get(['name', 'slug']);

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => 'tag',
            'label' => 'Tag',
            'title' => $title,
            'filter' => fn (Builder $query, string $value) => $query->whereHas('tags', fn (Builder $t) => $t->where('slug', $value)),
            'labelFor' => fn (string $value): string => $distinct($model)->firstWhere('slug', $value)?->name ?? Str::headline($value),
            'values' => fn (): Collection => $distinct($model)
                ->map(fn (Tag $tag): array => ['value' => $tag->slug, 'label' => $tag->name]),
        ];
    }

    private static function media(): callable
    {
        $map = [
            'films' => [MediaType::Film->value],
            'tv' => [MediaType::TvEpisode->value],
            'books' => [MediaType::Book->value],
        ];
        $labels = ['films' => 'Films', 'tv' => 'TV', 'books' => 'Books'];

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => 'type',
            'label' => 'Type',
            'filter' => fn (Builder $query, string $value) => $query->whereIn('type', $map[$value] ?? ['__none__']),
            'labelFor' => fn (string $value): string => $labels[$value] ?? Str::headline($value),
            'values' => fn (): Collection => collect($map)->keys()->map(fn ($value): array => ['value' => $value, 'label' => $labels[$value]]),
        ];
    }

    /**
     * A taxonomy over the podcast's integer season number, addressed in the URL
     * by the bare number (e.g. /this-week-with/3). Chips read "Season N" and run
     * in season order rather than the usual most-used-first, since seasons have a
     * natural sequence.
     */
    private static function podcastSeason(): callable
    {
        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => 'season',
            'label' => 'Season',
            // The leading chip reads "All Seasons" rather than the default
            // "All This Week With", which the type label would produce.
            'allLabel' => 'All Seasons',
            'filter' => fn (Builder $query, string $value) => $query->where('season_number', (int) $value),
            'labelFor' => fn (string $value): string => "Season {$value}",
            'values' => fn (): Collection => $model::query()
                ->whereNotNull('season_number')
                ->selectRaw('season_number as value, count(*) as total')
                ->groupBy('season_number')
                ->orderBy('season_number')
                ->get()
                ->map(fn ($row): array => [
                    'value' => (string) $row->value,
                    'label' => "Season {$row->value}",
                    'count' => (int) $row->total,
                ]),
        ];
    }

    private static function airline(): callable
    {
        $airlines = function (string $model): Collection {
            $icaos = $model::query()->whereNotNull('airline_icao')->distinct()->pluck('airline_icao');

            return Airline::query()->whereIn('icao_code', $icaos)->orderBy('name')->get();
        };

        $forSlug = fn (Collection $airlines, string $slug): Collection => $airlines
            ->filter(fn (Airline $airline): bool => Str::slug($airline->name) === $slug);

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => 'airline',
            'label' => 'Airline',
            'title' => fn (string $label): string => "Flights with {$label}",
            'filter' => fn (Builder $query, string $value) => $query->whereIn(
                'airline_icao',
                $forSlug($airlines($model), $value)->pluck('icao_code')->map('strtoupper')->all() ?: ['__none__'],
            ),
            'labelFor' => fn (string $value): string => $forSlug($airlines($model), $value)->first()?->name ?? Str::headline($value),
            'values' => fn (): Collection => $airlines($model)
                ->map(fn (Airline $airline): array => [
                    'value' => Str::slug($airline->name),
                    'label' => $airline->name,
                    'icon' => $airline->icon_url,
                ])
                ->unique('value')->values(),
        ];
    }

    private static function vehicle(): callable
    {
        return fn (string $model, string $slug): array => [
            'base' => 'vehicles',
            'param' => 'vehicle',
            'label' => 'Vehicle',
            'title' => fn (string $label): string => "Fuel for {$label}",
            'filter' => fn (Builder $query, string $value) => $query->where('vehicle_id', $value),
            'labelFor' => fn (string $value): string => self::vehicleLabel($value),
            'values' => fn (): Collection => collect(config('vehicles', []))
                ->map(fn (array $vehicle, string $id): array => [
                    'value' => $id,
                    'label' => self::vehicleLabel($id),
                ])->values(),
        ];
    }

    /**
     * Display name for a vehicle config entry, e.g. "Toyota Aygo".
     */
    private static function vehicleLabel(string $id): string
    {
        $vehicle = config("vehicles.{$id}");

        if (! is_array($vehicle)) {
            return $id;
        }

        $label = trim(($vehicle['make'] ?? '').' '.($vehicle['model'] ?? ''));

        return $label !== '' ? $label : $id;
    }

    /**
     * @param  Collection<int, string>  $values
     */
    private static function resolveSlug(Collection $values, string $slug): ?string
    {
        return $values->first(fn (string $value): bool => Str::slug($value) === $slug);
    }

    /**
     * Every stored value whose slug matches (handles divergent encodings).
     *
     * @param  Collection<int, string>  $values
     * @return array<int, string>
     */
    private static function resolveSlugs(Collection $values, string $slug): array
    {
        return $values->filter(fn (string $value): bool => Str::slug($value) === $slug)->values()->all();
    }
}
