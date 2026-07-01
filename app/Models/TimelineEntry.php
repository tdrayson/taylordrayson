<?php

namespace App\Models;

use App\Content\ContentFeed;
use App\Content\ContentRepository;
use App\Timeline\FeedPresets;
use App\Timeline\TypeRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection as SupportCollection;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

#[Fillable([
    'timelineable_type',
    'timelineable_id',
    'occurred_at',
])]
class TimelineEntry extends Model implements Feedable
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function timelineable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Relations each timelineable's card() reads, so feeds can eager-load them
     * and avoid N+1 queries (flight endpoints/airline, appearance cover thumbnail).
     *
     * @return array<class-string, array<int, string>>
     */
    public static function cardRelations(): array
    {
        return [
            Flight::class => ['origin', 'destination', 'airline'],
            Appearance::class => ['media'],
            Activity::class => ['media'],
        ];
    }

    /**
     * Eager-load the polymorphic timelineable together with every relation its
     * card() needs.
     */
    public function scopeWithCardRelations(Builder $query): Builder
    {
        return $query->with(['timelineable' => fn (MorphTo $morphTo) => $morphTo->morphWith(self::cardRelations())]);
    }

    public function toFeedItem(): FeedItem
    {
        $card = $this->timelineable->card();
        $link = url($this->timelineable->url());

        return FeedItem::create([
            'id' => $link,
            'title' => $card['title'],
            'summary' => $card['subtitle'] ?? $card['title'],
            'updated' => $this->occurred_at,
            'link' => $link,
            'authorName' => config('feed.author_name'),
            'authorEmail' => config('feed.author_email'),
            'category' => $card['type'],
        ]);
    }

    /**
     * Build the feed item list by merging Eloquent timeline entries with
     * Statamic-sourced articles and notes, honouring the ?filter= / ?types=
     * selection and applying the 50-item cap to the merged, sorted set.
     *
     * Article and Note content always comes exclusively from Statamic; they
     * have no Eloquent rows and their models no longer exist.
     *
     * @return SupportCollection<int, FeedItem>
     */
    public static function getFeedItems(): SupportCollection
    {
        $requestedKeys = self::requestedTypeKeys();

        // Determine which content types (article / note) are active in the
        // current selection. When no selection is set (null) every type is
        // included, so both content types are active.
        $includeArticles = $requestedKeys === null || in_array('article', $requestedKeys, true);
        $includeNotes = $requestedKeys === null || in_array('note', $requestedKeys, true);
        $includeContent = $includeArticles || $includeNotes;

        // Resolve Eloquent model class strings for non-content types only.
        // Article/Note have no Eloquent models anymore, so they are excluded.
        $eloquentModels = self::resolveEloquentModels($requestedKeys);

        // Fetch Eloquent entries (no Article/Note morph rows exist after migration).
        // ->toBase() converts the Eloquent Collection to a plain SupportCollection
        // so the subsequent merge() accepts non-model values (FeedItem instances).
        $eloquentItems = self::query()
            ->when(
                $eloquentModels !== null,
                fn (Builder $query) => count($eloquentModels) > 0
                    ? $query->whereHasMorph('timelineable', $eloquentModels)
                    : $query->whereRaw('0 = 1'),
            )
            ->when(
                $eloquentModels === null,
                fn (Builder $query) => $query->whereNotIn('timelineable_type', ['App\\Models\\Article', 'App\\Models\\Note']),
            )
            ->withCardRelations()
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->toBase()
            ->map(fn (TimelineEntry $entry): FeedItem => $entry->toFeedItem());

        if (! $includeContent) {
            // No content types requested; return Eloquent items limited to 50.
            return $eloquentItems->take(50)->values();
        }

        // Fetch Statamic content entries filtered to the requested types.
        /** @var ContentRepository $repo */
        $repo = app(ContentRepository::class);

        $contentEntries = match (true) {
            $includeArticles && $includeNotes => $repo->all(),
            $includeArticles => $repo->articles(),
            default => $repo->notes(),
        };

        $contentItems = $contentEntries->map(fn ($entry) => ContentFeed::toFeedItem($entry));

        // Merge, sort newest-first, cap at 50.
        return $eloquentItems
            ->merge($contentItems)
            ->sortByDesc(fn (FeedItem $item): int => $item->updated->timestamp)
            ->take(50)
            ->values();
    }

    /**
     * Resolve the requested TypeRegistry keys from the feed query string:
     * `?filter=` selects a named preset, `?types=` a comma-separated list of
     * TypeRegistry keys. Unknown presets/types are ignored, and an empty or
     * absent selection returns null so the feed falls back to every type.
     *
     * @return array<int, string>|null
     */
    private static function requestedTypeKeys(): ?array
    {
        $request = request();
        $keys = null;

        if (is_string($filter = $request->query('filter'))) {
            $keys = FeedPresets::types($filter);
        } elseif (is_string($types = $request->query('types'))) {
            $keys = array_filter(
                explode(',', $types),
                fn (string $key): bool => TypeRegistry::find(trim($key)) !== null,
            );
        }

        if (empty($keys)) {
            return null;
        }

        return array_values($keys);
    }

    /**
     * Resolve Eloquent model class strings for non-content type keys.
     * Article and Note have no Eloquent models (removed in Task 16), so they
     * are always excluded from the returned list.
     *
     * @param  array<int, string>|null  $typeKeys  TypeRegistry keys, or null for all types.
     * @return array<int, class-string>|null Null means "all Eloquent types".
     */
    private static function resolveEloquentModels(?array $typeKeys): ?array
    {
        if ($typeKeys === null) {
            // No filter: all types requested; Eloquent path excludes article/note via whereNotIn.
            return null;
        }

        return collect($typeKeys)
            ->map(fn (string $key): ?string => TypeRegistry::find(trim($key))['model'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
