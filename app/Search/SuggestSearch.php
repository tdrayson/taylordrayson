<?php

namespace App\Search;

use App\Presenters\CardPresenter;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Free-text search across timeline entries for the command palette, plus the
 * matching taxonomy destination pages, ordered by recency.
 */
final class SuggestSearch
{
    private const LIMIT = 8;

    private const DESTINATION_LIMIT = 6;

    private const PER_TYPE = 5;

    /**
     * Searchable text columns per timeline type. Types without meaningful free
     * text (e.g. sleep) are intentionally omitted.
     *
     * @var array<string, array<int, string>>
     */
    private const SEARCHABLE = [
        'activity' => ['name'],
        'calorie' => ['name', 'meal'],
        'media' => ['title'],
        'event' => ['name', 'venue_name', 'city', 'country'],
        'appearance' => ['title', 'show_name', 'description'],
        'podcast' => ['topic', 'show_notes'],
        'flight' => ['flight_number', 'origin_iata', 'destination_iata', 'reason'],
        'checkin' => ['venue_name', 'category', 'city', 'description'],
        'fuel' => ['station_name', 'city'],
        'project' => ['title', 'description', 'status'],
        'note' => ['content'],
        'article' => ['title', 'excerpt', 'content'],
    ];

    /**
     * @param  SearchCompiler  $compiler  Only its guardPublished() gate is used here,
     *                                    so an unpublished article never surfaces in
     *                                    the palette for a guest.
     */
    public function __construct(private readonly SearchCompiler $compiler) {}

    /**
     * Match the term against the palette's free-text hits and taxonomy destinations.
     *
     * @param  string  $term  The free-text query (already length-checked by the caller).
     * @return array{results: array<int, array{title: string, subtitle: ?string, type: string, url: string, date: string}>, destinations: array<int, array{label: string, section: string, type: string, tag: bool, url: string}>}
     */
    public function __invoke(string $term): array
    {
        $registry = TypeRegistry::all();

        $results = collect(array_keys(self::SEARCHABLE))
            ->flatMap(fn (string $key): array => $this->searchType(
                $registry[$key]['model'],
                $key,
                self::SEARCHABLE[$key],
                $term,
            ))
            ->sortByDesc(fn (array $result): int => $result['occurred_at']->getTimestamp())
            ->take(self::LIMIT)
            ->map(fn (array $result): array => [
                'title' => $result['title'],
                'subtitle' => $result['subtitle'],
                'type' => $result['type'],
                'url' => $result['url'],
                'date' => $result['occurred_at']->format('j M Y'),
            ])
            ->values()
            ->all();

        return [
            'results' => $results,
            'destinations' => $this->matchDestinations($term),
        ];
    }

    /**
     * Taxonomy pages (e.g. /activities/run, /places/london) whose label matches
     * the term, drawn live from the registry so newly logged values appear with
     * no code change. Prefix hits rank above mid-word hits, then shorter labels.
     *
     * @param  string  $term  The free-text query.
     * @return array<int, array{label: string, section: string, type: string, tag: bool, url: string}>
     */
    private function matchDestinations(string $term): array
    {
        $needle = Str::lower($term);

        return collect(TypeRegistry::all())
            ->flatMap(function (array $definition, string $type) use ($needle): array {
                $taxonomy = $definition['taxonomy'];

                if ($taxonomy === null) {
                    return [];
                }

                return $taxonomy['values']()
                    ->filter(fn (array $value): bool => str_contains(Str::lower($value['label']), $needle))
                    ->map(fn (array $value): array => [
                        'label' => $value['label'],
                        // The taxonomy's own kind label, not the owning type's: a tag
                        // jump is a "Tag", not an "Articles" entry.
                        'section' => $taxonomy['label'],
                        'type' => $type,
                        'tag' => $taxonomy['param'] === 'tag',
                        // Tags go to the cross-type feed, so the same tag on several
                        // types collapses to one destination (deduped by url below).
                        'url' => $taxonomy['param'] === 'tag'
                            ? '/tags/'.$value['value']
                            : '/'.$taxonomy['base'].'/'.$value['value'],
                        'rank' => str_starts_with(Str::lower($value['label']), $needle) ? 0 : 1,
                    ])
                    ->all();
            })
            ->sortBy(fn (array $destination): string => sprintf('%d %03d %s', $destination['rank'], mb_strlen($destination['label']), Str::lower($destination['label'])))
            ->unique('url')
            ->take(self::DESTINATION_LIMIT)
            ->map(fn (array $destination): array => [
                'label' => $destination['label'],
                'section' => $destination['section'],
                'type' => $destination['type'],
                'tag' => $destination['tag'],
                'url' => $destination['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * Match a single type's text columns and shape each hit for the palette.
     *
     * @param  class-string  $model
     * @param  array<int, string>  $columns
     * @return array<int, array{title: string, subtitle: ?string, type: string, url: string, occurred_at: Carbon}>
     */
    private function searchType(string $model, string $type, array $columns, string $term): array
    {
        $query = $model::query();

        $this->compiler->guardPublished($query, $model);

        $query->where(function (Builder $builder) use ($columns, $term): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', "%{$term}%");
            }
        })
            ->orderByDesc('occurred_at')
            ->limit(self::PER_TYPE);

        if ($type === 'flight') {
            $query->with(['origin', 'destination', 'airline']);
        }

        return $query->get()
            ->map(function ($entry): array {
                $card = CardPresenter::for($entry);

                return [
                    'title' => $card->title,
                    'subtitle' => $card->subtitle,
                    'type' => $card->type->value,
                    'url' => $entry->url(),
                    'occurred_at' => $entry->occurred_at,
                ];
            })
            ->all();
    }
}
