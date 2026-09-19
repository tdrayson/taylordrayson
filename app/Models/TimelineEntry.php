<?php

namespace App\Models;

use App\Datasets\Dataset;
use App\Datasets\Datasets;
use App\Enums\EntryStatus;
use App\Models\Scopes\ListedScope;
use App\Presenters\CardPresenter;
use App\Presenters\EntryDescription;
use App\Support\SqlDate;
use App\Timeline\FeedPresets;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

#[ScopedBy([ListedScope::class])]
#[Fillable([
    'dataset',
    'entry_id',
    'occurred_at',
    'ends_at',
    'occurred_utc',
    'url_slug',
    'status',
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
            'occurred_utc' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EntryStatus::class,
        ];
    }

    public function entry(): MorphTo
    {
        return $this->morphTo('entry', 'dataset', 'entry_id');
    }

    /**
     * Relations each entry's card() reads, so feeds can eager-load them
     * and avoid N+1 queries (flight endpoints/airline, appearance cover thumbnail).
     *
     * @return array<class-string, array<int, string>>
     */
    public static function cardRelations(): array
    {
        return [
            Flight::class => ['origin', 'destination', 'airline', 'media'],
            Appearance::class => ['media'],
            Activity::class => ['media'],
            Article::class => ['media', 'citation'],
            Event::class => ['media'],
            Fuel::class => ['media'],
            Place::class => ['media'],
            Note::class => ['citation'],
            Film::class => ['media'],
            // `tvShow` names the show on an episode card, and carries the
            // backdrop an episode has none of its own. Without these every
            // episode in the feed resolves its show, and both their
            // attachments, one query at a time.
            TvEpisode::class => ['tvShow', 'media', 'tvShow.media'],
            Book::class => ['media'],
        ];
    }

    /**
     * Eager-load the polymorphic entry together with every relation its
     * card() needs.
     */
    public function scopeWithCardRelations(Builder $query): Builder
    {
        return $query->with(['entry' => fn (MorphTo $morphTo) => $morphTo->morphWith(self::cardRelations())]);
    }

    /**
     * Entries whose date span covers the given Y-m-d. Single-day entries
     * (ends_at null) collapse to their occurred_at day; multi-day events
     * match every day from occurred_at through ends_at inclusive.
     */
    /**
     * Ordered by when entries actually happened, rather than by what the local
     * clock said: 09:00 in London and 09:00 in New York are five hours apart.
     *
     * Selecting and grouping stay on `occurred_at`, so a day is still the local
     * day. The coalesce covers a row whose instant has not been derived yet.
     */
    public function scopeOrderByInstant(Builder $query, string $direction = 'desc'): Builder
    {
        $order = $direction === 'asc' ? 'asc' : 'desc';

        // Broken by id, or day-granular types would order arbitrarily against
        // each other: food and vitals share an end-of-day instant exactly.
        return $query
            ->orderByRaw("COALESCE(occurred_utc, occurred_at) {$order}")
            ->orderBy('id', $order);
    }

    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query
            ->whereRaw('DATE(occurred_at) <= ?', [$date])
            ->whereRaw('DATE(COALESCE(ends_at, occurred_at)) >= ?', [$date]);
    }

    /**
     * Entries whose date span covers the given m-d in any year (for on-this-day).
     * Handles ranges within a single calendar year; a range crossing a month
     * boundary matches each covered month-day. Multi-year-spanning ranges are an
     * accepted edge (no current data spans them).
     */
    public function scopeCoveringAnniversary(Builder $query, string $monthDay): Builder
    {
        return $query
            ->whereRaw(SqlDate::monthDay('occurred_at').' <= ?', [$monthDay])
            ->whereRaw(SqlDate::monthDay('COALESCE(ends_at, occurred_at)').' >= ?', [$monthDay]);
    }

    public function toFeedItem(): FeedItem
    {
        $this->entry->setRelation('timelineEntry', $this);

        $card = CardPresenter::for($this->entry);
        $link = url($this->entry->url());

        return FeedItem::create([
            'id' => $link,
            'title' => $card->title,
            // The standalone sentence, not the card subtitle: a subtitle is
            // written to sit under its title, and a check-in without a note has
            // none at all.
            'summary' => EntryDescription::for($this->entry, $card) ?? '',
            'updated' => $this->occurred_at,
            'link' => $link,
            'authorName' => config('feed.author_name'),
            'authorEmail' => config('feed.author_email'),
            'category' => $card->type->value,
        ]);
    }

    /**
     * @return Collection<int, TimelineEntry>
     */
    public static function getFeedItems(): Collection
    {
        $models = self::requestedModels();

        return self::query()
            ->when($models !== null, fn (Builder $query) => $query->whereHasMorph('entry', $models))
            ->withCardRelations()
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->entry !== null)
            ->values();
    }

    /**
     * Resolve the requested entry models from the feed query string:
     * `?filter=` selects a named preset, `?types=` a comma-separated list of
     * dataset keys. Unknown presets/types are ignored, and an empty or absent
     * selection returns null so the feed falls back to every type.
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
            $keys = explode(',', $types);
        }

        if (empty($keys)) {
            return null;
        }

        $models = collect($keys)
            ->map(fn (string $key): ?Dataset => Datasets::for(trim($key)))
            ->filter()
            ->map(fn (Dataset $dataset): string => $dataset->model())
            ->unique()
            ->values()
            ->all();

        return $models === [] ? null : $models;
    }
}
