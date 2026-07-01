<?php

namespace App\Content;

use Spatie\Feed\FeedItem;

/**
 * Maps ContentEntry instances (Statamic articles and notes) to Spatie FeedItem
 * objects, mirroring the shape produced by TimelineEntry::toFeedItem().
 */
class ContentFeed
{
    /**
     * Convert a ContentEntry to a FeedItem suitable for inclusion in any feed.
     *
     * - id/link: absolute URL built from url()
     * - title:   card title
     * - summary: card subtitle (excerpt for articles) or title if absent
     * - updated: occurredAt()
     * - author:  from config feed.author_name / feed.author_email
     * - category: type() ('article' or 'note')
     */
    public static function toFeedItem(ContentEntry $entry): FeedItem
    {
        $card = $entry->card();
        $link = url($entry->url());

        return FeedItem::create([
            'id' => $link,
            'title' => $card['title'],
            'summary' => $card['subtitle'] ?? $card['title'],
            'updated' => $entry->occurredAt(),
            'link' => $link,
            'authorName' => config('feed.author_name'),
            'authorEmail' => config('feed.author_email'),
            'category' => $entry->type(),
        ]);
    }
}
