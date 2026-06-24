<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
     * @return Collection<int, TimelineEntry>
     */
    public static function getFeedItems(): Collection
    {
        return self::query()
            ->withCardRelations()
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();
    }
}
