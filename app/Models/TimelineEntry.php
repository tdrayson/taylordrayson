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
     * Article and Note morphs are always excluded from the Eloquent query when
     * the current selection would include them, because those types now come
     * from Statamic (avoiding duplicate entries for migrated posts).
     *
     * @return SupportCollection<int, FeedItem>
     */
    public static function getFeedItems(): SupportCollection
    {
        $models = self::requestedModels();

        // Determine which content types (article / note) are active in the
        // current selection. When no selection is set ($models === null) every
        // type is included, so both content types are active.
        $includeArticles = $models === null || in_array(Article::class, $models, true);
        $includeNotes = $models === null || in_array(Note::class, $models, true);
        $includeContent = $includeArticles || $includeNotes;

        // Build the Eloquent models list, removing Article/Note so they are
        // never returned from TimelineEntry when Statamic is the source.
        $eloquentModels = $models !== null
            ? array_values(array_filter($models, fn (string $m): bool => $m !== Article::class && $m !== Note::class))
            : null;

        // Fetch Eloquent entries (no Article/Note morphs).
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
                fn (Builder $query) => $query->whereNotIn('timelineable_type', [Article::class, Note::class]),
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
     * Resolve the requested timelineable models from the feed query string:
     * `?filter=` selects a named preset, `?types=` a comma-separated list of
     * TypeRegistry keys. Unknown presets/types are ignored, and an empty or
     * absent selection returns null so the feed falls back to every type.
     *
     * @return array<int, class-string>|null
     */
    private static function requestedModels(): ?array
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

        return collect($keys)
            ->map(fn (string $key): string => TypeRegistry::find(trim($key))['model'])
            ->unique()
            ->values()
            ->all();
    }
}
