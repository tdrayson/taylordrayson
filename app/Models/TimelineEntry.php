<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
            ->with('timelineable')
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->filter(fn (TimelineEntry $entry): bool => $entry->timelineable !== null)
            ->values();
    }
}
