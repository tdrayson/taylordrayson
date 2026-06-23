<?php

namespace App\Timeline;

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
            'activity' => self::type(Activity::class, 'activities', 'Activities', self::column('type', 'Type', fn (string $label): string => "{$label} activities")),
            'sleep' => self::type(Sleep::class, 'sleep', 'Sleep'),
            'calorie' => self::type(Calorie::class, 'food', 'Food'),
            'media' => self::type(Media::class, 'media', 'Media', self::media()),
            'event' => self::type(Event::class, 'events', 'Events', self::column('type', 'Type', fn (string $label): string => "{$label} events")),
            'appearance' => self::type(Appearance::class, 'appearances', 'Appearances', self::column('type', 'Type', fn (string $label): string => "{$label} appearances")),
            'podcast' => self::type(Podcast::class, 'this-week-with', 'This Week With', null, 'episode'),
            'flight' => self::type(Flight::class, 'flights', 'Flights', self::airline()),
            'checkin' => self::type(Checkin::class, 'places', 'Places', self::column('category', 'Category', fn (string $label): string => Str::plural($label))),
            'fuel' => self::type(Fuel::class, 'fuel', 'Fuel', self::vehicle()),
            'project' => self::type(Project::class, 'projects', 'Projects', self::tags(fn (string $label): string => "Projects tagged {$label}")),
            'article' => self::type(Article::class, 'articles', 'Articles', self::tags(fn (string $label): string => "Articles tagged {$label}")),
            'note' => self::type(Note::class, 'notes', 'Notes'),
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
     * Slugs are matched back with `whereIn` so divergent encodings of the same thing
     * (e.g. "weight training" and "weight_training" → weight-training) both resolve.
     */
    private static function column(string $column, string $label, ?callable $title = null): callable
    {
        $distinct = fn (string $model): Collection => $model::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => $column,
            'label' => $label,
            'title' => $title,
            'filter' => fn (Builder $query, string $value) => $query->whereIn($column, self::resolveSlugs($distinct($model), $value) ?: [$value]),
            'labelFor' => fn (string $value): string => Str::headline(self::resolveSlugs($distinct($model), $value)[0] ?? $value),
            'values' => fn (): Collection => $distinct($model)
                ->map(fn ($value): array => ['value' => Str::slug($value), 'label' => Str::headline($value)])
                ->unique('value')->values(),
        ];
    }

    /**
     * A taxonomy over a JSON `tags` array, addressed by tag slug.
     */
    private static function tags(?callable $title = null): callable
    {
        $distinct = fn (string $model): Collection => $model::query()->pluck('tags')->flatten()->filter()->unique()->sort()->values();

        return fn (string $model, string $slug): array => [
            'base' => $slug,
            'param' => 'tag',
            'label' => 'Tag',
            'title' => $title,
            'filter' => fn (Builder $query, string $value) => $query->whereJsonContains('tags', self::resolveSlug($distinct($model), $value) ?? $value),
            'labelFor' => fn (string $value): string => self::resolveSlug($distinct($model), $value) ?? Str::headline($value),
            'values' => fn (): Collection => $distinct($model)->map(fn ($tag): array => ['value' => Str::slug($tag), 'label' => $tag]),
        ];
    }

    private static function media(): callable
    {
        $map = ['films' => ['film'], 'tv' => ['tv', 'tv_episode'], 'books' => ['book']];
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
            'labelFor' => fn (string $value): string => config("vehicles.{$value}.name", $value),
            'values' => fn (): Collection => collect(config('vehicles', []))
                ->map(fn (array $vehicle, string $id): array => ['value' => $id, 'label' => $vehicle['name'] ?? $id])->values(),
        ];
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
